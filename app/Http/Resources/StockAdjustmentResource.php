<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_supply_item_id' => $this->stock_supply_item_id,
            'user_id' => $this->user_id,
            'quantity' => $this->quantity,
            'type' => $this->type,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'stock_supply_item' => new StockSupplyItemResource($this->whenLoaded('stockSupplyItem')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

