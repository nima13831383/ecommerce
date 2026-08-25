<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Services\CouponService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('غیرفعال‌سازی و حذف نرم')->authorize('delete'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(CouponService::class)->assertValidConfigurationData($data);

        return $data;
    }
}
