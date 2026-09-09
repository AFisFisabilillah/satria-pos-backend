<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Models\StockSupplyItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class StockAdjustmentController extends Controller
{
    #[OA\Get(
        path: '/api/stock-adjustments',
        summary: 'List Stock Adjustments',
        security: [['bearerAuth' => []]],
        tags: ['Stock Adjustment'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of stock adjustments',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            [
                                'id' => 1,
                                'stock_supply_item_id' => 10,
                                'user_id' => 1,
                                'quantity' => 5,
                                'type' => 'damaged',
                                'reason' => 'Kemasan rusak',
                                'created_at' => '2026-09-08T09:00:00Z',
                                'updated_at' => '2026-09-08T09:00:00Z',
                            ]
                        ],
                        'links' => ['first' => '...', 'last' => '...'],
                        'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1]
                    ]
                )
            )
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $search = request()->query('search');
        $type = request()->query('type');
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $size= request()->query("size",10);
        $query = StockAdjustment::query();
        $query
            ->when($type, function ($query) use($type) {
            $query->where('type', $type);})
            ->when($search, function ($query, $search) {
                $query->where('reason', 'LIKE', "%{$search}%")
                    ->whereHas('stockSupplyItem.product', function ($query) use($search) {
                        $query->where('name', "LIKE", "%{$search}%");
                    });
            })
            ->when($startDate, function ($query) use( $startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when($endDate, function ($query) use($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            });
        $adjustments = $query->with(['stockSupplyItem', 'user'])->latest()->paginate($size);

        return StockAdjustmentResource::collection($adjustments);
    }

    #[OA\Post(
        path: '/api/stock-adjustments',
        summary: 'Create Stock Adjustment',
        security: [['bearerAuth' => []]],
        tags: ['Stock Adjustment'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['stock_supply_item_id', 'quantity', 'type'],
                properties: [
                    new OA\Property(property: 'stock_supply_item_id', type: 'integer', example: 10),
                    new OA\Property(property: 'quantity', type: 'integer', example: 5),
                    new OA\Property(property: 'type', type: 'string', enum: ['damaged', 'expired', 'lost', 'other'], example: 'damaged'),
                    new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Kemasan rusak')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Stock adjustment created successfully',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'stock_supply_item_id' => 10,
                            'user_id' => 1,
                            'quantity' => 5,
                            'type' => 'damaged',
                            'reason' => 'Kemasan rusak',
                            'created_at' => '2026-09-08T09:00:00Z',
                            'updated_at' => '2026-09-08T09:00:00Z'
                        ]
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error or quantity exceeds remaining stock',
                content: new OA\JsonContent(
                    example: ['message' => 'Quantity adjustment exceeds remaining stock quantity.']
                )
            )
        ]
    )]
    public function store(StoreStockAdjustmentRequest $request): StockAdjustmentResource|JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $stockSupplyItem = StockSupplyItem::lockForUpdate()->findOrFail($request->stock_supply_item_id);

            if ($request->quantity > $stockSupplyItem->quantity_remaining) {
                return response()->json([
                    'message' => 'Quantity adjustment exceeds remaining stock quantity.',
                ], 422);
            }

            $stockSupplyItem->decrement('quantity_remaining', $request->quantity);

            $adjustment = StockAdjustment::create([
                'stock_supply_item_id' => $request->stock_supply_item_id,
                'user_id' => Auth::id(),
                'quantity' => $request->quantity,
                'type' => $request->type,
                'reason' => $request->reason,
            ]);

            return new StockAdjustmentResource($adjustment->load(['stockSupplyItem', 'user']));
        });
    }

    #[OA\Get(
        path: '/api/stock-adjustments/{stockAdjustment}',
        summary: 'Get Stock Adjustment Detail',
        security: [['bearerAuth' => []]],
        tags: ['Stock Adjustment'],
        parameters: [
            new OA\Parameter(name: 'stockAdjustment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stock adjustment detail',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'stock_supply_item_id' => 10,
                            'user_id' => 1,
                            'quantity' => 5,
                            'type' => 'damaged',
                            'reason' => 'Kemasan rusak',
                            'created_at' => '2026-09-08T09:00:00Z',
                            'updated_at' => '2026-09-08T09:00:00Z'
                        ]
                    ]
                )
            )
        ]
    )]
    public function show(StockAdjustment $stockAdjustment): StockAdjustmentResource
    {
        return new StockAdjustmentResource($stockAdjustment->load(['stockSupplyItem', 'user']));
    }
}


