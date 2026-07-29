<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */

class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id"=>$this->id,
            "name" => $this->name,
            "image" => $this->image,
        ];
    }
}
