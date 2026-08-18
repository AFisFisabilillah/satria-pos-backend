<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'city', 'province', 'address', 'postal_code'])]
class Suplier extends Model
{
    use HasFactory;

    public function stockSupplies(): HasMany
    {
        return $this->hasMany(StockSupply::class);
    }
}
