<?php

namespace App\Filament\Resources\InventoryReservations\Tables;

use App\Enums\InventoryReservationStatus;
use App\Filament\Resources\Inventory\Support\InventoryPresentation;
use App\Filament\Resources\InventoryReservations\InventoryReservationResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariation;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['orderItem.order:id,order_number,customer_name,status,payment_status'])
                ->with(['inventoryOwner' => fn (MorphTo $relation): MorphTo => $relation->morphWith([Product::class => [], ProductVariation::class => ['product']])]))
            ->columns([
                TextColumn::make('id')->label('شناسه رزرو')->searchable()->sortable()->copyable(),
                TextColumn::make('inventory_owner_label')->label('مالک موجودی')->state(fn (InventoryReservation $record): string => InventoryPresentation::ownerLabel($record->inventoryOwner))->wrap(),
                TextColumn::make('inventory_owner_type')->label('نوع مالک')->state(fn (InventoryReservation $record): string => InventoryPresentation::ownerType($record->inventoryOwner)),
                TextColumn::make('quantity')->label('مقدار')->numeric()->sortable(),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => InventoryPresentation::reservationStatus($state))->color(fn (mixed $state): string => InventoryPresentation::reservationStatusColor($state))->sortable(),
                TextColumn::make('orderItem.order.order_number')->label('سفارش مرتبط')->searchable()->url(fn (InventoryReservation $record): ?string => $record->orderItem?->order ? OrderResource::getUrl('view', ['record' => $record->orderItem->order]) : null),
                TextColumn::make('reference_id')->label('شناسه مرجع')->searchable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('anomaly')->label('هشدار')->boolean()->state(fn (InventoryReservation $record): bool => InventoryPresentation::warnings($record) !== [])->trueIcon('heroicon-o-exclamation-triangle')->trueColor('danger')->falseIcon('heroicon-o-check-circle')->falseColor('gray'),
                TextColumn::make('expires_at')->label('تاریخ انقضا')->dateTime()->sortable(),
                TextColumn::make('created_at')->label('تاریخ ثبت')->dateTime()->sortable(),
                TextColumn::make('committed_at')->label('زمان قطعی‌سازی')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('released_at')->label('زمان آزادسازی')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(self::enumOptions(InventoryReservationStatus::cases(), InventoryPresentation::reservationStatus(...))),
                SelectFilter::make('inventory_owner_type')->label('نوع مالک')->options([Product::class => 'محصول', ProductVariation::class => 'تنوع محصول']),
                Filter::make('past_due')->label('رزرو فعال منقضی‌شده')->query(fn (Builder $query): Builder => $query->where('status', InventoryReservationStatus::Active->value)->whereNotNull('expires_at')->where('expires_at', '<=', now())),
                Filter::make('dates')->label('بازه زمانی')->form([DatePicker::make('from')->label('از تاریخ'), DatePicker::make('until')->label('تا تاریخ')])->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
                Filter::make('search')->label('جست‌وجوی مالک یا سفارش')->form([TextInput::make('value')->label('عبارت')])->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['value'] ?? null), function (Builder $q) use ($data): Builder {
                    $term = '%'.$data['value'].'%';

                    return $q->where(function (Builder $inner) use ($term): void {
                        $inner->whereHas('orderItem.order', fn (Builder $o): Builder => $o->where('order_number', 'like', $term)->orWhere('customer_name', 'like', $term))->orWhere(fn (Builder $owner): Builder => $owner->where('inventory_owner_type', Product::class)->whereIn('inventory_owner_id', Product::query()->select('id')->where('name', 'like', $term)))->orWhere(fn (Builder $owner): Builder => $owner->where('inventory_owner_type', ProductVariation::class)->whereIn('inventory_owner_id', ProductVariation::query()->select('id')->where('sku', 'like', $term)));
                    });
                })),
            ])
            ->recordActions([ViewAction::make()->label('مشاهده')->authorize('view')])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (InventoryReservation $record): string => InventoryReservationResource::getUrl('view', ['record' => $record]));
    }

    private static function enumOptions(array $cases, callable $label): array
    {
        return collect($cases)->mapWithKeys(fn ($case): array => [$case->value => $label($case)])->all();
    }
}
