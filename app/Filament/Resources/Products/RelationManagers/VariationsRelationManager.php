<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariationsRelationManager extends RelationManager
{
    protected static string $relationship = 'variations';

    protected static ?string $title = 'Variations';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->label('SKU')->maxLength(255),

            Select::make('attributeValues')
                ->relationship('attributeValues', 'value')
                ->multiple()
                ->searchable()
                ->preload()
                ->label('Attribute values'),

            TextInput::make('price')->numeric()->required()->suffix('T'),
            TextInput::make('sale_price')->numeric()->suffix('T'),

            DateTimePicker::make('sale_starts_at')->seconds(false),
            DateTimePicker::make('sale_ends_at')->seconds(false),

            Toggle::make('manage_stock')->default(true)->live(),
            TextInput::make('stock_quantity')
                ->numeric()
                ->default(0)
                ->visible(fn(Get $get) => $get('manage_stock')),

            Select::make('stock_status')
                ->options([
                    'in_stock'     => 'In stock',
                    'out_of_stock' => 'Out of stock',
                    'on_backorder' => 'On backorder',
                ])
                ->default('in_stock'),

            TextInput::make('weight')->numeric()->suffix('kg'),
            TextInput::make('image')->maxLength(255),
            Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('price')->numeric()->suffix(' T'),
                TextColumn::make('sale_price')->numeric()->suffix(' T'),
                TextColumn::make('stock_quantity')->numeric(),
                TextColumn::make('stock_status')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
