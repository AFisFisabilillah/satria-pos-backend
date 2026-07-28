<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'active' => $this->active,
            'sale_price' => $this->sale_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'unit_id' => $this->unit,
            'quantity' => (int) $this->stock_supply_items_sum_quantity_remaining,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}
