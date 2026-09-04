<?php

namespace App\Filament\Forms\Components;

use App\Filament\Forms\Components\Concerns\InteractsWithJalaliState;
use Filament\Forms\Components\TextInput;

class JalaliDatePicker extends TextInput
{
    use InteractsWithJalaliState;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureJalaliState(false);
    }
}
