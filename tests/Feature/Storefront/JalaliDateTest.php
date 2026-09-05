<?php

use App\Support\JalaliDate;
use Carbon\Carbon;

test('storefront dates use Tehran timezone and Jalali presentation', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Tehran');
    expect(JalaliDate::format(Carbon::parse('2026-09-04 12:00:00', 'UTC'), 'j F Y H:i'))
        ->toBe('۱۳ شهریور ۱۴۰۵ ۱۵:۳۰');
});
