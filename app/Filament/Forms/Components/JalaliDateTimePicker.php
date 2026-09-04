<?php

namespace App\Filament\Forms\Components;

use App\Filament\Forms\Components\Concerns\InteractsWithJalaliState;
use Filament\Forms\Components\TextInput;

class JalaliDateTimePicker extends TextInput
{
    use InteractsWithJalaliState;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureJalaliState(true);
    }

    public function seconds(bool $condition = true): static
    {
        return $this;
    }
}
