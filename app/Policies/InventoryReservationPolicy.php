<?php

namespace App\Policies;

use App\Models\InventoryReservation;
use App\Models\User;

class InventoryReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-reservations.viewAny');
    }

    public function view(User $user, InventoryReservation $reservation): bool
    {
        return $user->can('inventory-reservations.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InventoryReservation $reservation): bool
    {
        return false;
    }

    public function delete(User $user, InventoryReservation $reservation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
