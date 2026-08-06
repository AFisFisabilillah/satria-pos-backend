<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suplier_id' => $this->suplier_id,
            'return_number' => $this->return_number,
            'date' => $this->date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'suplier' => new SuplierResource($this->whenLoaded('suplier')),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'stock_supply_item_id' => $item->stock_supply_item_id,
                        'quantity' => $item->quantity,
                        'reason' => $item->reason,
                        'batch_info' => $item->stockSupplyItem ? [
                            'product_name' => $item->stockSupplyItem->product->name ?? null,
                            'purchase_price' => $item->stockSupplyItem->purchase_price,
                            'quantity_in' => $item->stockSupplyItem->quantity_in,
                            'created_at' => $item->stockSupplyItem->created_at,
                        ] : null
                    ];
                });
            }),
        ];
    }
}