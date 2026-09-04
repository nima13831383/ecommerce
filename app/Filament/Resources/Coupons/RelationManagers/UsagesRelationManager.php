<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use App\Support\JalaliDate;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'سوابق مصرف';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('کاربر')->searchable(),
            TextColumn::make('user.email')->label('ایمیل'),
            TextColumn::make('user.mobile')->label('موبایل'),
            TextColumn::make('order.order_number')->label('شماره سفارش'),
            TextColumn::make('discount_amount')->label('مبلغ تخفیف')->numeric(),
            TextColumn::make('created_at')->label('زمان مصرف')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->sortable(),
        ])->defaultSort('created_at', 'desc');
    }
}
