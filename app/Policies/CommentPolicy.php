<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Anyone can view comments (public).
     */
    public function view(?User $user, Comment $comment): bool
    {
        return true;
    }

    /**
     * Only authenticated users can create comments.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * User can update own comment; admin can update any.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isAdmin();
    }

    /**
     * User can delete own comment; admin can delete any.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isAdmin();
    }
}
