<?php

namespace App\Filament\Resources\CustomerNotifications\Support;

use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationStatus;
use App\Enums\CustomerNotificationType;

class CustomerNotificationPresentation
{
    public static function type(CustomerNotificationType|string $type): string
    {
        $type = $type instanceof CustomerNotificationType ? $type : CustomerNotificationType::from($type);

        return [
            CustomerNotificationType::OrderPlaced->value => 'ثبت سفارش',
            CustomerNotificationType::PaymentSucceeded->value => 'پرداخت موفق',
            CustomerNotificationType::OrderCancelled->value => 'لغو سفارش',
            CustomerNotificationType::ShipmentReady->value => 'آماده‌سازی ارسال',
            CustomerNotificationType::ShipmentShipped->value => 'ارسال مرسوله',
            CustomerNotificationType::ShipmentDelivered->value => 'تحویل مرسوله',
        ][$type->value];
    }

    public static function status(CustomerNotificationStatus|string $status): string
    {
        $status = $status instanceof CustomerNotificationStatus ? $status : CustomerNotificationStatus::from($status);

        return [
            'pending' => 'در انتظار', 'queued' => 'صف ارسال', 'sent' => 'ارسال‌شده', 'failed' => 'ناموفق',
        ][$status->value];
    }

    public static function channel(CustomerNotificationChannel|string $channel): string
    {
        $channel = $channel instanceof CustomerNotificationChannel ? $channel : CustomerNotificationChannel::from($channel);

        return ['development' => 'محیط توسعه', 'sms' => 'پیامک', 'email' => 'ایمیل'][$channel->value];
    }

    public static function statusColor(CustomerNotificationStatus|string $status): string
    {
        return match (($status instanceof CustomerNotificationStatus ? $status : CustomerNotificationStatus::from($status))) {
            CustomerNotificationStatus::Sent => 'success',
            CustomerNotificationStatus::Failed => 'danger',
            CustomerNotificationStatus::Queued => 'info',
            CustomerNotificationStatus::Pending => 'gray',
        };
    }
}
