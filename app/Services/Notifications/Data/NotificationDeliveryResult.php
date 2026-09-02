<?php

namespace App\Services\Notifications\Data;

final readonly class NotificationDeliveryResult
{
    private function __construct(public bool $sent, public bool $simulated) {}

    public static function simulated(): self
    {
        return new self(false, true);
    }

    public static function sent(): self
    {
        return new self(true, false);
    }
}
