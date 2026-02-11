<?php

namespace App\Observers;

use App\Models\PostReaction;

class PostReactionObserver
{
    public function created(PostReaction $reaction): void
    {
        $this->refreshPostReactionCounts($reaction->post_id);
    }

    public function updated(PostReaction $reaction): void
    {
        $this->refreshPostReactionCounts($reaction->post_id);
    }

    public function deleted(PostReaction $reaction): void
    {
        $this->refreshPostReactionCounts($reaction->post_id);
    }

    private function refreshPostReactionCounts(int $postId): void
    {
        $post = \App\Models\Post::find($postId);
        if (!$post) {
            return;
        }

        $counts = \App\Models\PostReaction::where('post_id', $postId)
            ->get()
            ->groupBy(fn ($r) => $r->reaction_type?->value ?? $r->getRawOriginal('reaction_type'))
            ->map->count()
            ->toArray();

        $total = array_sum($counts);
        $post->update([
            'reactions_count' => $total,
            'reaction_counts' => $counts,
        ]);
    }
}
