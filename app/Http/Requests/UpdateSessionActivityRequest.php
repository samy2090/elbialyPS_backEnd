<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_type' => 'nullable|in:device_use,pause',
            'device_id' => 'nullable|integer|exists:devices,id',
            'mode' => 'nullable|in:single,multi',
            'ended_at' => 'nullable|date_format:Y-m-d H:i:s',
            'duration_hours' => 'nullable|numeric|min:0',
            'price_per_hour' => 'nullable|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
        ];
    }
}
