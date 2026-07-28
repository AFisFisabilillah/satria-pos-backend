<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockSupplyRequest;
use App\Http\Resources\DetailStockSupplyResource;
use App\Http\Resources\StockSupplyResource;
use App\Models\StockSupply;
use Carbon\Carbon;

class StockSupplyController extends Controller
{
    public function index()
    {
        $search = request()->query('search');
        $startDate = request()->query('startDate');
        $endDate = request()->query('endDate');
        $size = request()->query('size',10);

        $stockSupply = StockSupply::query();
        $stockSupply->when($search, function ($query, $search) {
            $query->whereHas("suplier", function ($query) use ($search) {
                $query->where("name", "like", "%{$search}%");
            })->orWhere("invoice_number", "like", "%{$search}%");
        });
        $stockSupply->when($startDate, function ($query, $startDate) {
            $query->where("date", ">=", $startDate);
        });
        $stockSupply->when($endDate, function ($query, $endDate) {
            $query->where("date", "<=", $endDate);
        });

        return StockSupplyResource::collection($stockSupply->with("suplier")->paginate($size));
    }

    public function store(StockSupplyRequest $request)
    {
        $data = $request->validated();
        $data["date"] = \Illuminate\Support\now();
        $stockSupply = StockSupply::create($data);
        $stockSupplyItem = array_map(function ($item) {
            return [
                "product_id" => $item["product_id"],
                "quantity_in" => $item["quantity_in"],
                "quantity_remaining" => $item["quantity_in"], // default is the quantity in
                "purchase_price" => $item["purchase_price"],
                "expired_at" => $item["expired_date"],
            ];
        }, $data["products"]);

        $stockSupply->stockSupplyItem()->createMany($stockSupplyItem);
        return new StockSupplyResource($stockSupply->load(['suplier', 'stockSupplyItem']));
    }

    public function show(StockSupply $stockSupply){
        $stockSupply->with(["stockSupplyItem", "suplier", 'stockSupplyItem.product']);
        return new DetailStockSupplyResource($stockSupply);
    }

    public function update(StockSupplyRequest $request, StockSupply $stockSupply){
        $data = $request->validated();

        $stockSupply->update([
            'suplier_id' => $data['suplier_id'],
            'invoice_number' => $data['invoice_number'],
            'notes' => $data['notes']
        ]);

        $incomingIds = array_filter(array_column($data['products'], 'id'));
        $existingItems = $stockSupply->stockSupplyItem;

        // Delete items that are not in the payload
        foreach ($existingItems as $existing) {
            if (!in_array($existing->id, $incomingIds)) {
                if ($existing->quantity_in !== $existing->quantity_remaining) {
                    return response()->json([
                        'message' => 'Cannot delete item because stock has already been used.'
                    ], 422);
                }
                $existing->delete();
            }
        }

        foreach ($data['products'] as $item) {
            if (isset($item['id'])) {
                $existing = $existingItems->firstWhere('id', $item['id']);
                if ($existing) {
                    $diff = $item['quantity_in'] - $existing->quantity_in;
                    $newRemaining = $existing->quantity_remaining + $diff;

                    if ($newRemaining < 0) {
                        return response()->json([
                            'message' => 'Updated quantity_in is too low, some stock is already consumed.'
                        ], 422);
                    }

                    $existing->update([
                        'product_id' => $item['product_id'],
                        'quantity_in' => $item['quantity_in'],
                        'quantity_remaining' => $newRemaining,
                        'purchase_price' => $item['purchase_price'],
                        'expired_at' => $item['expired_date'],
                    ]);
                }
            } else {
                // Create new item
                $stockSupply->stockSupplyItem()->create([
                    'product_id' => $item['product_id'],
                    'quantity_in' => $item['quantity_in'],
                    'quantity_remaining' => $item['quantity_in'],
                    'purchase_price' => $item['purchase_price'],
                    'expired_at' => $item['expired_date'],
                ]);
            }
        }

        return new StockSupplyResource($stockSupply->refresh()->load(['suplier', 'stockSupplyItem']));
    }

    public function destroy(StockSupply $stockSupply)
    {
        // Cek secara efisien apakah ada item yang stoknya sudah dipakai
        $hasUsedStock = $stockSupply->stockSupplyItem()
            ->whereColumn('quantity_in', '!=', 'quantity_remaining')
            ->exists();

        if ($hasUsedStock) {
            return response()->json([
                'message' => 'Cannot delete stock supply because one or more items have already been consumed.'
            ], 422);
        }

        $stockSupply->delete();

        return response()->json([
            'message' => 'Stock supply deleted successfully.'
        ]);
    }
}
