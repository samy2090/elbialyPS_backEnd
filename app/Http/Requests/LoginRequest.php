<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string', // Can be email, phone, or username
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Please provide your email, phone number, or username.',
        ];
    }
}
