<?php

namespace App\Filament\Resources\Payments\Support;

use App\Enums\InventoryReservationStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Support\OrderPresentation;
use App\Models\Payment;
use App\Support\PersianNumber;
use App\Support\SafeMetadata;

class PaymentPresentation
{
    public static function money(mixed $amount, ?string $currency = 'IRR'): string
    {
        if ($currency === 'IRR') {
            return OrderPresentation::money($amount);
        }

        return PersianNumber::integer($amount).' '.($currency ?: '—');
    }

    public static function status(mixed $status): string
    {
        return match (self::value($status)) {
            PaymentStatus::Pending->value => 'در انتظار',
            PaymentStatus::Processing->value => 'در حال پردازش',
            PaymentStatus::Paid->value => 'پرداخت موفق',
            PaymentStatus::Failed->value => 'ناموفق',
            PaymentStatus::Cancelled->value => 'لغو شده',
            PaymentStatus::Expired->value => 'منقضی شده',
            PaymentStatus::Refunded->value => 'مرجوع شده',
            PaymentStatus::PartiallyRefunded->value => 'بخشی مرجوع شده',
            default => (string) self::value($status),
        };
    }

    public static function statusColor(mixed $status): string
    {
        return match (self::value($status)) {
            PaymentStatus::Paid->value => 'success',
            PaymentStatus::Failed->value, PaymentStatus::Cancelled->value, PaymentStatus::Expired->value => 'danger',
            PaymentStatus::Refunded->value, PaymentStatus::PartiallyRefunded->value => 'info',
            default => 'warning',
        };
    }

    public static function transactionType(mixed $type): string
    {
        return match ((string) $type) {
            'request' => 'شروع پرداخت',
            'callback' => 'بازگشت از درگاه',
            'verify' => 'تأیید پرداخت',
            'inquiry' => 'استعلام',
            'refund' => 'مرجوعی',
            'reverse' => 'برگشت تراکنش',
            default => (string) $type,
        };
    }

    public static function transactionStatus(mixed $status): string
    {
        return match ((string) $status) {
            'success' => 'موفق',
            'failed' => 'ناموفق',
            'pending' => 'در انتظار',
            default => (string) $status,
        };
    }

    public static function transactionStatusColor(mixed $status): string
    {
        return match ((string) $status) {
            'success' => 'success',
            'failed' => 'danger',
            default => 'warning',
        };
    }

    /** @return array<int, string> */
    public static function warnings(Payment $payment): array
    {
        $warnings = [];

        if ($payment->reconciliation_required) {
            $warnings[] = 'این پرداخت نیازمند بررسی و تطبیق دستی است.';
        }

        if (self::isLateSuccessForCancelledOrder($payment)) {
            $warnings[] = 'پرداخت تأیید شده اما سفارش مرتبط لغو شده است.';
        }

        if (self::hasInvalidReservationCoverage($payment)) {
            $warnings[] = 'پرداخت تأیید شده اما پوشش رزرو موجودی سفارش معتبر یا قطعی نیست.';
        }

        if (self::hasDuplicateSuccessfulAttempts($payment)) {
            $warnings[] = 'برای این سفارش بیش از یک پرداخت موفق ثبت شده و حداقل یکی نیازمند تطبیق است.';
        }

        return $warnings;
    }

    public static function safeMetadata(array $metadata): string
    {
        return SafeMetadata::format($metadata);
    }

    public static function isLateSuccessForCancelledOrder(Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Paid
            && $payment->order?->status?->value === 'cancelled';
    }

    public static function hasInvalidReservationCoverage(Payment $payment): bool
    {
        if ($payment->status !== PaymentStatus::Paid || ! $payment->order) {
            return false;
        }

        $items = $payment->order->items;

        if ($items->isEmpty()) {
            return false;
        }

        return $items->contains(function ($item): bool {
            return $item->inventoryReservation?->status !== InventoryReservationStatus::Committed;
        });
    }

    public static function hasDuplicateSuccessfulAttempts(Payment $payment): bool
    {
        if (! $payment->reconciliation_required || ! $payment->order) {
            return false;
        }

        return $payment->order->payments->where('status', PaymentStatus::Paid)->count() > 1;
    }

    private static function value(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
