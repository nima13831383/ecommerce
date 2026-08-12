<?php

namespace App\Filament\Resources\TaxClasses\RelationManagers;

use App\Enums\TaxType;
use App\Models\Product;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'محصولات مشمول';

    protected static ?string $modelLabel = 'محصول';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('نام محصول')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('sku')
                    ->label('کد کالا')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('price')
                    ->label('قیمت')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' ریال')
                    ->sortable(),

                TextColumn::make('tax_amount')
                    ->label('مالیات محاسبه‌شده')
                    ->state(function (Product $record): string {
                        $taxClass = $this->getOwnerRecord();

                        $amount = $taxClass->type === TaxType::Percent
                            ? ((float) $record->price * (float) $taxClass->value) / 100
                            : (float) $taxClass->value;

                        return number_format($amount) . ' ریال';
                    })
                    ->badge()
                    ->color('warning'),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn($state): string => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'published' => 'منتشر شده',
                        'draft' => 'پیش‌نویس',
                        'pending' => 'در انتظار',
                        'private' => 'خصوصی',
                        default => (string) $state,
                    })
                    ->color(fn($state): string => match ($state instanceof \BackedEnum ? $state->value : $state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'private' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'draft' => 'پیش‌نویس',
                        'published' => 'منتشر شده',
                        'pending' => 'در انتظار',
                        'private' => 'خصوصی',
                    ]),

                TernaryFilter::make('is_featured')
                    ->label('محصول ویژه'),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label('افزودن محصول')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'sku'])
                    ->recordSelectOptionsQuery(
                        fn(Builder $query) => $query->whereNull('tax_class_id')
                    ),
            ])
            ->recordActions([
                DissociateAction::make()
                    ->label('حذف از کلاس'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make()
                        ->label('حذف گروهی از کلاس'),

                    BulkAction::make('publish')
                        ->label('انتشار گروهی')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn(Collection $records) => $records->each->update(['status' => 'published']))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('محصولی به این کلاس مالیاتی متصل نیست')
            ->emptyStateDescription('با دکمه «افزودن محصول» محصولات بدون کلاس مالیاتی را متصل کنید.');
    }
}
