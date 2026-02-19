<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Authorization policy for Comment model.
 *
 * Controls who can view, create, update, and delete comments.
 */
class CommentPolicy
{
    /**
     * Anyone can view comments (public).
     *
     * @param  User|null  $user  The authenticated user or null (guest).
     * @param  Comment  $comment  The comment to view.
     * @return bool
     */
    public function view(?User $user, Comment $comment): bool
    {
        return true;
    }

    /**
     * Only authenticated users can create comments.
     *
     * @param  User  $user  The authenticated user.
     * @return bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * User can update own comment; admin can update any.
     *
     * @param  User  $user  The authenticated user.
     * @param  Comment  $comment  The comment to update.
     * @return bool
     */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isAdmin();
    }

    /**
     * User can delete own comment; admin can delete any.
     *
     * @param  User  $user  The authenticated user.
     * @param  Comment  $comment  The comment to delete.
     * @return bool
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isAdmin();
    }
}
