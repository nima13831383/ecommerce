<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Support\OrderPresentation;
use App\Filament\Resources\Shipments\ShipmentResource;
use App\Filament\Resources\Shipments\Support\ShipmentPresentation;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('هشدارهای عملیاتی')
                ->schema([
                    TextEntry::make('reconciliation_warning')
                        ->label('تطبیق پرداخت')
                        ->state('این سفارش دارای پرداختی است که نیاز به بررسی و تطبیق دستی دارد.')
                        ->color('danger')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->visible(fn (Order $record): bool => $record->payments->contains('reconciliation_required', true)),
                    TextEntry::make('reservation_warning')
                        ->label('پوشش رزرو موجودی')
                        ->state('رزرو موجودی این سفارش منقضی یا ناقص است؛ لغو خودکار انجام نمی‌شود.')
                        ->color('warning')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->visible(fn (Order $record): bool => OrderPresentation::hasReservationWarning($record)),
                ])
                ->visible(fn (Order $record): bool => $record->payments->contains('reconciliation_required', true) || OrderPresentation::hasReservationWarning($record)),
            Section::make('خلاصه سفارش')
                ->columns(4)
                ->schema([
                    TextEntry::make('order_number')->label('شماره سفارش')->copyable()->weight('bold'),
                    TextEntry::make('status')->label('وضعیت سفارش')->badge()
                        ->formatStateUsing(fn (mixed $state): string => OrderPresentation::orderStatus($state))
                        ->color(fn (mixed $state): string => OrderPresentation::orderStatusColor($state)),
                    TextEntry::make('payment_status')->label('وضعیت پرداخت')->badge()
                        ->formatStateUsing(fn (mixed $state): string => OrderPresentation::paymentStatus($state))
                        ->color(fn (mixed $state): string => OrderPresentation::paymentStatusColor($state)),
                    TextEntry::make('currency')->label('ارز'),
                    TextEntry::make('items_subtotal')->label('جمع اقلام')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                    TextEntry::make('discount_total')->label('تخفیف')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                    TextEntry::make('tax_total')->label('مالیات')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                    TextEntry::make('shipping_total')->label('هزینه ارسال')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                    TextEntry::make('grand_total')->label('مبلغ نهایی')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state))->weight('bold'),
                    TextEntry::make('paid_total')->label('پرداخت‌شده')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                    TextEntry::make('created_at')->label('تاریخ ثبت')->dateTime(),
                    TextEntry::make('updated_at')->label('آخرین بروزرسانی')->dateTime(),
                ]),
            Section::make('اطلاعات ارسال')
                ->columns(4)
                ->schema([
                    TextEntry::make('shipment.shipment_number')->label('شماره مرسوله')->placeholder('ایجاد نشده')->url(fn (Order $record): ?string => $record->shipment ? ShipmentResource::getUrl('view', ['record' => $record->shipment]) : null),
                    TextEntry::make('shipment.status')->label('وضعیت مرسوله')->placeholder('ایجاد نشده')->badge()->formatStateUsing(fn (mixed $state): string => ShipmentPresentation::status($state))->color(fn (mixed $state): string => ShipmentPresentation::color($state)),
                    TextEntry::make('shipment.tracking_number')->label('کد رهگیری')->placeholder('ثبت نشده'),
                    TextEntry::make('shipment.shipped_at')->label('زمان ارسال')->dateTime()->placeholder('ثبت نشده'),
                    TextEntry::make('shipment.delivered_at')->label('زمان تحویل')->dateTime()->placeholder('ثبت نشده'),
                ]),
            Section::make('تصویر تاریخی مشتری و آدرس')
                ->columns(2)
                ->schema([
                    TextEntry::make('customer_name')->label('نام مشتری'),
                    TextEntry::make('customer_mobile')->label('شماره تماس'),
                    TextEntry::make('customer_email')->label('ایمیل')->placeholder('ثبت نشده'),
                    TextEntry::make('national_id')->label('کد ملی')->placeholder('ثبت نشده'),
                    TextEntry::make('billing_address')->label('آدرس صورتحساب')->formatStateUsing(fn (mixed $state): string => OrderPresentation::json($state))->prose(),
                    TextEntry::make('shipping_address')->label('آدرس ارسال')->formatStateUsing(fn (mixed $state): string => OrderPresentation::json($state))->prose(),
                    TextEntry::make('user.email')->label('حساب کاربری فعلی')->placeholder('مهمان'),
                ]),
            Section::make('اقلام سفارش و تصاویر تاریخی')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('اقلام')
                        ->state(fn (Order $record): array => $record->items->all())
                        ->table([
                            TableColumn::make('محصول'),
                            TableColumn::make('SKU'),
                            TableColumn::make('ویژگی‌های انتخاب‌شده'),
                            TableColumn::make('تعداد'),
                            TableColumn::make('قیمت واحد'),
                            TableColumn::make('مالیات'),
                            TableColumn::make('مبلغ ردیف'),
                        ])
                        ->schema([
                            TextEntry::make('product_name')->label('محصول')
                                ->formatStateUsing(fn (mixed $state, OrderItem $record): string => $record->product_id === null ? "{$state} (محصول فعلی حذف شده است)" : (string) $state),
                            TextEntry::make('sku')->label('SKU')->placeholder('—'),
                            TextEntry::make('variation_attributes')->label('ویژگی‌ها')->formatStateUsing(fn (mixed $state): string => OrderPresentation::json($state)),
                            TextEntry::make('quantity')->label('تعداد'),
                            TextEntry::make('unit_price')->label('قیمت واحد')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                            TextEntry::make('tax_amount')->label('مالیات')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                            TextEntry::make('line_total')->label('مبلغ ردیف')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                        ]),
                ]),
            Section::make('جزئیات مالیات تاریخی')
                ->schema([
                    RepeatableEntry::make('tax_snapshots')
                        ->label('تصویر مالیات هر قلم')
                        ->state(fn (Order $record): array => $record->items->map(fn (OrderItem $item): array => [
                            'product_name' => $item->product_name,
                            'tax_type' => $item->tax_snapshot['tax_type'] ?? null,
                            'tax_value' => $item->tax_snapshot['tax_value'] ?? null,
                            'taxable_amount' => $item->tax_snapshot['taxable_amount'] ?? null,
                            'tax_amount' => $item->tax_snapshot['tax_amount'] ?? null,
                        ])->all())
                        ->table([
                            TableColumn::make('قلم سفارش'),
                            TableColumn::make('نوع مالیات'),
                            TableColumn::make('نرخ/مقدار'),
                            TableColumn::make('مبلغ مشمول'),
                            TableColumn::make('مبلغ مالیات'),
                        ])
                        ->schema([
                            TextEntry::make('product_name')->label('قلم سفارش'),
                            TextEntry::make('tax_type')->label('نوع مالیات')->placeholder('—'),
                            TextEntry::make('tax_value')->label('نرخ/مقدار')->placeholder('—'),
                            TextEntry::make('taxable_amount')->label('مبلغ مشمول')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                            TextEntry::make('tax_amount')->label('مبلغ مالیات')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                        ]),
                ]),
            Section::make('رزرو موجودی')
                ->columns(2)
                ->schema([
                    TextEntry::make('reservation_coverage')->label('پوشش فعلی')->state(fn (Order $record): string => OrderPresentation::reservationCoverage($record)['label'])->badge()->color(fn (Order $record): string => OrderPresentation::reservationCoverage($record)['color']),
                    RepeatableEntry::make('reservations')
                        ->label('رزروهای مرتبط')
                        ->state(fn (Order $record): array => $record->items->map(function (OrderItem $item): array {
                            $reservation = $item->inventoryReservation;

                            return [
                                'product_name' => $item->product_name,
                                'owner' => $reservation?->inventory_owner_type ? class_basename($reservation->inventory_owner_type) : '—',
                                'quantity' => $reservation?->quantity,
                                'status' => $reservation?->status,
                                'created_at' => $reservation?->created_at,
                                'expires_at' => $reservation?->expires_at,
                                'committed_at' => $reservation?->committed_at,
                                'released_at' => $reservation?->released_at,
                            ];
                        })->all())
                        ->table([
                            TableColumn::make('قلم سفارش'),
                            TableColumn::make('مالک موجودی'),
                            TableColumn::make('تعداد رزرو'),
                            TableColumn::make('وضعیت'),
                            TableColumn::make('ایجاد'),
                            TableColumn::make('انقضا'),
                        ])
                        ->schema([
                            TextEntry::make('product_name')->label('قلم سفارش'),
                            TextEntry::make('owner')->label('مالک موجودی'),
                            TextEntry::make('quantity')->label('تعداد رزرو'),
                            TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => OrderPresentation::reservationStatus($state))->color(fn (mixed $state): string => OrderPresentation::reservationStatusColor($state)),
                            TextEntry::make('created_at')->label('ایجاد')->dateTime()->placeholder('—'),
                            TextEntry::make('expires_at')->label('انقضا')->dateTime()->placeholder('—'),
                        ]),
                ]),
            Section::make('تلاش‌های پرداخت')
                ->schema([
                    RepeatableEntry::make('payments')
                        ->label('پرداخت‌ها')
                        ->state(fn (Order $record): array => $record->payments->all())
                        ->table([
                            TableColumn::make('درگاه'),
                            TableColumn::make('مبلغ'),
                            TableColumn::make('وضعیت'),
                            TableColumn::make('شناسه شروع'),
                            TableColumn::make('شناسه مرجع'),
                            TableColumn::make('تطبیق'),
                            TableColumn::make('تأیید'),
                        ])
                        ->schema([
                            TextEntry::make('gateway')->label('درگاه')->placeholder('—'),
                            TextEntry::make('amount')->label('مبلغ')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                            TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => OrderPresentation::paymentStatus($state))->color(fn (mixed $state): string => OrderPresentation::paymentStatusColor($state)),
                            TextEntry::make('authority')->label('شناسه شروع')->placeholder('—'),
                            TextEntry::make('reference_id')->label('شناسه مرجع')->placeholder('—'),
                            TextEntry::make('reconciliation_required')->label('تطبیق')->badge()->formatStateUsing(fn (mixed $state): string => $state ? 'نیازمند بررسی' : 'نیازی نیست')->color(fn (mixed $state): string => $state ? 'danger' : 'gray'),
                            TextEntry::make('verified_at')->label('تأیید')->dateTime()->placeholder('—'),
                        ]),
                ]),
            Section::make('تاریخچه تراکنش‌های پرداخت')
                ->schema([
                    RepeatableEntry::make('payment_transactions')
                        ->label('تراکنش‌ها')
                        ->state(fn (Order $record): array => $record->payments->flatMap(fn ($payment) => $payment->transactions->map(fn ($transaction): array => [
                            'payment_number' => $payment->payment_number,
                            'type' => $transaction->type,
                            'status' => $transaction->status,
                            'amount' => $transaction->amount,
                            'authority' => $transaction->authority,
                            'reference_id' => $transaction->reference_id,
                            'message' => $transaction->message,
                            'created_at' => $transaction->created_at,
                        ]))->values()->all())
                        ->table([
                            TableColumn::make('شماره پرداخت'),
                            TableColumn::make('نوع'),
                            TableColumn::make('وضعیت'),
                            TableColumn::make('مبلغ'),
                            TableColumn::make('شناسه مرجع'),
                            TableColumn::make('زمان'),
                        ])
                        ->schema([
                            TextEntry::make('payment_number')->label('شماره پرداخت'),
                            TextEntry::make('type')->label('نوع'),
                            TextEntry::make('status')->label('وضعیت'),
                            TextEntry::make('amount')->label('مبلغ')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                            TextEntry::make('reference_id')->label('شناسه مرجع')->placeholder('—'),
                            TextEntry::make('created_at')->label('زمان')->dateTime(),
                        ]),
                ]),
            Section::make('تاریخچه وضعیت سفارش')
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->label('تغییرات وضعیت')
                        ->state(fn (Order $record): array => $record->statusHistories->sortBy('created_at')->all())
                        ->table([
                            TableColumn::make('وضعیت قبلی'),
                            TableColumn::make('وضعیت جدید'),
                            TableColumn::make('توضیح'),
                            TableColumn::make('کاربر'),
                            TableColumn::make('زمان'),
                        ])
                        ->schema([
                            TextEntry::make('from_status')->label('وضعیت قبلی')->placeholder('—')->formatStateUsing(fn (mixed $state): string => $state ? OrderPresentation::orderStatus($state) : '—'),
                            TextEntry::make('to_status')->label('وضعیت جدید')->formatStateUsing(fn (mixed $state): string => OrderPresentation::orderStatus($state)),
                            TextEntry::make('comment')->label('توضیح')->placeholder('—')->prose(),
                            TextEntry::make('user.name')->label('کاربر')->placeholder('سیستم'),
                            TextEntry::make('created_at')->label('زمان')->dateTime(),
                        ]),
                ]),
        ]);
    }
}
