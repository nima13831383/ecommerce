<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Data\WordpressShippingDataLoader;

class ShippingOptionCatalog
{
    public function __construct(private readonly WordpressShippingDataLoader $dataLoader) {}

    /** @return array<string, string> */
    public function services(): array
    {
        return [
            'pishtaz' => 'پست پیشتاز',
            'vijeh' => 'پست ویژه',
        ];
    }

    /** @return array<string, string> */
    public function parcelTypes(): array
    {
        return [
            'normal' => 'عادی',
            'fragile_liquid' => 'شکستنی یا مایعات',
        ];
    }

    /** @return array<string, string> */
    public function paymentTypes(): array
    {
        return [
            'online' => 'پرداخت آنلاین',
            'cod' => 'پرداخت در محل',
            'postpaid' => 'پس‌کرایه',
            'free' => 'ارسال رایگان',
        ];
    }

    /** @return array<int, string> */
    public function packageSizes(): array
    {
        return $this->dataLoader->pluginPackageSizes() + [
            // Required by the isolated test UI; these labels are absent from plugin 4.4.5.
            14 => 'پاکت جوف B5',
            15 => 'پاکت جوف B4',
        ];
    }
}
