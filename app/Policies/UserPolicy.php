<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.viewAny');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update') && ! $target->trashed() && self::mayManageTarget($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->can('users.delete') || $user->is($target) || $target->trashed() || ! self::mayManageTarget($user, $target)) {
            return false;
        }

        if (! $target->hasRole('super-admin')) {
            return true;
        }

        return $user->hasRole('super-admin') && self::hasAnotherActiveSuperAdmin($target);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('users.delete');
    }

    public function restore(User $user, User $target): bool
    {
        return $user->can('users.restore') && $target->trashed() && self::mayManageTarget($user, $target);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('users.restore');
    }

    public function manageRoles(User $user, User $target, array $roles = []): bool
    {
        if (! $user->can('users.manage_roles') || $target->trashed() || ! self::mayManageTarget($user, $target)) {
            return false;
        }

        $roles = array_values(array_unique(array_map('strval', $roles)));
        $assignsSuperAdmin = in_array('super-admin', $roles, true);
        $targetIsSuperAdmin = $target->hasRole('super-admin');

        if ($assignsSuperAdmin && ! $user->hasRole('super-admin')) {
            return false;
        }

        if ($targetIsSuperAdmin && ! $user->hasRole('super-admin')) {
            return false;
        }

        if ($user->is($target) && $targetIsSuperAdmin && ! $assignsSuperAdmin) {
            return false;
        }

        if ($targetIsSuperAdmin && ! $assignsSuperAdmin && ! self::hasAnotherActiveSuperAdmin($target)) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private static function mayManageTarget(User $actor, User $target): bool
    {
        return $actor->hasRole('super-admin') || ! $target->hasRole('super-admin');
    }

    private static function hasAnotherActiveSuperAdmin(User $target): bool
    {
        return User::role('super-admin')->whereKeyNot($target->getKey())->exists();
    }
}
