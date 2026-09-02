<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\Services\Notifications\Channels\DevelopmentNotificationChannel;

class NotificationChannelResolver
{
    public function resolve(): NotificationChannelInterface
    {
        return app(DevelopmentNotificationChannel::class);
    }
}
