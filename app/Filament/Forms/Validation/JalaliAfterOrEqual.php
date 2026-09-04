<?php

namespace App\Filament\Forms\Validation;

use App\Support\JalaliDate;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;

final class JalaliAfterOrEqual implements ValidationRule
{
    public function __construct(private readonly mixed $minimum, private readonly bool $withTime = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        try {
            $canonical = JalaliDate::toGregorian((string) $value, $this->withTime);
            $minimum = $this->minimum instanceof DateTimeInterface
                ? $this->minimum
                : now()->parse((string) $this->minimum);

            if ($canonical !== null && now()->parse($canonical)->lt($minimum)) {
                $fail('زمان انتخاب‌شده باید پس از تاریخ مجاز باشد.');
            }
        } catch (\Throwable) {
            $fail('فرمت تاریخ جلالی نامعتبر است.');
        }
    }
}
