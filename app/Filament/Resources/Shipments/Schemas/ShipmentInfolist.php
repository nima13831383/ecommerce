<?php

namespace App\Filament\Resources\Shipments\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Shipments\Support\ShipmentPresentation;
use App\Models\Shipment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('خلاصه مرسوله')->columns(4)->schema([
                TextEntry::make('shipment_number')->label('شماره مرسوله')->copyable()->weight('bold'),
                TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => ShipmentPresentation::status($state))->color(fn (mixed $state): string => ShipmentPresentation::color($state)),
                TextEntry::make('order.order_number')->label('شماره سفارش')->url(fn (Shipment $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextEntry::make('carrier_service')->label('خدمت پستی')->placeholder('ثبت نشده'),
                TextEntry::make('tracking_number')->label('کد رهگیری')->placeholder('ثبت نشده')->copyable(),
                TextEntry::make('tracking_url')->label('لینک رهگیری')->url(fn (mixed $state): ?string => filled($state) ? (string) $state : null)->placeholder('ثبت نشده'),
                TextEntry::make('shipped_at')->label('زمان ارسال')->dateTime()->placeholder('ثبت نشده'),
                TextEntry::make('delivered_at')->label('زمان تحویل')->dateTime()->placeholder('ثبت نشده'),
                TextEntry::make('cancelled_at')->label('زمان لغو')->dateTime()->placeholder('ثبت نشده'),
            ]),
            Section::make('تصویر تاریخی ارسال')->columns(2)->schema([
                TextEntry::make('shipping_address')->label('آدرس مقصد')->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: 'ثبت نشده')->prose(),
                TextEntry::make('shipping_snapshot')->label('داده‌های ارسال در زمان سفارش')->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: 'ثبت نشده')->prose(),
                TextEntry::make('notes')->label('یادداشت عملیاتی')->placeholder('ثبت نشده')->prose()->columnSpanFull(),
            ]),
            Section::make('تاریخچه وضعیت')->schema([
                RepeatableEntry::make('statusHistories')->label('تغییرات')->state(fn (Shipment $record): array => $record->statusHistories->sortBy('created_at')->all())->table([
                    TableColumn::make('وضعیت قبلی'), TableColumn::make('وضعیت جدید'), TableColumn::make('کاربر'), TableColumn::make('یادداشت'), TableColumn::make('زمان'),
                ])->schema([
                    TextEntry::make('from_status')->label('وضعیت قبلی')->placeholder('—')->formatStateUsing(fn (mixed $state): string => $state ? ShipmentPresentation::status($state) : '—'),
                    TextEntry::make('to_status')->label('وضعیت جدید')->formatStateUsing(fn (mixed $state): string => ShipmentPresentation::status($state)),
                    TextEntry::make('user.name')->label('کاربر')->placeholder('سیستم'),
                    TextEntry::make('note')->label('یادداشت')->placeholder('—')->prose(),
                    TextEntry::make('created_at')->label('زمان')->dateTime(),
                ]),
            ]),
        ]);
    }
}
