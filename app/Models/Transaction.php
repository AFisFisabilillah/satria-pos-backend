<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable("invoice_number", "total_price", "paid_amount", "change_amount", "payment_method", "member_id")]
class Transaction extends Model
{
    use HasFactory;

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'transaction_voucher')
                    ->withPivot('discount_applied')
                    ->withTimestamps();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
