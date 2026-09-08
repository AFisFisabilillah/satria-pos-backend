<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockSupplyRequest;
use App\Http\Resources\DetailStockSupplyResource;
use App\Http\Resources\StockSupplyResource;
use App\Models\StockSupply;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class StockSupplyController extends Controller
{
    #[OA\Get(
        path: '/api/stock-supply',
        summary: 'List Stock Supplies',
        security: [['bearerAuth' => []]],
        tags: ['Stock Supply'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'startDate', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'endDate', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of stock supplies',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            [
                                'id' => 1,
                                'suplier_id' => 2,
                                'invoice_number' => 'INV-SUP-123',
                                'date' => '2026-08-01',
                                'notes' => 'Supply bulanan',
                                'suplier' => ['id' => 2, 'name' => 'PT Suplier ABC']
                            ]
                        ]
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $search = request()->query('search');
        $startDate = request()->query('startDate');
        $endDate = request()->query('endDate');
        $size = request()->query('size',10);
        $supplier = request()->query('supplier');

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
        $stockSupply->when($supplier, function ($query, $supplier) {
            $query->where('suplier_id', $supplier);
        });
        return StockSupplyResource::collection($stockSupply->with(["suplier", "stockSupplyItem"])->orderBy("stock_supplies.created_at", \SortDirection::Descending)->paginate($size));
    }

    #[OA\Post(
        path: '/api/stock-supply',
        summary: 'Create Stock Supply',
        security: [['bearerAuth' => []]],
        tags: ['Stock Supply'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['suplier_id', 'invoice_number', 'products'],
                properties: [
                    new OA\Property(property: 'suplier_id', type: 'integer'),
                    new OA\Property(property: 'invoice_number', type: 'string'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'products',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer'),
                                new OA\Property(property: 'quantity_in', type: 'integer'),
                                new OA\Property(property: 'purchase_price', type: 'integer'),
                                new OA\Property(property: 'expired_date', type: 'string', format: 'date', nullable: true)
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Stock supply created',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'suplier_id' => 1,
                            'invoice_number' => 'INV-SUP-123',
                            'date' => '2026-08-01',
                            'items' => [
                                [
                                    'id' => 1,
                                    'product_id' => 5,
                                    'quantity_in' => 100,
                                    'quantity_remaining' => 100,
                                    'purchase_price' => 20000
                                ]
                            ]
                        ]
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: '/api/stock-supply/{stockSupply}',
        summary: 'Get Stock Supply Detail',
        security: [['bearerAuth' => []]],
        tags: ['Stock Supply'],
        parameters: [
            new OA\Parameter(name: 'stockSupply', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stock supply details',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'suplier_id' => 1,
                            'invoice_number' => 'INV-SUP-123',
                            'date' => '2026-08-01',
                            'suplier' => ['id' => 1, 'name' => 'Suplier A'],
                            'items' => [
                                ['id' => 1, 'product_id' => 5, 'quantity_in' => 100, 'quantity_remaining' => 90, 'product' => ['id' => 5, 'name' => 'Kopi']]
                            ]
                        ]
                    ]
                )
            )
        ]
    )]
    public function show(StockSupply $stockSupply){
        $stockSupply->load(["stockSupplyItem", "suplier", 'stockSupplyItem.product']);
        return new DetailStockSupplyResource($stockSupply);
    }

    #[OA\Put(
        path: '/api/stock-supply/{stockSupply}',
        summary: 'Update Stock Supply',
        security: [['bearerAuth' => []]],
        tags: ['Stock Supply'],
        parameters: [
            new OA\Parameter(name: 'stockSupply', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'suplier_id', type: 'integer'),
                    new OA\Property(property: 'invoice_number', type: 'string'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'products',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', description: 'Include ID to update existing item', nullable: true),
                                new OA\Property(property: 'product_id', type: 'integer'),
                                new OA\Property(property: 'quantity_in', type: 'integer'),
                                new OA\Property(property: 'purchase_price', type: 'integer'),
                                new OA\Property(property: 'expired_date', type: 'string', format: 'date', nullable: true)
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stock supply updated',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'suplier_id' => 1,
                            'invoice_number' => 'INV-SUP-123-REVISI',
                            'items' => [
                                ['id' => 1, 'quantity_in' => 120, 'quantity_remaining' => 120]
                            ]
                        ]
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Cannot update used stock', content: new OA\JsonContent(example: ['message' => 'Updated quantity_in is too low, some stock is already consumed.']))
        ]
    )]
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

    #[OA\Delete(
        path: '/api/stock-supply/{stockSupply}',
        summary: 'Delete Stock Supply',
        security: [['bearerAuth' => []]],
        tags: ['Stock Supply'],
        parameters: [
            new OA\Parameter(name: 'stockSupply', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Stock supply deleted'),
            new OA\Response(response: 422, description: 'Cannot delete because items are consumed')
        ]
    )]
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
