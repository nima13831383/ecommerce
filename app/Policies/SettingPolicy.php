<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use App\Settings\SettingRegistry;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function view(User $user, Setting $setting): bool
    {
        return $user->can('settings.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->can('settings.update')
            && SettingRegistry::has($setting->key)
            && SettingRegistry::get($setting->key)->group === $setting->group;
    }

    public function delete(User $user, Setting $setting): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
