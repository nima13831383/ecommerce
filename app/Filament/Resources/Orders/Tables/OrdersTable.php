<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Support\OrderPresentation;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['items', 'payments'])
                ->withExists([
                    'payments as has_reconciliation_required_payment' => fn (Builder $paymentQuery): Builder => $paymentQuery->where('reconciliation_required', true),
                ]))
            ->columns([
                TextColumn::make('order_number')
                    ->label('شماره سفارش')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('customer_name')
                    ->label('مشتری')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_mobile')
                    ->label('شماره تماس')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer_email')
                    ->label('ایمیل')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('وضعیت سفارش')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => OrderPresentation::orderStatus($state))
                    ->color(fn (mixed $state): string => OrderPresentation::orderStatusColor($state))
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('وضعیت پرداخت')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => OrderPresentation::paymentStatus($state))
                    ->color(fn (mixed $state): string => OrderPresentation::paymentStatusColor($state))
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('مبلغ نهایی')
                    ->formatStateUsing(fn (mixed $state): string => OrderPresentation::money($state))
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('تعداد اقلام')
                    ->badge()
                    ->sortable(),
                TextColumn::make('payments_count')
                    ->label('تلاش‌های پرداخت')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('has_reconciliation_required_payment')
                    ->label('نیازمند تطبیق')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت سفارش')
                    ->options(self::enumOptions(OrderStatus::cases(), OrderPresentation::orderStatus(...))),
                SelectFilter::make('payment_status')
                    ->label('وضعیت پرداخت')
                    ->options(self::enumOptions(OrderPaymentStatus::cases(), OrderPresentation::paymentStatus(...))),
                Filter::make('reconciliation_required')
                    ->label('نیازمند تطبیق پرداخت')
                    ->query(fn (Builder $query): Builder => $query->whereHas('payments', fn (Builder $paymentQuery): Builder => $paymentQuery->where('reconciliation_required', true))),
                Filter::make('created_between')
                    ->label('بازه تاریخ ثبت')
                    ->form([
                        DatePicker::make('from')->label('از تاریخ'),
                        DatePicker::make('until')->label('تا تاریخ'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()->label('مشاهده')->authorize('view'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]));
    }

    /** @param array<int, \BackedEnum> $cases */
    private static function enumOptions(array $cases, callable $label): array
    {
        return collect($cases)->mapWithKeys(fn (\BackedEnum $case): array => [$case->value => $label($case)])->all();
    }
}
