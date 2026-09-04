<?php

namespace App\Filament\Resources\InventoryTransactions\Tables;

use App\Enums\InventoryOperation;
use App\Filament\Forms\Components\JalaliDatePicker;
use App\Filament\Resources\Inventory\Support\InventoryPresentation;
use App\Filament\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Support\JalaliDate;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['inventoryReservation.orderItem.order:id,order_number', 'createdBy:id,name'])
                ->with(['inventoryOwner' => fn (MorphTo $relation): MorphTo => $relation->morphWith([Product::class => [], ProductVariation::class => ['product']])]))
            ->columns([
                TextColumn::make('id')->label('شناسه تراکنش')->searchable()->sortable()->copyable(),
                TextColumn::make('owner')->label('مالک موجودی')->state(fn (InventoryTransaction $record): string => InventoryPresentation::ownerLabel($record->inventoryOwner))->wrap(),
                TextColumn::make('operation')->label('نوع عملیات')->badge()->formatStateUsing(fn (mixed $state): string => InventoryPresentation::operation($state))->color(fn (mixed $state): string => InventoryPresentation::operationColor($state))->sortable(),
                TextColumn::make('quantity_delta')->label('مقدار تغییر')->state(fn (InventoryTransaction $record): string => InventoryPresentation::delta($record->quantity_delta))->color(fn (InventoryTransaction $record): string => $record->quantity_delta > 0 ? 'success' : ($record->quantity_delta < 0 ? 'danger' : 'gray'))->sortable(),
                TextColumn::make('quantity_before')->label('موجودی قبل')->numeric()->sortable(),
                TextColumn::make('quantity_after')->label('موجودی بعد')->numeric()->sortable(),
                TextColumn::make('order_number')->label('سفارش مرتبط')->state(fn (InventoryTransaction $record): string => $record->inventoryReservation?->orderItem?->order?->order_number ?? '—')->url(fn (InventoryTransaction $record): ?string => $record->inventoryReservation?->orderItem?->order ? OrderResource::getUrl('view', ['record' => $record->inventoryReservation->orderItem->order]) : null),
                TextColumn::make('reference_id')->label('شناسه مرجع')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')->label('عامل ثبت‌کننده')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('تاریخ ثبت')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->sortable(),
            ])
            ->filters([
                SelectFilter::make('operation')->label('نوع عملیات')->options(self::enumOptions(InventoryOperation::cases(), InventoryPresentation::operation(...))),
                SelectFilter::make('inventory_owner_type')->label('نوع مالک')->options([Product::class => 'محصول', ProductVariation::class => 'تنوع محصول']),
                SelectFilter::make('direction')->label('جهت تغییر')->options(['positive' => 'افزایش', 'negative' => 'کاهش'])->query(fn (Builder $query, array $data): Builder => $query->when(($data['value'] ?? null) === 'positive', fn (Builder $q): Builder => $q->where('quantity_delta', '>', 0))->when(($data['value'] ?? null) === 'negative', fn (Builder $q): Builder => $q->where('quantity_delta', '<', 0))),
                Filter::make('dates')->label('بازه زمانی')->form([JalaliDatePicker::make('from')->label('از تاریخ'), JalaliDatePicker::make('until')->label('تا تاریخ')])->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
                Filter::make('owner_search')->label('جست‌وجوی مالک')->form([TextInput::make('value')->label('نام محصول یا SKU')])->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['value'] ?? null), function (Builder $q) use ($data): Builder {
                    $term = '%'.$data['value'].'%';

                    return $q->where(function (Builder $inner) use ($term): void {
                        $inner->where(fn (Builder $owner): Builder => $owner->where('inventory_owner_type', Product::class)->whereIn('inventory_owner_id', Product::query()->select('id')->where('name', 'like', $term)))->orWhere(fn (Builder $owner): Builder => $owner->where('inventory_owner_type', ProductVariation::class)->whereIn('inventory_owner_id', ProductVariation::query()->select('id')->where('sku', 'like', $term)));
                    });
                })),
            ])
            ->recordActions([ViewAction::make()->label('مشاهده')->authorize('view')])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (InventoryTransaction $record): string => InventoryTransactionResource::getUrl('view', ['record' => $record]));
    }

    private static function enumOptions(array $cases, callable $label): array
    {
        return collect($cases)->mapWithKeys(fn ($case): array => [$case->value => $label($case)])->all();
    }
}
