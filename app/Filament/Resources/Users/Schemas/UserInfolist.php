<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Support\OrderPresentation;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Support\PaymentPresentation;
use App\Models\Order;
use App\Models\User;
use App\Support\JalaliDate;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('وضعیت حساب')->columns(4)->schema([
                TextEntry::make('name')->label('نام')->weight('bold'),
                TextEntry::make('email')->label('ایمیل')->copyable(),
                TextEntry::make('mobile')->label('شماره تماس')->placeholder('ثبت نشده'),
                TextEntry::make('status')->label('وضعیت حساب')->badge()->formatStateUsing(fn (mixed $state): string => self::statusLabel($state))->color(fn (mixed $state): string => self::statusColor($state)),
                TextEntry::make('email_verified_at')->label('تأیید ایمیل')->state(fn (User $record): string => $record->email_verified_at ? 'تأیید شده' : 'تأیید نشده')->badge()->color(fn (User $record): string => $record->email_verified_at ? 'success' : 'warning'),
                TextEntry::make('mobile_verified_at')->label('تأیید شماره تماس')->state(fn (User $record): string => $record->mobile_verified_at ? 'تأیید شده' : 'تأیید نشده')->badge()->color(fn (User $record): string => $record->mobile_verified_at ? 'success' : 'gray'),
                TextEntry::make('created_at')->label('تاریخ عضویت')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                TextEntry::make('deleted_at')->label('تاریخ حذف')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->placeholder('حذف نشده'),
            ]),
            Section::make('نقش‌ها و دسترسی کلی')->schema([
                TextEntry::make('roles')->label('نقش‌ها')->state(fn (User $record): string => $record->roles->pluck('name')->map(fn (string $role): string => self::roleLabel($role))->implode('، ') ?: 'بدون نقش')->badge(),
            ]),
            Section::make('خلاصه سفارش‌ها')->columns(3)->schema([
                TextEntry::make('orders_count')->label('تعداد کل سفارش‌ها')->state(fn (User $record): string => number_format((int) ($record->orders_count ?? $record->orders()->count()))),
                TextEntry::make('paid_orders_count')->label('سفارش‌های پرداخت‌شده')->state(fn (User $record): string => number_format($record->orders->filter(fn ($order): bool => in_array((string) $order->payment_status?->value, ['paid', 'partially_paid'], true))->count()))->helperText('بر اساس سفارش‌های اخیر نمایش‌داده‌شده محاسبه می‌شود.'),
                TextEntry::make('reconciliation_count')->label('نیازمند تطبیق')->state(fn (User $record): string => number_format($record->orders->flatMap->payments->where('reconciliation_required', true)->count()))->color(fn (User $record): string => $record->orders->flatMap->payments->where('reconciliation_required', true)->isNotEmpty() ? 'danger' : 'gray'),
            ]),
            Section::make('سفارش‌های کاربر')->schema([
                RepeatableEntry::make('orders')
                    ->label('سفارش‌ها')
                    ->table([
                        TableColumn::make('شماره سفارش'),
                        TableColumn::make('وضعیت'),
                        TableColumn::make('وضعیت پرداخت'),
                        TableColumn::make('مبلغ نهایی'),
                        TableColumn::make('تاریخ ثبت'),
                    ])
                    ->schema([
                        TextEntry::make('order_number')->label('شماره سفارش')->url(fn (mixed $state, mixed $record): ?string => $record instanceof Order ? OrderResource::getUrl('view', ['record' => $record]) : null),
                        TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => OrderPresentation::orderStatus($state))->color(fn (mixed $state): string => OrderPresentation::orderStatusColor($state)),
                        TextEntry::make('payment_status')->label('وضعیت پرداخت')->badge()->formatStateUsing(fn (mixed $state): string => OrderPresentation::paymentStatus($state))->color(fn (mixed $state): string => OrderPresentation::paymentStatusColor($state)),
                        TextEntry::make('grand_total')->label('مبلغ نهایی')->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state)),
                        TextEntry::make('created_at')->label('تاریخ ثبت')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                    ]),
            ]),
            Section::make('پرداخت‌های اخیر')->schema([
                RepeatableEntry::make('user_payments')
                    ->label('تلاش‌های پرداخت')
                    ->state(fn (User $record): array => self::recentPayments($record))
                    ->table([
                        TableColumn::make('شماره پرداخت'),
                        TableColumn::make('مبلغ'),
                        TableColumn::make('وضعیت'),
                        TableColumn::make('تطبیق'),
                        TableColumn::make('تاریخ'),
                    ])
                    ->schema([
                        TextEntry::make('payment_number')->label('شماره پرداخت')->url(fn (mixed $state, mixed $record): ?string => is_array($record) && filled($record['payment_id'] ?? null) ? PaymentResource::getUrl('view', ['record' => $record['payment_id']]) : null),
                        TextEntry::make('amount')->label('مبلغ')->formatStateUsing(fn (mixed $state, mixed $record): string => PaymentPresentation::money($state, is_array($record) ? $record['currency'] : null)),
                        TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (mixed $state): string => PaymentPresentation::status($state))->color(fn (mixed $state): string => PaymentPresentation::statusColor($state)),
                        TextEntry::make('reconciliation_required')->label('تطبیق')->badge()->formatStateUsing(fn (mixed $state): string => $state ? 'نیازمند بررسی' : 'موردی نیست')->color(fn (mixed $state): string => $state ? 'danger' : 'gray'),
                        TextEntry::make('created_at')->label('تاریخ')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null),
                    ]),
            ])->collapsible()->collapsed(),
        ]);
    }

    private static function roleLabel(string $role): string
    {
        return match ($role) {
            'super-admin' => 'مدیر ارشد',
            'admin' => 'مدیر',
            default => $role,
        };
    }

    private static function recentPayments(User $user): array
    {
        return $user->orders
            ->flatMap(function ($order) {
                return $order->payments->map(fn ($payment): array => [
                    'payment_number' => $payment->payment_number,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'reconciliation_required' => $payment->reconciliation_required,
                    'verified_at' => $payment->verified_at,
                    'created_at' => $payment->created_at,
                ]);
            })
            ->sortByDesc('created_at')
            ->take(20)
            ->values()
            ->all();
    }

    private static function statusLabel(mixed $status): string
    {
        return match ((string) $status) {
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'banned' => 'مسدود',
            default => 'نامشخص',
        };
    }

    private static function statusColor(mixed $status): string
    {
        return match ((string) $status) {
            'active' => 'success',
            'inactive' => 'warning',
            'banned' => 'danger',
            default => 'gray',
        };
    }
}
