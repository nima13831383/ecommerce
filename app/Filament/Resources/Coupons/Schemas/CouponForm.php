<?php

namespace App\Filament\Resources\Coupons\Schemas;

use App\Filament\Forms\Components\JalaliDateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->default(null),
                Select::make('type')
                    ->options(['percent' => 'Percent', 'fixed_cart' => 'Fixed cart', 'fixed_product' => 'Fixed product'])
                    ->default('fixed_cart')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('min_spend')
                    ->numeric()
                    ->default(null),
                TextInput::make('max_spend')
                    ->numeric()
                    ->default(null),
                TextInput::make('max_discount')
                    ->numeric()
                    ->default(null),
                TextInput::make('usage_limit')
                    ->numeric()
                    ->default(null),
                TextInput::make('usage_limit_per_user')
                    ->numeric()
                    ->default(null),
                TextInput::make('usage_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('individual_use_only')
                    ->required(),
                Toggle::make('exclude_discounted_products')
                    ->label('عدم اعمال روی محصولات دارای تخفیف')
                    ->default(false),
                Toggle::make('is_active')
                    ->required(),
                JalaliDateTimePicker::make('starts_at'),
                JalaliDateTimePicker::make('expires_at'),
            ]);
    }
}
