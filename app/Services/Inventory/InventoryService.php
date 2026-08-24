<?php

namespace App\Services\Inventory;

use App\Enums\InventoryOperation;
use App\Enums\InventoryReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidInventoryAdjustmentException;
use App\Exceptions\InvalidReservationStateException;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function adjust(Product|ProductVariation $owner, int $quantityDelta, InventoryOperation $operation = InventoryOperation::ManualAdjustment, ?string $referenceType = null, ?string $referenceId = null, ?string $reason = null): InventoryTransaction
    {
        $this->assertNonZero($quantityDelta);

        return DB::transaction(function () use ($owner, $quantityDelta, $operation, $referenceType, $referenceId, $reason): InventoryTransaction {
            $owner = $this->lockOwner($owner);
            $existing = $this->existingTransaction($owner, $operation, $referenceType, $referenceId);

            if ($existing) {
                if ($existing->quantity_delta !== $quantityDelta) {
                    throw new InvalidInventoryAdjustmentException('مرجع عملیات موجودی با تغییر متفاوت تکرار شده است.');
                }

                return $existing;
            }

            return $this->adjustLocked($owner, $quantityDelta, $operation, $referenceType, $referenceId, $reason);
        });
    }

    public function setOnHand(Product|ProductVariation $owner, int $quantity, InventoryOperation $operation = InventoryOperation::ManualAdjustment, ?string $reason = null): ?InventoryTransaction
    {
        if ($quantity < 0) {
            throw new InvalidInventoryAdjustmentException('موجودی نمی‌تواند منفی باشد.');
        }

        return DB::transaction(function () use ($owner, $quantity, $operation, $reason): ?InventoryTransaction {
            $owner = $this->lockOwner($owner);
            $delta = $quantity - (int) $owner->stock_quantity;

            return $delta === 0 ? null : $this->adjustLocked($owner, $delta, $operation, null, null, $reason);
        });
    }

    public function reserve(Product|ProductVariation $owner, int $quantity, CarbonInterface $expiresAt, string $referenceType, string $referenceId): InventoryReservation
    {
        $this->assertPositive($quantity);
        if ($expiresAt->isPast()) {
            throw new InvalidInventoryAdjustmentException('زمان انقضای رزرو باید در آینده باشد.');
        }

        return DB::transaction(function () use ($owner, $quantity, $expiresAt, $referenceType, $referenceId): InventoryReservation {
            $owner = $this->lockOwner($owner);
            $existing = InventoryReservation::query()->where($this->ownerWhere($owner))->where('reference_type', $referenceType)->where('reference_id', $referenceId)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->quantity !== $quantity) {
                    throw new InvalidInventoryAdjustmentException('مرجع رزرو با تعداد متفاوت تکرار شده است.');
                }

                return $existing;
            }
            if ($quantity > $this->availableLocked($owner)) {
                throw new InsufficientStockException('موجودی قابل رزرو کافی نیست.');
            }

            return InventoryReservation::create([...$this->ownerAttributes($owner), 'quantity' => $quantity, 'status' => InventoryReservationStatus::Active, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'expires_at' => $expiresAt]);
        });
    }

    public function release(InventoryReservation $reservation, bool $expired = false): InventoryReservation
    {
        return DB::transaction(function () use ($reservation, $expired): InventoryReservation {
            $reservation = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if (in_array($reservation->status, [InventoryReservationStatus::Released, InventoryReservationStatus::Expired], true)) {
                return $reservation;
            }
            if ($reservation->status !== InventoryReservationStatus::Active) {
                throw new InvalidReservationStateException('این رزرو قابل آزادسازی نیست.');
            }
            $reservation->update(['status' => $expired ? InventoryReservationStatus::Expired : InventoryReservationStatus::Released, 'released_at' => now()]);

            return $reservation;
        });
    }

    public function commit(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation): InventoryReservation {
            $reservation = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status === InventoryReservationStatus::Committed) {
                return $reservation;
            }
            if ($reservation->status !== InventoryReservationStatus::Active) {
                throw new InvalidReservationStateException('این رزرو قابل ثبت نیست.');
            }
            $owner = $this->lockOwner($reservation->inventoryOwner);
            if ((int) $owner->stock_quantity < $reservation->quantity) {
                throw new InsufficientStockException('موجودی فیزیکی برای ثبت رزرو کافی نیست.');
            }
            $reservation->update(['status' => InventoryReservationStatus::Committed, 'committed_at' => now()]);
            $this->adjustLocked($owner, -$reservation->quantity, InventoryOperation::ReservationCommit, 'inventory_reservation', (string) $reservation->id, null, true);

            return $reservation;
        });
    }

    public function expireDueReservations(): int
    {
        $expired = 0;
        InventoryReservation::query()->where('status', InventoryReservationStatus::Active)->where('expires_at', '<=', now())->orderBy('id')->each(function (InventoryReservation $reservation) use (&$expired): void {
            $this->release($reservation, true);
            $expired++;
        });

        return $expired;
    }

    public function availableQuantity(Product|ProductVariation $owner): int
    {
        return max(0, (int) $owner->stock_quantity - $this->activeReserved($owner));
    }

    private function adjustLocked(Product|ProductVariation $owner, int $delta, InventoryOperation $operation, ?string $referenceType, ?string $referenceId, ?string $reason, bool $reservationCommit = false): InventoryTransaction
    {
        $before = (int) $owner->stock_quantity;
        $after = $before + $delta;
        if ($after < 0 || (! $reservationCommit && $after < $this->activeReserved($owner))) {
            throw new InsufficientStockException('موجودی قابل فروش کافی نیست.');
        }
        $owner->forceFill(['stock_quantity' => $after, 'stock_status' => $after > 0 ? 'in_stock' : 'out_of_stock'])->save();

        return InventoryTransaction::create([...$this->ownerAttributes($owner), 'operation' => $operation, 'quantity_delta' => $delta, 'quantity_before' => $before, 'quantity_after' => $after, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'reason' => $reason]);
    }

    private function lockOwner(Product|ProductVariation $owner): Product|ProductVariation
    {
        if ($owner instanceof Product && $owner->type === 'variable') {
            throw new InvalidInventoryAdjustmentException('موجودی محصول متغیر باید روی تنوع مدیریت شود.');
        }

        return $owner instanceof Product ? Product::query()->lockForUpdate()->findOrFail($owner->id) : ProductVariation::query()->lockForUpdate()->findOrFail($owner->id);
    }

    private function activeReserved(Product|ProductVariation $owner): int
    {
        return (int) InventoryReservation::query()->where($this->ownerWhere($owner))->where('status', InventoryReservationStatus::Active)->where('expires_at', '>', now())->sum('quantity');
    }

    private function availableLocked(Product|ProductVariation $owner): int
    {
        return max(0, (int) $owner->stock_quantity - $this->activeReserved($owner));
    }

    private function existingTransaction(Product|ProductVariation $owner, InventoryOperation $operation, ?string $type, ?string $id): ?InventoryTransaction
    {
        return $type && $id ? InventoryTransaction::query()->where($this->ownerWhere($owner))->where('operation', $operation)->where('reference_type', $type)->where('reference_id', $id)->first() : null;
    }

    private function ownerAttributes(Product|ProductVariation $owner): array
    {
        return ['inventory_owner_type' => $owner::class, 'inventory_owner_id' => $owner->id];
    }

    private function ownerWhere(Product|ProductVariation $owner): array
    {
        return $this->ownerAttributes($owner);
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidInventoryAdjustmentException('تعداد باید حداقل یک باشد.');
        }
    }

    private function assertNonZero(int $quantity): void
    {
        if ($quantity === 0) {
            throw new InvalidInventoryAdjustmentException('تغییر موجودی نمی‌تواند صفر باشد.');
        }
    }
}
