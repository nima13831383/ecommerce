<?php

namespace App\Policies;

use App\Models\TaxClass;
use App\Models\User;

class TaxClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-classes.view');
    }

    public function view(User $user, TaxClass $taxClass): bool
    {
        return $user->can('tax-classes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tax-classes.create');
    }

    public function update(User $user, TaxClass $taxClass): bool
    {
        return $user->can('tax-classes.update');
    }

    public function delete(User $user, TaxClass $taxClass): bool
    {
        return $user->can('tax-classes.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('tax-classes.delete');
    }
}
