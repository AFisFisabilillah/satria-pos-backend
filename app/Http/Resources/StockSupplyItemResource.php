<?php

namespace App\Http\Resources;

use App\Models\StockSupplyItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockSupplyItem */
class StockSupplyItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity_in' => $this->quantity_in,
            'quantity_remaining' => $this->quantity_remaining,
            'purchase_price' => $this->purchase_price,
            'expired_at' => $this->expired_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'stock_supply_id' => $this->stock_supply_id,
            'product_id' => $this->product_id,

            'stockSupply' => new StockSupplyResource($this->whenLoaded('stockSupply')),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
