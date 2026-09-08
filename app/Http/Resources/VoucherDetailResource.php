<?php

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Voucher */

class VoucherDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'min_purchase' => $this->min_purchase,
            'quota' => $this->quota,
            'expired_at' => $this->expired_at,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'transactions' =>TransactionResource::collection($this->transactions)
        ];
    }
}
