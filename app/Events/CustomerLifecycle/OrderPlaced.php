<?php

namespace App\Events\CustomerLifecycle;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class OrderPlaced implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Order $order) {}
}
