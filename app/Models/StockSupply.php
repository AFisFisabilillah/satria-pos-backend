<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable("suplier_id", "invoice_number", "date", "notes")]
class StockSupply extends Model
{
    use HasFactory;

    public function suplier(): BelongsTo
    {
        return $this->belongsTo(Suplier::class);
    }

    public function stockSupplyItem(): HasMany
    {
        return $this->hasMany(StockSupplyItem::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
