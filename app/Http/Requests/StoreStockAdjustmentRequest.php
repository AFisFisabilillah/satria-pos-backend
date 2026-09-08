<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_supply_item_id' => ['required', 'exists:stock_supply_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:damaged,expired,lost,other'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

