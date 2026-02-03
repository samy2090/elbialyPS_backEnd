<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0|max:9999999.99',
            'expense_date' => 'sometimes|required|date|date_format:Y-m-d',
            'category_id' => 'sometimes|required|integer|exists:expense_categories,id',
            'recurring_id' => 'nullable|integer|exists:expense_recurrences,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'status' => 'sometimes|required|in:paid,unpaid',
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
