<?php

namespace App\Http\Resources;

use App\Models\StockSupply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockSupply */
class StockSupplyResource extends JsonResource
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
            'suplier' => $this->suplier->name,
            'total_items_quantity' => $this->whenLoaded('stockSupplyItem', fn () => $this->stockSupplyItem->sum('quantity_in')),
            'total_item_types' => $this->whenLoaded('stockSupplyItem', fn () => $this->stockSupplyItem->count()),
        ];
    }
}
