<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'member_id' => 'nullable|exists:members,id',
            'paid_amount' => ['required', 'integer', 'min:0'],
            'payment_method' => ['required', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'vouchers' => ['nullable', 'array'],
            'vouchers.*' => ['integer', 'exists:vouchers,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
