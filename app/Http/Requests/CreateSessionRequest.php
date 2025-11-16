<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'created_by' => 'required|integer|exists:users,id',
            'customer_id' => 'nullable|integer|exists:users,id',
            'started_at' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'nullable|in:active,paused,ended',
            'total_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ];
    }
}
