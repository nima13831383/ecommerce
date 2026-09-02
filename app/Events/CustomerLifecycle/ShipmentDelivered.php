<?php

namespace App\Events\CustomerLifecycle;

use App\Models\Shipment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class ShipmentDelivered implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Shipment $shipment) {}
}
