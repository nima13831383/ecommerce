<?php

namespace App\Filament\Resources\Shipments\Tables;

use App\Enums\ShipmentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Shipments\ShipmentResource;
use App\Filament\Resources\Shipments\Support\ShipmentPresentation;
use App\Models\Shipment;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('order:id,order_number'))
            ->columns([
                TextColumn::make('shipment_number')->label('شماره مرسوله')->searchable()->sortable()->copyable()->weight('bold'),
                TextColumn::make('order.order_number')->label('شماره سفارش')->searchable()->sortable()->url(fn (Shipment $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => ShipmentPresentation::status($state))->color(fn (mixed $state): string => ShipmentPresentation::color($state))->sortable(),
                TextColumn::make('tracking_number')->label('کد رهگیری')->searchable()->placeholder('ثبت نشده'),
                TextColumn::make('carrier_service')->label('خدمت پستی')->placeholder('ثبت نشده'),
                TextColumn::make('created_at')->label('تاریخ ایجاد')->dateTime()->sortable(),
                TextColumn::make('shipped_at')->label('تاریخ ارسال')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')->label('تاریخ تحویل')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(ShipmentPresentation::options(ShipmentStatus::cases())),
                Filter::make('created_between')->label('بازه تاریخ')->form([
                    DatePicker::make('from')->label('از'),
                    DatePicker::make('until')->label('تا'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([ViewAction::make()->label('مشاهده')->authorize('view')])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Shipment $record): string => ShipmentResource::getUrl('view', ['record' => $record]));
    }
}
