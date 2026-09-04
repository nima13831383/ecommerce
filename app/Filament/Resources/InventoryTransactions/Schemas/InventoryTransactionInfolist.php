<?php

namespace App\Filament\Resources\InventoryTransactions\Schemas;

use App\Filament\Resources\Inventory\Support\InventoryPresentation;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\InventoryTransaction;
use App\Services\Inventory\InventoryService;
use App\Support\JalaliDate;
use App\Support\SafeMetadata;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('مالک و موجودی جاری')->columns(4)->schema([
                TextEntry::make('owner')->label('مالک')->state(fn (InventoryTransaction $record): string => InventoryPresentation::ownerLabel($record->inventoryOwner))->weight('bold'),
                TextEntry::make('owner_type')->label('نوع مالک')->state(fn (InventoryTransaction $record): string => InventoryPresentation::ownerType($record->inventoryOwner)),
                TextEntry::make('on_hand')->label('موجودی فیزیکی')->state(fn (InventoryTransaction $record, InventoryService $inventory): ?string => self::number(InventoryPresentation::stockSummary($record->inventoryOwner, $inventory)['on_hand'])),
                TextEntry::make('available')->label('موجودی قابل فروش')->state(fn (InventoryTransaction $record, InventoryService $inventory): ?string => self::number(InventoryPresentation::stockSummary($record->inventoryOwner, $inventory)['available'])),
            ]),
            Section::make('جزئیات تراکنش')->columns(4)->schema([
                TextEntry::make('id')->label('شناسه تراکنش')->copyable(),
                TextEntry::make('operation')->label('نوع عملیات')->badge()->formatStateUsing(fn (mixed $state): string => InventoryPresentation::operation($state))->color(fn (mixed $state): string => InventoryPresentation::operationColor($state)),
                TextEntry::make('quantity_delta')->label('مقدار تغییر')->state(fn (InventoryTransaction $record): string => InventoryPresentation::delta($record->quantity_delta)),
                TextEntry::make('quantity_before')->label('موجودی قبل')->numeric(),
                TextEntry::make('quantity_after')->label('موجودی بعد')->numeric(),
                TextEntry::make('reference_type')->label('نوع مرجع'),
                TextEntry::make('reference_id')->label('شناسه مرجع')->copyable(),
                TextEntry::make('reason')->label('دلیل')->placeholder('ثبت نشده')->prose(),
                TextEntry::make('createdBy.name')->label('عامل ثبت‌کننده')->placeholder('سیستم یا نامشخص'),
                TextEntry::make('created_at')->label('تاریخ ثبت')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
            ]),
            Section::make('سفارش مرتبط')->schema([
                TextEntry::make('order_number')->label('شماره سفارش')->state(fn (InventoryTransaction $record): string => $record->inventoryReservation?->orderItem?->order?->order_number ?? 'یافت نشد')->url(fn (InventoryTransaction $record): ?string => $record->inventoryReservation?->orderItem?->order ? OrderResource::getUrl('view', ['record' => $record->inventoryReservation->orderItem->order]) : null),
            ]),
            Section::make('متادیتای امن')->schema([
                TextEntry::make('metadata')->label('متادیتا')->state(fn (InventoryTransaction $record): string => SafeMetadata::format($record->metadata ?? []))->prose(),
            ])->collapsible()->collapsed(),
        ]);
    }

    private static function number(?int $value): ?string
    {
        return $value === null ? null : number_format($value);
    }
}
