<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierReturnRequest;
use App\Http\Resources\SupplierReturnResource;
use App\Models\StockSupplyItem;
use App\Models\SupplierReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierReturnController extends Controller
{
    public function index()
    {
        $size = request()->query('size', 10);
        $returns = SupplierReturn::with(['suplier', 'items.stockSupplyItem.product'])
            ->latest()
            ->paginate($size);

        return SupplierReturnResource::collection($returns);
    }

    public function store(SupplierReturnRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            while (SupplierReturn::where('return_number', $returnNumber)->exists()) {
                $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }

            $supplierReturn = SupplierReturn::create([
                'suplier_id' => $data['suplier_id'],
                'return_number' => $returnNumber,
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $stockItem = StockSupplyItem::where('id', $item['stock_supply_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($stockItem->quantity_remaining < $item['quantity']) {
                    throw new \Exception("Stok tidak mencukupi untuk diretur pada batch ID {$stockItem->id}. Sisa: {$stockItem->quantity_remaining}, Diminta: {$item['quantity']}");
                }

                $supplierReturn->items()->create([
                    'stock_supply_item_id' => $stockItem->id,
                    'quantity' => $item['quantity'],
                    'reason' => $item['reason'] ?? null,
                ]);

                $stockItem->decrement('quantity_remaining', $item['quantity']);
            }

            DB::commit();

            return new SupplierReturnResource($supplierReturn->load(['suplier', 'items.stockSupplyItem.product']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses retur ke suplier.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(SupplierReturn $supplierReturn)
    {
        return new SupplierReturnResource($supplierReturn->load(['suplier', 'items.stockSupplyItem.product']));
    }
}