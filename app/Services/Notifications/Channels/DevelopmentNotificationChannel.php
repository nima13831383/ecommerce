<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\Models\CustomerNotification;
use App\Services\Notifications\Data\NotificationDeliveryResult;
use Illuminate\Support\Facades\Log;

class DevelopmentNotificationChannel implements NotificationChannelInterface
{
    public function send(CustomerNotification $notification): NotificationDeliveryResult
    {
        Log::info('customer_notification_simulated', [
            'notification_id' => $notification->id,
            'type' => $notification->type->value,
            'channel' => $notification->channel->value,
            'order_id' => $notification->order_id,
        ]);

        return NotificationDeliveryResult::simulated();
    }
}
