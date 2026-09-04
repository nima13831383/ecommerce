<?php

namespace App\Filament\Forms\Components\Concerns;

use App\Filament\Forms\Validation\JalaliAfterOrEqual;
use App\Support\JalaliDate;

trait InteractsWithJalaliState
{
    protected bool $jalaliWithTime = true;

    protected function configureJalaliState(bool $withTime): void
    {
        $this->jalaliWithTime = $withTime;
        $this->type('text')
            ->inputMode('numeric')
            ->placeholder($withTime ? '۱۴۰۵/۰۶/۱۴ ۱۵:۳۰' : '۱۴۰۵/۰۶/۱۴')
            ->extraInputAttributes([
                'data-jalali-picker' => $withTime ? 'datetime' : 'date',
                'dir' => 'ltr',
                'autocomplete' => 'off',
            ]);

        $this->afterStateHydrated(function ($component, $state): void {
            $component->state(JalaliDate::forPicker($state, $this->jalaliWithTime));
        });

        $this->dehydrateStateUsing(function ($state): ?string {
            return JalaliDate::toGregorian($state, $this->jalaliWithTime);
        });
    }

    public function minDate(mixed $date): static
    {
        return $this->rule(new JalaliAfterOrEqual($date, $this->jalaliWithTime));
    }
}
