<?php

namespace App\Http\Resources;

use App\Models\StockSupply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockSupply */
class DetailStockSupplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'date' => $this->date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'suplier' => new SuplierResource($this->suplier),
            "items" => new StockSupplyItemResource($this->stockSupplyItem)
        ];
    }
}
