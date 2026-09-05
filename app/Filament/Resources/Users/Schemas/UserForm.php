<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('نام')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('ایمیل')
                ->email()
                ->maxLength(255)
                ->nullable()
                ->rule(fn (?User $record) => Rule::unique('users', 'email')->ignore($record?->getKey())),
            TextInput::make('mobile')
                ->label('شماره تماس')
                ->tel()
                ->maxLength(15)
                ->rule(fn (?User $record) => Rule::unique('users', 'mobile')->ignore($record?->getKey())),
        ])->columns(2);
    }
}
