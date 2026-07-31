<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('brand.name')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('price')
                    ->numeric()
                    ->sortable()
                    ->suffix(' T'),

                TextColumn::make('stock_status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'in_stock'     => 'success',
                        'out_of_stock' => 'danger',
                        'on_backorder' => 'warning',
                        default        => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'published' => 'success',
                        'draft'     => 'gray',
                        'pending'   => 'warning',
                        'private'   => 'info',
                        default     => 'gray',
                    }),

                IconColumn::make('is_featured')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'simple'       => 'Simple',
                        'variable'     => 'Variable',
                        'grouped'      => 'Grouped',
                        'external'     => 'External',
                        'downloadable' => 'Downloadable',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                        'pending'   => 'Pending',
                        'private'   => 'Private',
                    ]),
                SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_featured'),
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
            ])
            ->defaultSort('created_at', 'desc');
    }
}
