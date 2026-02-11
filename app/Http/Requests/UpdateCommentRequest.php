<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', $this->route('comment'));
    }

    public function rules(): array
    {
        $maxBody = config('posts.comment_body_max_length', 500);

        return [
            'body' => ['sometimes', 'string', 'max:' . $maxBody],
        ];
    }
}
