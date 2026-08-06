<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable('supplier_return_id', 'stock_supply_item_id', 'quantity', 'reason')]
class SupplierReturnItem extends Model
{
    use HasFactory;

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function stockSupplyItem(): BelongsTo
    {
        return $this->belongsTo(StockSupplyItem::class);
    }
}