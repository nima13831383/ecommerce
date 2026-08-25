<?php

namespace App\Policies;

use App\Models\PostTag;
use App\Models\User;

class PostTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('post-tags.view');
    }

    public function view(User $user, PostTag $tag): bool
    {
        return $user->can('post-tags.view');
    }

    public function create(User $user): bool
    {
        return $user->can('post-tags.create');
    }

    public function update(User $user, PostTag $tag): bool
    {
        return $user->can('post-tags.update');
    }

    public function delete(User $user, PostTag $tag): bool
    {
        return $user->can('post-tags.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('post-tags.delete');
    }
}
