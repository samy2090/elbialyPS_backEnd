<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:products,sku',
            'note' => 'nullable|string',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'stock' => 'required|integer|min:0',
        ];
    }
}