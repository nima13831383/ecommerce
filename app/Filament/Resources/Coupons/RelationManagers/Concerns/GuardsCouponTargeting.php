<?php

namespace App\Filament\Resources\Coupons\RelationManagers\Concerns;

use App\Exceptions\CouponConfigurationException;
use App\Models\Coupon;
use App\Services\CouponService;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;

trait GuardsCouponTargeting
{
    /** @param  array<int, int|string>  $recordIds */
    protected function guardTargetingMutation(string $dimension, bool $isExcluded, array $recordIds): void
    {
        try {
            app(CouponService::class)->assertTargetingMutation(
                $this->coupon(),
                $dimension,
                $isExcluded,
                $recordIds,
            );
        } catch (CouponConfigurationException $exception) {
            throw ValidationException::withMessages([
                'is_excluded' => $exception->getMessage(),
            ]);
        }
    }

    protected function guardActionTargetingMutation(Action $action, string $dimension, ?int $recordId = null): void
    {
        $data = $action->getData();
        $recordIds = $recordId === null
            ? (array) ($data['recordId'] ?? [])
            : [$recordId];

        $this->guardTargetingMutation(
            $dimension,
            (bool) ($data['is_excluded'] ?? false),
            $recordIds,
        );
    }

    private function coupon(): Coupon
    {
        /** @var Coupon $coupon */
        $coupon = $this->getOwnerRecord();

        return $coupon;
    }
}
