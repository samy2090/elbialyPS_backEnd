<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', $this->route('post'));
    }

    public function rules(): array
    {
        $maxBody = config('posts.post_body_max_length', 700);
        $maxImages = config('posts.post_images_max_count', 4);
        $maxSizeMb = config('posts.post_image_max_size_mb', 10);
        $mimes = config('posts.post_image_allowed_mimes', ['jpeg', 'png', 'gif', 'webp']);

        return [
            'body' => ['sometimes', 'string', 'max:' . $maxBody],
            'tagged_user_ids' => ['sometimes', 'nullable', 'array'],
            'tagged_user_ids.*' => ['integer', 'exists:users,id'],
            'images' => ['sometimes', 'nullable', 'array', 'max:' . $maxImages],
            'images.*' => ['image', 'mimes:' . implode(',', $mimes), 'max:' . ($maxSizeMb * 1024)],
        ];
    }
}
