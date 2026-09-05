<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */

class DetailTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'total_price' => $this->total_price,
            'paid_amount' => $this->paid_amount,
            'change_amount' => $this->change_amount,
            'payment_method' => $this->payment_method,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items'=>TransactionItemResource::collection($this->items),
            'member'=>$this->member ? new MemberResource($this->member) : null,
            'vouchers'=>$this->vouchers ?  VoucherResource::collection($this->vouchers) : null,
        ];
    }

}
