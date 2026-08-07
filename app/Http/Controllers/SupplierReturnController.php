<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierReturnRequest;
use App\Http\Resources\SupplierReturnResource;
use App\Models\StockSupplyItem;
use App\Models\SupplierReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class SupplierReturnController extends Controller
{
    #[OA\Get(
        path: '/api/supplier-returns',
        summary: 'List Supplier Returns',
        security: [['bearerAuth' => []]],
        tags: ['Supplier Return'],
        parameters: [
            new OA\Parameter(name: 'size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of returns')
        ]
    )]
    public function index()
    {
        $size = request()->query('size', 10);
        $returns = SupplierReturn::with(['suplier', 'items.stockSupplyItem.product'])
            ->latest()
            ->paginate($size);

        return SupplierReturnResource::collection($returns);
    }

    #[OA\Post(
        path: '/api/supplier-returns',
        summary: 'Create Supplier Return',
        description: 'Creates a return to supplier and deducts from stock supply items.',
        security: [['bearerAuth' => []]],
        tags: ['Supplier Return'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['suplier_id', 'date', 'items'],
                properties: [
                    new OA\Property(property: 'suplier_id', type: 'integer', example: 1),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-08-06'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Barang reject'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'stock_supply_item_id', type: 'integer', example: 5),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Pecah')
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Return created'),
            new OA\Response(
                response: 422,
                description: 'Validation error or stock insufficient',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Gagal memproses retur ke suplier.',
                        'error' => 'Stok tidak mencukupi untuk diretur pada batch ID 5. Sisa: 1, Diminta: 2'
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: '/api/supplier-returns/{supplierReturn}',
        summary: 'Get Supplier Return Detail',
        security: [['bearerAuth' => []]],
        tags: ['Supplier Return'],
        parameters: [
            new OA\Parameter(name: 'supplierReturn', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Supplier return details')
        ]
    )]
    public function show(SupplierReturn $supplierReturn)
    {
        return new SupplierReturnResource($supplierReturn->load(['suplier', 'items.stockSupplyItem.product']));
    }
}