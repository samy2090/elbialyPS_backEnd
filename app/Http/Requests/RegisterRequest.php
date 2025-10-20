<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^01[0-9]{9}$/|unique:users,phone',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be 11 digits and start with 01.',
            'phone.unique' => 'This phone number is already registered.',
            'email.unique' => 'This email is already registered.',
        ];
    }
}
