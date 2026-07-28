<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable("name", "code", "slug", "description", "image", "active", "unit_id", "sale_price")]
class Product extends Model{
    use HasFactory;

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function stockSupplyItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockSupplyItem::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
