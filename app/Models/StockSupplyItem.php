<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable("product_id", "created_at", "stock_supply_id", "quantity_in", "quantity_remaining", "purchase_price", "expired_at")]

class StockSupplyItem extends Model
{
    use HasFactory;

    public function stockSupply(): BelongsTo
    {
        return $this->belongsTo(StockSupply::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'expired_at' => 'date',
        ];
    }
}
