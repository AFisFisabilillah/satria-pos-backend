<?php

namespace App\Http\Resources;

use App\Models\TransactionItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use App\Http\Resources\ProductSummaryResource;

/** @mixin TransactionItem */
class TransactionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'selling_price' => $this->selling_price,
            'subtotal' => $this->subtotal,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'transaction_id' => $this->transaction_id,
            'product' => new ProductSummaryResource($this->whenLoaded('product')),
        ];
    }
}
