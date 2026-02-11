<?php

namespace App\Http\Requests;

use App\Enums\PostReactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactToPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'reaction_type' => [
                'required',
                'string',
                Rule::in(PostReactionType::values()),
            ],
        ];
    }
}
