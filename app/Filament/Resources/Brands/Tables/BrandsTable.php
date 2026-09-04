<?php

namespace App\Filament\Resources\Brands\Tables;

use App\Support\JalaliDate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('logo')->label('لوگو')->disk('public')->circular(),

                TextColumn::make('name')->label('نام')->searchable()->sortable(),

                TextColumn::make('slug')->label('نامک')->searchable()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('products_count')
                    ->label('محصولات')
                    ->counts('products')
                    ->badge()
                    ->sortable(),

                ToggleColumn::make('is_active')->label('فعال'),
                ToggleColumn::make('is_featured')->label('ویژه'),

                TextColumn::make('sort_order')->label('ترتیب')->sortable(),

                TextColumn::make('created_at')->label('ایجاد')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('فعال'),
                TernaryFilter::make('is_featured')->label('ویژه'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
