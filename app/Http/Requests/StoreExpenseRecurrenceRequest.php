<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:9999999.99',
            'category_id' => 'required|integer|exists:expense_categories,id',
            'frequency' => 'required|in:monthly,yearly',
            'due_day' => 'required|integer|min:1|max:31',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after:start_date',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Recurrence title is required',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Category does not exist',
            'frequency.required' => 'Frequency is required',
            'frequency.in' => 'Frequency must be either monthly or yearly',
            'due_day.required' => 'Due day is required',
            'due_day.min' => 'Due day must be between 1 and 31',
            'due_day.max' => 'Due day must be between 1 and 31',
            'start_date.required' => 'Start date is required',
            'start_date.date_format' => 'Start date must be in Y-m-d format',
            'end_date.date_format' => 'End date must be in Y-m-d format',
            'end_date.after' => 'End date must be after start date',
        ];
    }
}
