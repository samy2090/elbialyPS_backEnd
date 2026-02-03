<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:9999999.99',
            'expense_date' => 'required|date|date_format:Y-m-d',
            'category_id' => 'required|integer|exists:expense_categories,id',
            'recurring_id' => 'nullable|integer|exists:expense_recurrences,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|required_if:product_id,*',
            'status' => 'required|in:paid,unpaid',
            'paid_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Expense title is required',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'expense_date.required' => 'Expense date is required',
            'expense_date.date_format' => 'Expense date must be in Y-m-d format',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Category does not exist',
            'status.required' => 'Status is required',
            'status.in' => 'Status must be either paid or unpaid',
        ];
    }
}
