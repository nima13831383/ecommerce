<?php

namespace App\Filament\Resources\TaxClasses\Tables;

use App\Enums\TaxType;
use App\Filament\Resources\TaxClasses\Schemas\TaxClassForm;
use App\Models\TaxClass;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TaxClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->sortable(),
                TextColumn::make('type')->label('نوع')->badge(),
                TextColumn::make('value')
                    ->label('نرخ')
                    ->formatStateUsing(fn (mixed $state, TaxClass $record): string => TaxClassForm::formatValue($record))
                    ->sortable(),
                IconColumn::make('is_active')->label('فعال')->boolean()->sortable(),
                IconColumn::make('is_default')->label('پیش‌فرض')->boolean(),
                TextColumn::make('products_count')->label('محصولات')->counts('products')->badge()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('type')->label('نوع مالیات')->options(TaxType::class),
                TernaryFilter::make('is_active')->label('وضعیت'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
