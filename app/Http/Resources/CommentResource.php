<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'user' => new PostAuthorResource($this->whenLoaded('user')),
            'post' => $this->when($this->relationLoaded('post'), fn () => $this->post ? [
                'id' => $this->post->id,
                'body' => \Illuminate\Support\Str::limit($this->post->body, 80),
                'user_id' => $this->post->user_id,
                'status' => $this->post->status?->value ?? $this->post->status,
            ] : null),
            'replies_count' => $this->replies_count ?? 0,
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'can_edit' => $user ? $user->can('update', $this->resource) : false,
            'can_delete' => $user ? $user->can('delete', $this->resource) : false,
        ];
    }
}
