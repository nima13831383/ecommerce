<?php

namespace App\Filament\Resources\Inventory\Support;

use App\Enums\InventoryOperation;
use App\Enums\InventoryReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Inventory\InventoryService;
use App\Support\PersianNumber;
use Illuminate\Database\Eloquent\Model;

class InventoryPresentation
{
    public static function ownerLabel(?Model $owner): string
    {
        if ($owner instanceof ProductVariation) {
            return ($owner->product?->name ?? 'محصول حذف‌شده').' / SKU: '.($owner->sku ?: '—');
        }

        if ($owner instanceof Product) {
            return 'محصول: '.($owner->name ?: 'بدون نام');
        }

        return 'مالک تاریخی حذف‌شده یا ناشناخته';
    }

    public static function ownerType(?Model $owner): string
    {
        return match (true) {
            $owner instanceof ProductVariation => 'تنوع محصول',
            $owner instanceof Product => 'محصول',
            default => 'ناشناخته',
        };
    }

    public static function reservationStatus(mixed $status): string
    {
        return match ($status instanceof InventoryReservationStatus ? $status : InventoryReservationStatus::tryFrom((string) $status)) {
            InventoryReservationStatus::Active => 'فعال',
            InventoryReservationStatus::Committed => 'قطعی‌شده',
            InventoryReservationStatus::Released => 'آزادشده',
            InventoryReservationStatus::Expired => 'منقضی‌شده',
            default => 'نامشخص',
        };
    }

    public static function reservationStatusColor(mixed $status): string
    {
        return match ($status instanceof InventoryReservationStatus ? $status : InventoryReservationStatus::tryFrom((string) $status)) {
            InventoryReservationStatus::Active => 'warning',
            InventoryReservationStatus::Committed => 'success',
            InventoryReservationStatus::Released, InventoryReservationStatus::Expired => 'gray',
            default => 'gray',
        };
    }

    public static function operation(mixed $operation): string
    {
        return match ($operation instanceof InventoryOperation ? $operation : InventoryOperation::tryFrom((string) $operation)) {
            InventoryOperation::OpeningStock => 'موجودی اولیه',
            InventoryOperation::ManualAdjustment => 'اصلاح دستی',
            InventoryOperation::Restock => 'تأمین موجودی',
            InventoryOperation::Deduction => 'کسر موجودی',
            InventoryOperation::ReservationCommit => 'قطعی‌سازی رزرو',
            InventoryOperation::Correction => 'اصلاح',
            default => 'نامشخص',
        };
    }

    public static function operationColor(mixed $operation): string
    {
        return match ($operation instanceof InventoryOperation ? $operation : InventoryOperation::tryFrom((string) $operation)) {
            InventoryOperation::Restock, InventoryOperation::OpeningStock => 'success',
            InventoryOperation::ReservationCommit, InventoryOperation::Deduction => 'warning',
            InventoryOperation::ManualAdjustment, InventoryOperation::Correction => 'info',
            default => 'gray',
        };
    }

    public static function delta(mixed $value): string
    {
        $value = (int) $value;

        return ($value > 0 ? '+' : '').PersianNumber::integer($value);
    }

    public static function stockSummary(?Model $owner, InventoryService $inventory): array
    {
        if (! $owner instanceof Product && ! $owner instanceof ProductVariation) {
            return ['on_hand' => null, 'reserved' => null, 'available' => null, 'status' => 'نامشخص'];
        }

        $onHand = (int) $owner->stock_quantity;
        $available = $inventory->availableQuantity($owner);

        return [
            'on_hand' => $onHand,
            'reserved' => max(0, $onHand - $available),
            'available' => $available,
            'status' => (string) ($owner->stock_status ?? 'نامشخص'),
        ];
    }

    public static function isPastDue(InventoryReservation $reservation): bool
    {
        return $reservation->status === InventoryReservationStatus::Active
            && $reservation->expires_at?->isPast() === true;
    }

    public static function warnings(InventoryReservation $reservation): array
    {
        $warnings = [];

        if (self::isPastDue($reservation)) {
            $warnings[] = 'این رزرو فعال از زمان انقضا عبور کرده و نیازمند بررسی است.';
        }

        if ($reservation->inventoryOwner === null) {
            $warnings[] = 'مالک تاریخی موجودی دیگر در دسترس نیست.';
        }

        if ($reservation->reference_type === 'order_item' && $reservation->orderItem?->order === null) {
            $warnings[] = 'سفارش مرتبط با این رزرو یافت نشد.';
        }

        if ($reservation->orderItem?->order?->status?->value === 'cancelled') {
            $warnings[] = 'این رزرو به سفارش لغوشده مرتبط است.';
        }

        return $warnings;
    }
}
