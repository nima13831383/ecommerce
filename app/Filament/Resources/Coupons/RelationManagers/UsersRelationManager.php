<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use App\Filament\Resources\Coupons\RelationManagers\Concerns\GuardsCouponTargeting;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersRelationManager extends RelationManager
{
    use GuardsCouponTargeting;

    protected static string $relationship = 'users';

    protected static ?string $title = 'کاربران';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('خالی بودن فهرست = کوپن عمومی است. افزودن کاربر شامل، آن را اختصاصی می‌کند.')
            ->emptyStateHeading('کوپن عمومی است')
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->sortable(),
                TextColumn::make('email')->label('ایمیل')->searchable()->copyable(),
                TextColumn::make('mobile')->label('موبایل')->searchable()->toggleable(),

                IconColumn::make('is_excluded')
                    ->label('وضعیت')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => (bool) $record->pivot->is_excluded)
                    ->trueIcon('heroicon-o-no-symbol')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-badge')
                    ->falseColor('success')
                    ->tooltip(fn ($record): string => $record->pivot->is_excluded ? 'محروم' : 'مجاز'),
            ])
            ->filters([
                TernaryFilter::make('is_excluded')
                    ->label('دسترسی')
                    ->placeholder('همه')
                    ->trueLabel('فقط محروم‌ها')
                    ->falseLabel('فقط مجازها')
                    ->queries(
                        true: fn (Builder $query) => $query->where('coupon_user.is_excluded', true),
                        false: fn (Builder $query) => $query->where('coupon_user.is_excluded', false),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('افزودن کاربر')
                    ->multiple()
                    ->preloadRecordSelect(false)
                    ->recordSelectSearchColumns(['name', 'email', 'mobile'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('is_excluded')
                            ->label('محروم شود')
                            ->helperText('خاموش = کوپن مخصوص این کاربران | روشن = این کاربران اجازه استفاده ندارند')
                            ->default(false),
                    ])
                    ->before(fn (AttachAction $action) => $this->guardActionTargetingMutation($action, 'users')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('ویرایش')
                    ->modalHeading('ویرایش دسترسی کاربر')
                    ->schema([
                        Toggle::make('is_excluded')->label('محروم شود'),
                    ])
                    ->fillForm(fn ($record): array => [
                        'is_excluded' => (bool) $record->pivot->is_excluded,
                    ])
                    ->before(fn (EditAction $action) => $this->guardActionTargetingMutation($action, 'users', (int) $action->getRecord()->getKey())),

                DetachAction::make()->label('حذف'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('حذف انتخاب‌شده‌ها'),
                ]),
            ]);
    }
}
