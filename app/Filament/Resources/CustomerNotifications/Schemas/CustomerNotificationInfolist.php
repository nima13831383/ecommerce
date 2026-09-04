<?php

namespace App\Filament\Resources\CustomerNotifications\Schemas;

use App\Filament\Resources\CustomerNotifications\Support\CustomerNotificationPresentation;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CustomerNotification;
use App\Support\JalaliDate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('خلاصه اعلان')->columns(4)->schema([
                TextEntry::make('type')->label('نوع')->formatStateUsing(fn (mixed $state): string => CustomerNotificationPresentation::type($state))->badge(),
                TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn (mixed $state): string => CustomerNotificationPresentation::status($state))->badge(),
                TextEntry::make('channel')->label('کانال')->formatStateUsing(fn (mixed $state): string => CustomerNotificationPresentation::channel($state)),
                TextEntry::make('order.order_number')->label('شماره سفارش')->url(fn (CustomerNotification $record): ?string => $record->order_id ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null),
                TextEntry::make('attempts')->label('تلاش‌ها'),
                TextEntry::make('queued_at')->label('زمان صف')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                TextEntry::make('sent_at')->label('زمان ارسال')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                TextEntry::make('failed_at')->label('زمان خطا')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
            ]),
            Section::make('اطلاعات گیرنده و محتوا')->columns(2)->schema([
                TextEntry::make('recipient_snapshot')->label('گیرنده')->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '—')->prose(),
                TextEntry::make('payload_snapshot')->label('داده اعلان')->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '—')->prose(),
                TextEntry::make('last_error')->label('آخرین خطا')->placeholder('—')->prose()->columnSpanFull(),
            ]),
        ]);
    }
}
