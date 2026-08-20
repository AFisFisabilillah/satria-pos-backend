<?php

namespace App\Http\Requests;

use App\VoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class VoucherRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required','unique:vouchers,code', 'regex:/^\S+$/'],
            'type' => ['required',new Enum(VoucherType::class)],
            'value' => [
                'required', 'numeric',
                Rule::when(
                    $this->input('type') === VoucherType::PERCENT->value,
                    ["max:100"]
                )
                ],
            'min_purchase' => ['nullable', 'integer'],
            'quota' => ['required', "integer", "min:1"],
            'expired_at' => ["nullable", "date","after:today"],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
