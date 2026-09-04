<?php

use App\Filament\Forms\Components\JalaliDatePicker;
use App\Filament\Forms\Components\JalaliDateTimePicker;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;

it('converts a Jalali date to the canonical Gregorian date', function (): void {
    expect(JalaliDate::toGregorian('1405/06/14', false))->toBe('2026-09-05 00:00:00');
    expect(JalaliDate::forPicker(CarbonImmutable::parse('2026-09-05 12:30:00', 'Asia/Tehran')))->toBe('1405/06/14 12:30');
});

it('preserves Tehran local time and nullable picker values', function (): void {
    expect(JalaliDate::toGregorian('۱۴۰۵/۰۶/۱۴ ۱۵:۳۰'))->toBe('2026-09-05 15:30:00');
    expect(JalaliDate::toGregorian(null))->toBeNull();
    expect(JalaliDate::forPicker(null))->toBeNull();
});

it('uses centralized Jalali picker components for date and datetime fields', function (): void {
    expect(JalaliDatePicker::make('from'))->toBeInstanceOf(JalaliDatePicker::class);
    expect(JalaliDateTimePicker::make('published_at'))->toBeInstanceOf(JalaliDateTimePicker::class);
});
