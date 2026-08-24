<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('brands.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->can('brands.view');
    }

    public function create(User $user): bool
    {
        return $user->can('brands.create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can('brands.update');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->can('brands.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('brands.delete');
    }

    public function restore(User $user, Brand $brand): bool
    {
        return $user->can('brands.restore');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('brands.restore');
    }

    public function forceDelete(User $user, Brand $brand): bool
    {
        return $user->can('brands.force-delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('brands.force-delete');
    }
}
