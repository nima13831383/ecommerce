<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipments.viewAny');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('shipments.create');
    }

    public function updateTracking(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.update_tracking');
    }

    public function markReady(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.mark_ready');
    }

    public function markShipped(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.mark_shipped');
    }

    public function markDelivered(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.mark_delivered');
    }

    public function cancel(User $user, Shipment $shipment): bool
    {
        return $user->can('shipments.cancel');
    }
}
