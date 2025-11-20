<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|integer|exists:users,id',
            'started_at' => 'nullable|date_format:Y-m-d H:i:s',
            'ended_at' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'nullable|in:active,paused,ended',
            'type' => 'nullable|in:playing,chillout',
            'total_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'confirm_end_activities' => 'nullable|boolean',
        ];
    }
}
