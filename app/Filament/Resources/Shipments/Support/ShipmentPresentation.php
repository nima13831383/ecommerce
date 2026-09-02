<?php

namespace App\Filament\Resources\Shipments\Support;

use App\Enums\ShipmentStatus;

class ShipmentPresentation
{
    public static function status(ShipmentStatus|string|null $status): string
    {
        $value = $status instanceof ShipmentStatus ? $status->value : $status;

        return match ($value) {
            ShipmentStatus::Pending->value => 'در انتظار پردازش',
            ShipmentStatus::Ready->value => 'آماده ارسال',
            ShipmentStatus::Shipped->value => 'ارسال شده',
            ShipmentStatus::Delivered->value => 'تحویل شده',
            ShipmentStatus::Cancelled->value => 'لغو شده',
            default => 'نامشخص',
        };
    }

    public static function color(ShipmentStatus|string|null $status): string
    {
        $value = $status instanceof ShipmentStatus ? $status->value : $status;

        return match ($value) {
            ShipmentStatus::Delivered->value => 'success',
            ShipmentStatus::Cancelled->value => 'danger',
            ShipmentStatus::Shipped->value, ShipmentStatus::Ready->value => 'info',
            default => 'warning',
        };
    }

    /** @param array<int, ShipmentStatus> $cases */
    public static function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn (ShipmentStatus $case): array => [$case->value => self::status($case)])->all();
    }
}
