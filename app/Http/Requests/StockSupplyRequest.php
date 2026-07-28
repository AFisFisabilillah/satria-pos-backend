<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockSupplyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'suplier_id' => ['required', 'exists:supliers,id'],
            'invoice_number' => ['required'],
            'notes' => ['required', 'string'],
            'products'=> ['required', 'array'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.quantity_in' => ['required', 'integer', 'min:1'],
            'products.*.purchase_price' => ['required', 'integer', 'min:1'],
            'products.*.expired_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
