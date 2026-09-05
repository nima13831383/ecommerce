<?php

namespace App\Filament\Resources\InventoryReservations\Schemas;

use App\Filament\Resources\Inventory\Support\InventoryPresentation;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\InventoryReservation;
use App\Services\Inventory\InventoryService;
use App\Support\JalaliDate;
use App\Support\PersianNumber;
use App\Support\SafeMetadata;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('هشدارهای عملیاتی')->schema([
                RepeatableEntry::make('warnings')->label('موارد نیازمند بررسی')->state(fn (InventoryReservation $record): array => array_map(fn (string $warning): array => ['warning' => $warning], InventoryPresentation::warnings($record)))->schema([
                    TextEntry::make('warning')->label('هشدار')->color('danger')->icon('heroicon-o-exclamation-triangle')->prose(),
                ]),
            ])->visible(fn (InventoryReservation $record): bool => InventoryPresentation::warnings($record) !== []),
            Section::make('مالک موجودی')->columns(3)->schema([
                TextEntry::make('owner')->label('مالک')->state(fn (InventoryReservation $record): string => InventoryPresentation::ownerLabel($record->inventoryOwner))->weight('bold'),
                TextEntry::make('owner_type')->label('نوع مالک')->state(fn (InventoryReservation $record): string => InventoryPresentation::ownerType($record->inventoryOwner)),
                TextEntry::make('owner_id')->label('شناسه مالک')->state(fn (InventoryReservation $record): string => (string) $record->inventory_owner_id)->copyable(),
            ]),
            Section::make('خلاصه موجودی')->columns(4)->schema([
                TextEntry::make('on_hand')->label('موجودی فیزیکی')->state(fn (InventoryReservation $record, InventoryService $inventory): ?string => self::number(InventoryPresentation::stockSummary($record->inventoryOwner, $inventory)['on_hand'])),
                TextEntry::make('reserved')->label('موجودی رزروشده')->state(fn (InventoryReservation $record, InventoryService $inventory): ?string => self::number(InventoryPresentation::stockSummary($record->inventoryOwner, $inventory)['reserved'])),
                TextEntry::make('available')->label('موجودی قابل فروش')->state(fn (InventoryReservation $record, InventoryService $inventory): ?string => self::number(InventoryPresentation::stockSummary($record->inventoryOwner, $inventory)['available'])),
                TextEntry::make('stock_status')->label('وضعیت موجودی')->state(fn (InventoryReservation $record, InventoryService $inventory): string => InventoryPresentation::stockSummary($record->inventoryOwner, $inventory)['status']),
            ]),
            Section::make('چرخه عمر رزرو')->columns(4)->schema([
                TextEntry::make('id')->label('شناسه رزرو')->copyable(),
                TextEntry::make('quantity')->label('مقدار')->numeric(),
                TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => InventoryPresentation::reservationStatus($state))->color(fn (mixed $state): string => InventoryPresentation::reservationStatusColor($state)),
                TextEntry::make('reference_id')->label('شناسه مرجع')->copyable(),
                TextEntry::make('reference_type')->label('نوع مرجع'),
                TextEntry::make('created_at')->label('تاریخ ثبت')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                TextEntry::make('expires_at')->label('تاریخ انقضا')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                TextEntry::make('committed_at')->label('زمان قطعی‌سازی')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->placeholder('ثبت نشده'),
                TextEntry::make('released_at')->label('زمان آزادسازی')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->placeholder('ثبت نشده'),
            ]),
            Section::make('سفارش مرتبط')->columns(4)->schema([
                TextEntry::make('order_number')->label('شماره سفارش')->state(fn (InventoryReservation $record): string => $record->orderItem?->order?->order_number ?? 'یافت نشد')->url(fn (InventoryReservation $record): ?string => $record->orderItem?->order ? OrderResource::getUrl('view', ['record' => $record->orderItem->order]) : null),
                TextEntry::make('order_item')->label('قلم سفارش')->state(fn (InventoryReservation $record): string => $record->orderItem?->product_name ?? 'یافت نشد'),
                TextEntry::make('order_status')->label('وضعیت سفارش')->state(fn (InventoryReservation $record): string => (string) ($record->orderItem?->order?->status?->value ?? 'نامشخص')),
                TextEntry::make('payment_status')->label('وضعیت پرداخت سفارش')->state(fn (InventoryReservation $record): string => (string) ($record->orderItem?->order?->payment_status?->value ?? 'نامشخص')),
            ]),
            Section::make('اطلاعات تکمیلی')->schema([
                TextEntry::make('metadata')->label('متادیتای امن')->state(fn (InventoryReservation $record): string => SafeMetadata::format($record->metadata ?? []))->prose(),
            ])->collapsible()->collapsed(),
        ]);
    }

    private static function number(?int $value): ?string
    {
        return $value === null ? null : PersianNumber::integer($value);
    }
}
