<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Shop = 'shop';
    case Marketing = 'marketing';
    case SiteSettings = 'site-settings';

    public function getLabel(): string
    {
        return match ($this) {
            self::Shop => 'فروشگاه',
            self::Marketing => 'بازاریابی',
            self::SiteSettings => 'site settings',
        };
    }
}
