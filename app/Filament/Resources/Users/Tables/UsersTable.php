<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Forms\Components\JalaliDatePicker;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\JalaliDate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['roles:id,name'])->withCount('orders'))
            ->columns([
                TextColumn::make('id')->label('شناسه')->searchable()->sortable()->copyable(),
                TextColumn::make('name')->label('نام')->searchable()->sortable()->weight('bold'),
                TextColumn::make('email')->label('ایمیل')->searchable()->copyable(),
                TextColumn::make('mobile')->label('شماره تماس')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')->label('نقش‌ها')->badge()->formatStateUsing(fn (mixed $state): string => self::roleLabel($state)),
                TextColumn::make('status')->label('وضعیت حساب')->badge()->formatStateUsing(fn (mixed $state): string => self::statusLabel($state))->color(fn (mixed $state): string => self::statusColor($state))->sortable(),
                IconColumn::make('email_verified_at')->label('تأیید ایمیل')->boolean()->state(fn (User $record): bool => $record->email_verified_at !== null)->trueIcon('heroicon-o-check-circle')->trueColor('success')->falseIcon('heroicon-o-minus-circle')->falseColor('gray'),
                TextColumn::make('orders_count')->label('تعداد سفارش‌ها')->badge()->sortable(),
                IconColumn::make('deleted_at')->label('حساب حذف‌شده')->boolean()->state(fn (User $record): bool => $record->trashed())->trueIcon('heroicon-o-trash')->trueColor('danger')->falseIcon('heroicon-o-check-circle')->falseColor('gray'),
                TextColumn::make('created_at')->label('تاریخ عضویت')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->sortable(),
                TextColumn::make('deleted_at')->label('تاریخ حذف')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->label('نقش')->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')->map(fn (string $name): string => self::roleLabel($name))->all())->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['value'] ?? null), fn (Builder $q): Builder => $q->role($data['value']))),
                TrashedFilter::make()->label('وضعیت حذف حساب'),
                SelectFilter::make('status')->label('وضعیت حساب')->options(['active' => 'فعال', 'inactive' => 'غیرفعال', 'banned' => 'مسدود']),
                TernaryFilter::make('email_verified_at')->label('تأیید ایمیل')->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('email_verified_at'),
                    false: fn (Builder $query): Builder => $query->whereNull('email_verified_at'),
                ),
                Filter::make('created_between')->label('بازه تاریخ عضویت')->form([JalaliDatePicker::make('from')->label('از تاریخ'), JalaliDatePicker::make('until')->label('تا تاریخ')])->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
                Filter::make('has_orders')->label('دارای سفارش')->query(fn (Builder $query): Builder => $query->has('orders')),
            ])
            ->recordActions([
                ViewAction::make()->label('مشاهده')->authorize('view'),
                EditAction::make()->label('ویرایش')->authorize('update'),
                DeleteAction::make()->label('حذف حساب')->authorize('delete'),
                RestoreAction::make()->label('بازیابی حساب')->authorize('restore'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]));
    }

    private static function roleLabel(mixed $role): string
    {
        return match ((string) $role) {
            'super-admin' => 'مدیر ارشد',
            'admin' => 'مدیر',
            default => (string) $role,
        };
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
