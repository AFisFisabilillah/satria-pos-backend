<?php

namespace App\Http\Resources;

use App\Models\Suplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Suplier */
class DetailSupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'address' => $this->address,
            'stock_supply'=>StockSupplyResource::collection($this->stockSupplies)
        ];
    }
}
