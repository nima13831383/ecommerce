<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('products.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('products.restore');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('products.restore');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->can('products.force-delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('products.force-delete');
    }
}
