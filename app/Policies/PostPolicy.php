<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Anyone can view a published post (public feed). Policy used for update/delete.
     */
    public function view(?User $user, Post $post): bool
    {
        return true;
    }

    /**
     * Only authenticated users can create posts.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * User can update own post; admin can update any.
     */
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }

    /**
     * User can delete own post; admin can delete any.
     */
    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }

    /**
     * Only admin can approve pending posts.
     */
    public function approve(User $user): bool
    {
        return $user->isAdmin();
    }
}
