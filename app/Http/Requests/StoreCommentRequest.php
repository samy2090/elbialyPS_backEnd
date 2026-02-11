<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\Comment::class);
    }

    public function rules(): array
    {
        $maxBody = config('posts.comment_body_max_length', 500);

        return [
            'body' => ['required', 'string', 'max:' . $maxBody],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:comments,id',
                Rule::exists('comments', 'id')->whereNull('parent_id'), // only top-level can have replies (max depth 2)
            ],
        ];
    }
}
