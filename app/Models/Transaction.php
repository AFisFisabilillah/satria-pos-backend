<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable("invoice_number", "total_price", "paid_amount", "change_amount", "payment_method", 'quantity', 'product_id')]
class Transaction extends Model
{
    use HasFactory;

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}
