<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable("name", "code", "type", "value", "min_purchase", "quota", "expired_at", "active")]
class Voucher extends Model
{
    use HasFactory;

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'transaction_voucher')
            ->withPivot('discount_applied')
            ->withTimestamps();
    }
}
