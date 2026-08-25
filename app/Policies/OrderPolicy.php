<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.viewAny');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view');
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->can('orders.update_status');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
