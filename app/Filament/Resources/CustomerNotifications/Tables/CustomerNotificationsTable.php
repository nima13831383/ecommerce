<?php

namespace App\Filament\Resources\CustomerNotifications\Tables;

use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationStatus;
use App\Enums\CustomerNotificationType;
use App\Filament\Resources\CustomerNotifications\CustomerNotificationResource;
use App\Filament\Resources\CustomerNotifications\Support\CustomerNotificationPresentation;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CustomerNotification;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('order:id,order_number'))
            ->columns([
                TextColumn::make('type')->label('نوع')->formatStateUsing(fn (mixed $state): string => CustomerNotificationPresentation::type($state))->badge(),
                TextColumn::make('status')->label('وضعیت')->formatStateUsing(fn (mixed $state): string => CustomerNotificationPresentation::status($state))->badge()->color(fn (mixed $state): string => CustomerNotificationPresentation::statusColor($state)),
                TextColumn::make('channel')->label('کانال')->formatStateUsing(fn (mixed $state): string => CustomerNotificationPresentation::channel($state)),
                TextColumn::make('order.order_number')->label('شماره سفارش')->searchable()->url(fn (CustomerNotification $record): ?string => $record->order_id ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null),
                TextColumn::make('recipient_snapshot')->label('گیرنده')->state(fn (CustomerNotification $record): string => implode(' | ', array_filter([$record->recipient_snapshot['name'] ?? null, $record->recipient_snapshot['mobile'] ?? null, $record->recipient_snapshot['email'] ?? null]))),
                TextColumn::make('attempts')->label('تلاش‌ها')->sortable(),
                TextColumn::make('created_at')->label('ایجاد')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('نوع')->options(collect(CustomerNotificationType::cases())->mapWithKeys(fn ($type): array => [$type->value => CustomerNotificationPresentation::type($type)])->all()),
                SelectFilter::make('channel')->label('کانال')->options(collect(CustomerNotificationChannel::cases())->mapWithKeys(fn ($channel): array => [$channel->value => CustomerNotificationPresentation::channel($channel)])->all()),
                SelectFilter::make('status')->label('وضعیت')->options(collect(CustomerNotificationStatus::cases())->mapWithKeys(fn ($status): array => [$status->value => CustomerNotificationPresentation::status($status)])->all()),
            ])
            ->recordActions([ViewAction::make()->label('مشاهده')->authorize('view')])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (CustomerNotification $record): string => CustomerNotificationResource::getUrl('view', ['record' => $record]));
    }
}
