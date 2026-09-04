<?php

namespace App\Filament\Resources\Settings\Tables;

use App\Support\JalaliDate;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group')
                    ->searchable(),
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                IconColumn::make('is_public')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
