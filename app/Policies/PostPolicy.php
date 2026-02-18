<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Authorization policy for Post model.
 *
 * Controls who can view, create, update, delete, and approve posts.
 */
class PostPolicy
{
    /**
     * Anyone can view a published post (public feed). Policy used for update/delete.
     *
     * @param  User|null  $user  The authenticated user or null (guest).
     * @param  Post  $post  The post to view.
     * @return bool
     */
    public function view(?User $user, Post $post): bool
    {
        return true;
    }

    /**
     * Only authenticated users can create posts.
     *
     * @param  User  $user  The authenticated user.
     * @return bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * User can update own post; admin can update any.
     *
     * @param  User  $user  The authenticated user.
     * @param  Post  $post  The post to update.
     * @return bool
     */
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }

    /**
     * User can delete own post; admin can delete any.
     *
     * @param  User  $user  The authenticated user.
     * @param  Post  $post  The post to delete.
     * @return bool
     */
    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }

    /**
     * Only admin can approve pending posts.
     *
     * @param  User  $user  The authenticated user.
     * @return bool
     */
    public function approve(User $user): bool
    {
        return $user->isAdmin();
    }
}
