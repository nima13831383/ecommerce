<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use InvalidArgumentException;

final class JalaliDate
{
    private const MONTHS = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];

    public static function format(?DateTimeInterface $date, string $format = 'Y/m/d'): ?string
    {
        if ($date === null) {
            return null;
        }
        $timezone = new \DateTimeZone(config('app.timezone', 'Asia/Tehran'));
        $local = $date instanceof CarbonInterface ? $date->copy()->setTimezone($timezone) : (new \DateTimeImmutable($date->format('c')))->setTimezone($timezone);
        [$year, $month, $day] = self::toJalali((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));

        return strtr($format, ['Y' => (string) $year, 'y' => substr((string) $year, -2), 'm' => str_pad((string) $month, 2, '0', STR_PAD_LEFT), 'n' => (string) $month, 'd' => str_pad((string) $day, 2, '0', STR_PAD_LEFT), 'j' => (string) $day, 'F' => self::MONTHS[$month], 'H' => $local->format('H'), 'i' => $local->format('i'), 's' => $local->format('s')]);
    }

    public static function forPicker(DateTimeInterface|string|null $date, bool $withTime = true): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $date = is_string($date) ? CarbonImmutable::parse($date, config('app.timezone', 'Asia/Tehran')) : $date;

        return self::format($date, $withTime ? 'Y/m/d H:i' : 'Y/m/d');
    }

    public static function toGregorian(?string $value, bool $withTime = true): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = self::normalizeDigits(trim($value));

        // Filament actions/tests may provide an already-canonical value;
        // preserve it while browser-facing picker values use Jalali slashes.
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value)) {
            return CarbonImmutable::parse($value, config('app.timezone', 'Asia/Tehran'))->format('Y-m-d H:i:s');
        }

        [$datePart, $timePart] = array_pad(preg_split('/\s+/', $value, 2), 2, null);
        $parts = preg_split('/[\/-]/', $datePart);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('فرمت تاریخ جلالی نامعتبر است.');
        }

        [$jy, $jm, $jd] = array_map('intval', $parts);
        [$gy, $gm, $gd] = self::fromJalali($jy, $jm, $jd);
        $hour = 0;
        $minute = 0;
        $second = 0;

        if ($withTime && $timePart !== null) {
            $time = array_map('intval', explode(':', $timePart));
            $hour = $time[0] ?? 0;
            $minute = $time[1] ?? 0;
            $second = $time[2] ?? 0;
        }

        return CarbonImmutable::create($gy, $gm, $gd, $hour, $minute, $second, config('app.timezone', 'Asia/Tehran'))->format('Y-m-d H:i:s');
    }

    private static function normalizeDigits(string $value): string
    {
        return strtr($value, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function toJalali(int $gy, int $gm, int $gd): array
    {
        $gDays = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jy = $gy <= 1600 ? 0 : 979;
        $gy -= $gy <= 1600 ? 621 : 1600;
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd;
        for ($i = 1; $i < $gm; $i++) {
            $days += $gDays[$i];
        }
        if ($gm > 2 && (($gy2 % 4 === 0 && $gy2 % 100 !== 0) || $gy2 % 400 === 0)) {
            $days++;
        }
        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $jm = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jd = 1 + ($days < 186 ? $days % 31 : ($days - 186) % 30);

        return [$jy, $jm, $jd];
    }

    /** @return array{0:int,1:int,2:int} */
    private static function fromJalali(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186));
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0);
        $monthDays = [31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 1;
        while ($gm <= 12 && $gd > $monthDays[$gm - 1]) {
            $gd -= $monthDays[$gm - 1];
            $gm++;
        }

        return [$gy, $gm, $gd];
    }
}
