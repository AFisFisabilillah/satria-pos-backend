<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable("transaction_item_id", "stock_supply_item_id", "quantity_deducted", "purchase_price")]
class TransactionItemCogs extends Model
{
    use HasFactory;

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }

    public function stockSupplyItem(): BelongsTo
    {
        return $this->belongsTo(StockSupplyItem::class);
    }
}
