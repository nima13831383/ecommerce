<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Forms\Components\JalaliDateTimePicker;
use App\Models\Coupon;
use App\Support\JalaliDate;
use App\Support\PersianNumber;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'کدهای تخفیف';

    protected static ?string $modelLabel = 'کد تخفیف';

    protected static ?string $pluralModelLabel = 'کدهای تخفیف';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات پایه')->columns(2)->schema([
                TextInput::make('code')
                    ->label('کد')
                    ->required()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state): string => Coupon::normalizeCode((string) $state))
                    ->unique(ignoreRecord: true)
                    ->afterStateUpdated(
                        fn (Set $set, ?string $state) => $set('code', strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $state)))
                    )
                    ->live(onBlur: true),

                Toggle::make('is_active')->label('فعال')->default(true)->inline(false),

                Textarea::make('description')
                    ->label('توضیح')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]),

            Section::make('نوع و مقدار تخفیف')->columns(3)->schema([
                Select::make('type')
                    ->label('نوع')
                    ->options([
                        'percent' => 'درصدی',
                        'fixed_cart' => 'مبلغ ثابت روی سبد',
                        'fixed_product' => 'مبلغ ثابت روی محصول',
                    ])
                    ->default('fixed_cart')
                    ->required()
                    ->live(),

                TextInput::make('amount')
                    ->label(fn (callable $get) => $get('type') === 'percent' ? 'درصد تخفیف' : 'مبلغ تخفیف')
                    ->numeric()->integer()->step(1)->required()->default(1)->minValue(1)
                    ->maxValue(fn (callable $get) => $get('type') === 'percent' ? 100 : null)
                    ->suffix(fn (callable $get) => $get('type') === 'percent' ? '%' : 'ریال'),

                TextInput::make('max_discount')
                    ->label('سقف تخفیف')->numeric()->integer()->step(1)->minValue(1)->suffix('ریال')
                    ->visible(fn (callable $get) => $get('type') === 'percent')
                    ->dehydrated(fn (Get $get): bool => $get('type') === 'percent')
                    ->helperText('حداکثر مبلغ تخفیف قابل اعمال'),
            ]),

            Section::make('شرایط سبد')->columns(2)->schema([
                TextInput::make('min_spend')->label('حداقل مبلغ سبد')->numeric()->integer()->step(1)->minValue(0)->suffix('ریال'),
                TextInput::make('max_spend')->label('حداکثر مبلغ سبد')->numeric()->integer()->step(1)->minValue(0)->suffix('ریال'),
            ]),

            Section::make('محدودیت مصرف')->columns(3)->schema([
                TextInput::make('usage_limit')->label('سقف کل مصرف')->numeric()->integer()->step(1)->minValue(1),
                TextInput::make('usage_limit_per_user')->label('سقف مصرف هر کاربر')->numeric()->integer()->step(1)->minValue(1),
                TextInput::make('usage_count')
                    ->label('تعداد مصرف‌شده')->numeric()->default(0)
                    ->disabled()->dehydrated(false),
            ]),

            Section::make('قواعد')->columns(3)->schema([
                Toggle::make('individual_use_only')
                    ->label('فقط به‌تنهایی قابل استفاده')
                    ->helperText('اگر فعال باشد، با هیچ کوپن دیگری ترکیب نمی‌شود.')
                    ->inline(false),
                Toggle::make('exclude_discounted_products')->label('عدم اعمال روی محصولات دارای تخفیف')->inline(false),
            ]),

            Section::make('بازه اعتبار')->columns(2)->schema([
                JalaliDateTimePicker::make('starts_at')->label('شروع')->seconds(false),
                JalaliDateTimePicker::make('expires_at')->label('پایان')->seconds(false)
                    ->after('starts_at'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('کد')
                    ->searchable()->copyable()->weight('bold'),

                TextColumn::make('type')->label('نوع')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'percent' => 'درصدی',
                        'fixed_cart' => 'مبلغ سبد',
                        default => 'مبلغ محصول',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'percent' => 'info',
                        'fixed_cart' => 'success',
                        default => 'warning',
                    }),

                TextColumn::make('amount')->label('مقدار')
                    ->formatStateUsing(fn ($state, Coupon $record) => $record->type === 'percent'
                        ? PersianNumber::percentage($state)
                        : PersianNumber::money($state)),

                TextColumn::make('operational_status')->label('وضعیت عملیاتی')->badge()
                    ->state(fn (Coupon $record): string => self::operationalStatus($record))
                    ->color(fn (Coupon $record): string => self::operationalStatusColor($record)),

                TextColumn::make('users_count')->counts('users')->label('کاربران خاص')
                    ->badge()
                    ->formatStateUsing(fn (?int $state) => ((int) $state) === 0 ? 'عمومی' : $state.' کاربر')
                    ->color(fn (?int $state) => ((int) $state) === 0 ? 'gray' : 'primary'),

                TextColumn::make('usage_count')->label('مصرف')
                    ->formatStateUsing(fn ($state, Coupon $record) => $record->usage_limit
                        ? "{$state}/{$record->usage_limit}"
                        : (string) $state),

                IconColumn::make('individual_use_only')->label('انفرادی')->boolean()->toggleable(),
                IconColumn::make('is_active')->label('فعال')->boolean(),

                TextColumn::make('expires_at')->label('انقضا')
                    ->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('وضعیت فعال بودن'),
                SelectFilter::make('type')->label('نوع')->options([
                    'percent' => 'درصدی',
                    'fixed_cart' => 'مبلغ سبد',
                    'fixed_product' => 'مبلغ محصول',
                ]),
                Filter::make('expired')->label('منقضی‌شده')
                    ->query(fn ($query) => $query->whereNotNull('expires_at')->where('expires_at', '<', now())),
                Filter::make('scheduled')->label('زمان‌بندی‌شده')
                    ->query(fn ($query) => $query->whereNotNull('starts_at')->where('starts_at', '>', now())),
                Filter::make('exhausted')->label('اتمام ظرفیت')
                    ->query(fn ($query) => $query->whereNotNull('usage_limit')->whereColumn('usage_count', '>=', 'usage_limit')),
                Filter::make('user_restricted')->label('اختصاصی کاربران')
                    ->query(fn ($query) => $query->has('users')),
                TrashedFilter::make()->label('وضعیت حذف'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->label('ویرایش')->authorize('update'),
                DeleteAction::make()->label('حذف نرم')->authorize('delete'),
                RestoreAction::make()->label('بازیابی')->authorize('restore'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductsRelationManager::class,
            RelationManagers\UsersRelationManager::class,
            RelationManagers\RolesRelationManager::class,
            RelationManagers\UsagesRelationManager::class,
        ];
    }

    private static function operationalStatus(Coupon $coupon): string
    {
        if (! $coupon->is_active) {
            return 'غیرفعال';
        }

        if ($coupon->starts_at?->isFuture()) {
            return 'زمان‌بندی‌شده';
        }

        if ($coupon->expires_at?->isPast()) {
            return 'منقضی‌شده';
        }

        if ($coupon->hasReachedLimit()) {
            return 'اتمام ظرفیت';
        }

        return 'فعال';
    }

    private static function operationalStatusColor(Coupon $coupon): string
    {
        return match (self::operationalStatus($coupon)) {
            'فعال' => 'success',
            'زمان‌بندی‌شده' => 'info',
            'اتمام ظرفیت' => 'warning',
            'منقضی‌شده' => 'gray',
            default => 'danger',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
