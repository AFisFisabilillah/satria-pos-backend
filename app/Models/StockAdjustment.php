<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StockAdjustment',
    title: 'Stock Adjustment',
    description: 'Stock adjustment model schema',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'stock_supply_item_id', type: 'integer', example: 10),
        new OA\Property(property: 'user_id', type: 'integer', example: 1),
        new OA\Property(property: 'quantity', type: 'integer', example: 5),
        new OA\Property(property: 'type', type: 'string', enum: ['damaged', 'expired', 'lost', 'other'], example: 'damaged'),
        new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Kemasan rusak'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-09-08T09:00:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-09-08T09:00:00Z')
    ]
)]
#[Fillable('stock_supply_item_id', 'user_id', 'quantity', 'type', 'reason')]
class StockAdjustment extends Model
{
    use HasFactory;

    public function stockSupplyItem(): BelongsTo
    {
        return $this->belongsTo(StockSupplyItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


