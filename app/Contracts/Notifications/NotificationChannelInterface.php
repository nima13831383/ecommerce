<?php

namespace App\Contracts\Notifications;

use App\Models\CustomerNotification;
use App\Services\Notifications\Data\NotificationDeliveryResult;

interface NotificationChannelInterface
{
    public function send(CustomerNotification $notification): NotificationDeliveryResult;
}
