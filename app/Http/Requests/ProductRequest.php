<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'code' => ['required', 'unique:products,code'],
            'description' => ['nullable'],
            'image' => ['required', 'file', 'image', 'mimes:jpg,png,jpeg', 'max:2048'],
            'active' => ['boolean'],
            'unit_id' => ['required', 'exists:units,id'],
            'sale_price' => ['required', 'integer','min:0'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
