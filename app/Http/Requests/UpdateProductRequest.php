<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'sku' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($this->route('id')),
            ],
            'note' => 'nullable|string',
            'category' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0',
            'cost' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'stock' => 'sometimes|integer|min:0',
        ];
    }
}