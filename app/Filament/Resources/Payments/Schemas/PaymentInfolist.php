<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Support\OrderPresentation;
use App\Filament\Resources\Payments\Support\PaymentPresentation;
use App\Models\Payment;
use App\Support\JalaliDate;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('هشدارهای عملیاتی')
                ->schema([
                    RepeatableEntry::make('warnings')
                        ->label('موارد نیازمند بررسی')
                        ->state(fn (Payment $record): array => array_map(fn (string $warning): array => ['warning' => $warning], PaymentPresentation::warnings($record)))
                        ->schema([
                            TextEntry::make('warning')
                                ->label('هشدار')
                                ->color('danger')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->prose(),
                        ]),
                ])
                ->visible(fn (Payment $record): bool => PaymentPresentation::warnings($record) !== []),
            Section::make('خلاصه پرداخت')
                ->columns(4)
                ->schema([
                    TextEntry::make('payment_number')->label('شماره پرداخت')->copyable()->weight('bold'),
                    TextEntry::make('status')->label('وضعیت پرداخت')->badge()
                        ->formatStateUsing(fn (mixed $state): string => PaymentPresentation::status($state))
                        ->color(fn (mixed $state): string => PaymentPresentation::statusColor($state)),
                    TextEntry::make('amount')->label('مبلغ')->formatStateUsing(fn (mixed $state, Payment $record): string => PaymentPresentation::money($state, $record->currency)),
                    TextEntry::make('paid_amount')->label('مبلغ تأییدشده')->formatStateUsing(fn (mixed $state, Payment $record): string => PaymentPresentation::money($state, $record->currency)),
                    TextEntry::make('currency')->label('ارز'),
                    TextEntry::make('gateway')->label('شناسه درگاه')->placeholder('ثبت نشده'),
                    TextEntry::make('method')->label('روش پرداخت'),
                    TextEntry::make('authority')->label('شناسه شروع پرداخت')->placeholder('ثبت نشده'),
                    TextEntry::make('reference_id')->label('شماره مرجع تأیید')->placeholder('ثبت نشده'),
                    TextEntry::make('reconciliation_required')->label('تطبیق')->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state ? 'نیازمند بررسی' : 'نیازی نیست')
                        ->color(fn (mixed $state): string => $state ? 'danger' : 'gray'),
                    TextEntry::make('verified_at')->label('زمان تأیید')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->placeholder('ثبت نشده'),
                    TextEntry::make('created_at')->label('زمان ایجاد')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                    TextEntry::make('expires_at')->label('زمان انقضای تلاش')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->placeholder('ثبت نشده'),
                    TextEntry::make('paid_at')->label('زمان پرداخت')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->placeholder('ثبت نشده'),
                ]),
            Section::make('سفارش مرتبط')
                ->columns(4)
                ->schema([
                    TextEntry::make('order.order_number')
                        ->label('شماره سفارش')
                        ->url(fn (Payment $record): string => OrderResource::getUrl('view', ['record' => $record->order_id]))
                        ->copyable(),
                    TextEntry::make('order.customer_name')->label('مشتری'),
                    TextEntry::make('order.customer_mobile')->label('شماره تماس'),
                    TextEntry::make('order.customer_email')->label('ایمیل')->placeholder('ثبت نشده'),
                    TextEntry::make('order.status')->label('وضعیت سفارش')->badge()
                        ->formatStateUsing(fn (mixed $state): string => OrderPresentation::orderStatus($state))
                        ->color(fn (mixed $state): string => OrderPresentation::orderStatusColor($state)),
                    TextEntry::make('order.payment_status')->label('وضعیت پرداخت سفارش')->badge()
                        ->formatStateUsing(fn (mixed $state): string => OrderPresentation::paymentStatus($state))
                        ->color(fn (mixed $state): string => OrderPresentation::paymentStatusColor($state)),
                    TextEntry::make('order.grand_total')->label('مبلغ نهایی سفارش')->formatStateUsing(fn (mixed $state, Payment $record): string => PaymentPresentation::money($state, $record->order?->currency)),
                    TextEntry::make('order.currency')->label('ارز سفارش'),
                ]),
            Section::make('شناسه‌های عملیاتی')
                ->columns(2)
                ->schema([
                    TextEntry::make('initiation_idempotency_key')->label('کلید یکتایی شروع')->placeholder('ثبت نشده')->copyable(),
                ])
                ->collapsible()
                ->collapsed(),
            Section::make('تراکنش‌های پرداخت')
                ->schema([
                    RepeatableEntry::make('transactions')
                        ->label('تراکنش‌ها')
                        ->state(fn (Payment $record): array => $record->transactions->sortBy('created_at')->map(fn ($transaction): array => [
                            'type' => $transaction->type,
                            'status' => $transaction->status,
                            'amount' => $transaction->amount,
                            'authority' => $transaction->authority,
                            'reference_id' => $transaction->reference_id,
                            'gateway_status_code' => $transaction->gateway_status_code,
                            'message' => $transaction->message,
                            'currency' => $record->currency,
                            'metadata' => PaymentPresentation::safeMetadata([
                                'request' => $transaction->request_payload,
                                'response' => $transaction->response_payload,
                            ]),
                            'created_at' => $transaction->created_at,
                        ])->values()->all())
                        ->table([
                            TableColumn::make('تعامل'),
                            TableColumn::make('نتیجه'),
                            TableColumn::make('مبلغ'),
                            TableColumn::make('شناسه شروع'),
                            TableColumn::make('شماره مرجع'),
                            TableColumn::make('زمان'),
                        ])
                        ->schema([
                            TextEntry::make('type')->label('تعامل')->formatStateUsing(fn (mixed $state): string => PaymentPresentation::transactionType($state)),
                            TextEntry::make('status')->label('نتیجه')->badge()->formatStateUsing(fn (mixed $state): string => PaymentPresentation::transactionStatus($state))->color(fn (mixed $state): string => PaymentPresentation::transactionStatusColor($state)),
                            TextEntry::make('amount')->label('مبلغ')->formatStateUsing(fn (mixed $state, mixed $record): string => PaymentPresentation::money($state, is_array($record) ? $record['currency'] : null)),
                            TextEntry::make('authority')->label('شناسه شروع')->placeholder('—'),
                            TextEntry::make('reference_id')->label('شماره مرجع')->placeholder('—'),
                            TextEntry::make('created_at')->label('زمان')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                            TextEntry::make('gateway_status_code')->label('کد پاسخ')->placeholder('—'),
                            TextEntry::make('message')->label('پیام')->placeholder('—')->prose(),
                            TextEntry::make('metadata')->label('متادیتای امن')->prose()->columnSpanFull(),
                        ]),
                ])
                ->collapsible(),
            Section::make('تلاش‌های دیگر سفارش')
                ->schema([
                    RepeatableEntry::make('other_attempts')
                        ->label('پرداخت‌های مرتبط')
                        ->state(fn (Payment $record): array => $record->order?->payments
                            ->where('id', '!=', $record->id)
                            ->map(fn ($payment): array => [
                                'payment_number' => $payment->payment_number,
                                'status' => $payment->status,
                                'amount' => $payment->amount,
                                'currency' => $payment->currency,
                                'gateway' => $payment->gateway,
                                'reconciliation_required' => $payment->reconciliation_required,
                                'verified_at' => $payment->verified_at,
                                'created_at' => $payment->created_at,
                            ])->values()->all() ?? [])
                        ->table([
                            TableColumn::make('شماره پرداخت'),
                            TableColumn::make('وضعیت'),
                            TableColumn::make('مبلغ'),
                            TableColumn::make('درگاه'),
                            TableColumn::make('تطبیق'),
                            TableColumn::make('زمان ایجاد'),
                        ])
                        ->schema([
                            TextEntry::make('payment_number')->label('شماره پرداخت'),
                            TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => PaymentPresentation::status($state))->color(fn (mixed $state): string => PaymentPresentation::statusColor($state)),
                            TextEntry::make('amount')->label('مبلغ')->formatStateUsing(fn (mixed $state, mixed $record): string => PaymentPresentation::money($state, is_array($record) ? $record['currency'] : null)),
                            TextEntry::make('gateway')->label('درگاه')->placeholder('—'),
                            TextEntry::make('reconciliation_required')->label('تطبیق')->formatStateUsing(fn (mixed $state): string => $state ? 'نیازمند بررسی' : 'نیازی نیست')->color(fn (mixed $state): string => $state ? 'danger' : 'gray'),
                            TextEntry::make('created_at')->label('زمان ایجاد')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                        ]),
                ])
                ->collapsible(),
        ]);
    }
}
