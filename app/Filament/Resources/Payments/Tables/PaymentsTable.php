<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Filament\Forms\Components\JalaliDatePicker;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Support\OrderPresentation;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Support\PaymentPresentation;
use App\Models\Payment;
use App\Support\JalaliDate;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'order:id,order_number,customer_name,customer_mobile,customer_email,status,payment_status,grand_total,currency',
            ]))
            ->columns([
                TextColumn::make('payment_number')
                    ->label('شماره پرداخت')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('order.order_number')
                    ->label('سفارش مرتبط')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Payment $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('order.customer_name')
                    ->label('مشتری')
                    ->searchable(),
                TextColumn::make('order.customer_mobile')
                    ->label('شماره تماس')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order.customer_email')
                    ->label('ایمیل')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gateway')
                    ->label('شناسه درگاه')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('مبلغ')
                    ->formatStateUsing(fn (mixed $state, Payment $record): string => PaymentPresentation::money($state, $record->currency))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('وضعیت پرداخت')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => PaymentPresentation::status($state))
                    ->color(fn (mixed $state): string => PaymentPresentation::statusColor($state))
                    ->sortable(),
                TextColumn::make('authority')
                    ->label('شناسه شروع پرداخت')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_id')
                    ->label('شماره مرجع')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('reconciliation_required')
                    ->label('نیاز به تطبیق')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('gray'),
                TextColumn::make('verified_at')
                    ->label('زمان تأیید')
                    ->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت پرداخت')
                    ->options(self::enumOptions(PaymentStatus::cases(), PaymentPresentation::status(...))),
                Filter::make('gateway')
                    ->label('شناسه درگاه')
                    ->form([TextInput::make('value')->label('شناسه درگاه')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where('gateway', $data['value']),
                    )),
                TernaryFilter::make('reconciliation_required')->label('نیاز به تطبیق'),
                TernaryFilter::make('verified_at')
                    ->label('وضعیت تأیید')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('verified_at'),
                    ),
                Filter::make('created_between')
                    ->label('بازه تاریخ ثبت')
                    ->form([
                        JalaliDatePicker::make('from')->label('از تاریخ'),
                        JalaliDatePicker::make('until')->label('تا تاریخ'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
                Filter::make('verified_between')
                    ->label('بازه زمان تأیید')
                    ->form([
                        JalaliDatePicker::make('from')->label('از تاریخ'),
                        JalaliDatePicker::make('until')->label('تا تاریخ'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('verified_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('verified_at', '<=', $date))),
                SelectFilter::make('order_payment_status')
                    ->label('وضعیت پرداخت سفارش')
                    ->options(self::enumOptions(OrderPaymentStatus::cases(), OrderPresentation::paymentStatus(...)))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas('order', fn (Builder $orderQuery): Builder => $orderQuery->where('payment_status', $data['value'])),
                    )),
            ])
            ->recordActions([
                ViewAction::make()->label('مشاهده')->authorize('view'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]));
    }

    /** @param array<int, \BackedEnum> $cases */
    private static function enumOptions(array $cases, callable $label): array
    {
        return collect($cases)->mapWithKeys(fn (\BackedEnum $case): array => [$case->value => $label($case)])->all();
    }
}
