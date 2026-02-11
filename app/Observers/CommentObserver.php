<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Post;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        Post::where('id', $comment->post_id)->increment('comments_count');
        if ($comment->parent_id) {
            Comment::where('id', $comment->parent_id)->increment('replies_count');
        }
    }

    public function deleted(Comment $comment): void
    {
        Post::where('id', $comment->post_id)->decrement('comments_count');
        if ($comment->parent_id) {
            Comment::where('id', $comment->parent_id)->decrement('replies_count');
        }
    }

    public function restored(Comment $comment): void
    {
        Post::where('id', $comment->post_id)->increment('comments_count');
        if ($comment->parent_id) {
            Comment::where('id', $comment->parent_id)->increment('replies_count');
        }
    }
}
