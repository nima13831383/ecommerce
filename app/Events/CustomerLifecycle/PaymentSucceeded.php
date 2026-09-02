<?php

namespace App\Events\CustomerLifecycle;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class PaymentSucceeded implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Payment $payment) {}
}
