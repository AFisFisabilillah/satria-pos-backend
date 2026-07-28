<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockSupplyRequest extends FormRequest
{
    public function rules(): array
    {
        $stockSupplyId = $this->route('stockSupply') ? $this->route('stockSupply')->id : null;

        return [
            'suplier_id' => ['required', 'exists:supliers,id'],
            'invoice_number' => ['required', 'unique:stock_supplies,invoice_number,' . $stockSupplyId],
            'notes' => ['required', 'string'],
            'products'=> ['required', 'array'],
            'products.*.id' => ['nullable', 'exists:stock_supply_items,id'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.quantity_in' => ['required', 'integer', 'min:1'],
            'products.*.purchase_price' => ['required', 'integer', 'min:1'],
            'products.*.expired_date' => ['required', 'date'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
