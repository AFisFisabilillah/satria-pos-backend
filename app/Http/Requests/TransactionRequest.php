<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'total_price' => ['required', 'integer'],
            'paid_amount' => ['required', 'integer'],
            'change_amount' => ['required', 'integer'],
            'payment_method' => ['required'],
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
