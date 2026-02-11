<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $reactionCounts = $this->reaction_counts ?? [];
        // Sort by count descending for display (e.g. love:5, like:3)
        arsort($reactionCounts);
        $reactionCounts = array_filter($reactionCounts, fn ($c) => $c > 0);
        if (empty($reactionCounts)) {
            $reactionCounts = (object) [];
        }

        $currentUserReaction = null;
        if ($user && $this->relationLoaded('reactions')) {
            $mine = $this->reactions->firstWhere('user_id', $user->id);
            if ($mine) {
                $currentUserReaction = $mine->reaction_type?->value ?? $mine->getRawOriginal('reaction_type');
            }
        }

        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status?->value ?? $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'user' => new PostAuthorResource($this->whenLoaded('user')),
            'tagged_users' => PostAuthorResource::collection($this->whenLoaded('taggedUsers')),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'path' => $m->path,
                'url' => $m->url,
                'order' => $m->order,
            ])),
            'comments_count' => $this->comments_count ?? 0,
            'reactions_count' => $this->reactions_count ?? 0,
            'reaction_counts' => $reactionCounts,
            'current_user_reaction' => $currentUserReaction,
            'can_edit' => $user ? $user->can('update', $this->resource) : false,
            'can_delete' => $user ? $user->can('delete', $this->resource) : false,
        ];
    }
}
