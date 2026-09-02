<?php

namespace App\Policies;

use App\Models\CustomerNotification;
use App\Models\User;

class CustomerNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notifications.viewAny');
    }

    public function view(User $user, CustomerNotification $notification): bool
    {
        return $user->can('notifications.view');
    }

    public function retry(User $user, CustomerNotification $notification): bool
    {
        return $user->can('notifications.retry');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CustomerNotification $notification): bool
    {
        return false;
    }

    public function delete(User $user, CustomerNotification $notification): bool
    {
        return false;
    }
}
