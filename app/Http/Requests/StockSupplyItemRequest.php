<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockSupplyItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'stock_supply_id' => ['required', 'exists:stock_supplies,id'],
            'product_id' => ['required', 'exists:products'],
            'quantity_in' => ['required', 'integer'],
            'purchase_price' => ['required', 'decimal:2'],
            'expired_at' => ['required', 'date'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
