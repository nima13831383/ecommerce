<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaxType: string implements HasLabel, HasColor, HasIcon
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percent => 'درصدی',
            self::Fixed => 'مبلغ ثابت',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Percent => 'info',
            self::Fixed => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Percent => 'heroicon-o-receipt-percent',
            self::Fixed => 'heroicon-o-banknotes',
        };
    }

    public function affix(): string
    {
        return match ($this) {
            self::Percent => '%',
            self::Fixed => 'ریال',
        };
    }
    public static function parse(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return filled($value) ? self::tryFrom((string) $value) : null;
    }
}
