<?php

namespace App\Http\Requests;

use App\Enums\FinancialPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(FinancialPeriod::values())],
            'from'   => ['nullable', 'date', 'date_format:Y-m-d'],
            'to'     => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
            'mode'   => ['nullable', 'string', Rule::in(['actual', 'smoothed'])],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => 'Period must be one of: daily, weekly, monthly, yearly.',
            'to.after_or_equal' => 'The "to" date must be the same as or after the "from" date.',
            'mode.in' => 'Mode must be either "actual" or "smoothed".',
        ];
    }

    public function period(): FinancialPeriod
    {
        return FinancialPeriod::from($this->input('period', FinancialPeriod::Daily->value));
    }

    public function mode(): string
    {
        return $this->input('mode', 'actual');
    }
}
