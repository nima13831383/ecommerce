<?php

namespace App\Policies;

use App\Models\PostCategory;
use App\Models\User;

class PostCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('post-categories.view');
    }

    public function view(User $user, PostCategory $category): bool
    {
        return $user->can('post-categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('post-categories.create');
    }

    public function update(User $user, PostCategory $category): bool
    {
        return $user->can('post-categories.update');
    }

    public function delete(User $user, PostCategory $category): bool
    {
        return $user->can('post-categories.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('post-categories.delete');
    }
}
