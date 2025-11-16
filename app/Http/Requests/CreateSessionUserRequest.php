<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSessionUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|integer|exists:sessions,id',
            'user_id' => 'required|integer|exists:users,id',
            'is_payer' => 'nullable|boolean',
        ];
    }
}
