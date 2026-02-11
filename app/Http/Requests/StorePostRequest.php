<?php

namespace App\Http\Requests;

use App\Enums\PostReactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\Post::class);
    }

    public function rules(): array
    {
        $maxBody = config('posts.post_body_max_length', 700);
        $maxImages = config('posts.post_images_max_count', 4);
        $maxSizeMb = config('posts.post_image_max_size_mb', 10);
        $mimes = config('posts.post_image_allowed_mimes', ['jpeg', 'png', 'gif', 'webp']);

        $rules = [
            'body' => ['required', 'string', 'max:' . $maxBody],
            'tagged_user_ids' => ['nullable', 'array'],
            'tagged_user_ids.*' => ['integer', 'exists:users,id'],
            'images' => ['nullable', 'array', 'max:' . $maxImages],
            'images.*' => ['image', 'mimes:' . implode(',', $mimes), 'max:' . ($maxSizeMb * 1024)],
        ];

        return $rules;
    }
}
