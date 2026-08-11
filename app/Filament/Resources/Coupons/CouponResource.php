<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages;
use App\Filament\Resources\Coupons\RelationManagers;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\{BulkActionGroup, DeleteAction, DeleteBulkAction, EditAction};
use Filament\Forms\Components\{TextInput, Textarea, Select, Toggle, DateTimePicker};
use Filament\Resources\Resource;
use Filament\Schemas\Components\{Section, Grid, Utilities\Set};
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\{Filter, SelectFilter, TernaryFilter};
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
                    ->unique(ignoreRecord: true)
                    ->afterStateUpdated(
                        fn(Set $set, ?string $state) =>
                        $set('code', strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $state)))
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
                        'percent'       => 'درصدی',
                        'fixed_cart'    => 'مبلغ ثابت روی سبد',
                        'fixed_product' => 'مبلغ ثابت روی محصول',
                    ])
                    ->default('fixed_cart')
                    ->required()
                    ->live(),

                TextInput::make('amount')
                    ->label(fn(callable $get) => $get('type') === 'percent' ? 'درصد تخفیف' : 'مبلغ تخفیف')
                    ->numeric()->required()->default(0)->minValue(0)
                    ->maxValue(fn(callable $get) => $get('type') === 'percent' ? 100 : null)
                    ->suffix(fn(callable $get) => $get('type') === 'percent' ? '%' : 'ریال'),

                TextInput::make('max_discount')
                    ->label('سقف تخفیف')->numeric()->minValue(0)->suffix('ریال')
                    ->visible(fn(callable $get) => $get('type') === 'percent')
                    ->helperText('حداکثر مبلغ تخفیف قابل اعمال'),
            ]),

            Section::make('شرایط سبد')->columns(2)->schema([
                TextInput::make('min_spend')->label('حداقل مبلغ سبد')->numeric()->minValue(0)->suffix('ریال'),
                TextInput::make('max_spend')->label('حداکثر مبلغ سبد')->numeric()->minValue(0)->suffix('ریال'),
            ]),

            Section::make('محدودیت مصرف')->columns(3)->schema([
                TextInput::make('usage_limit')->label('سقف کل مصرف')->numeric()->minValue(0),
                TextInput::make('usage_limit_per_user')->label('سقف مصرف هر کاربر')->numeric()->minValue(0),
                TextInput::make('usage_count')
                    ->label('تعداد مصرف‌شده')->numeric()->default(0)
                    ->disabled()->dehydrated(false),
            ]),

            Section::make('قواعد')->columns(3)->schema([
                Toggle::make('individual_use_only')
                    ->label('فقط به‌تنهایی قابل استفاده')
                    ->helperText('اگر فعال باشد، با هیچ کوپن دیگری ترکیب نمی‌شود.')
                    ->inline(false),
                Toggle::make('exclude_sale_items')->label('عدم اعمال روی کالای حراجی')->inline(false),
                Toggle::make('free_shipping')->label('ارسال رایگان')->inline(false),
            ]),

            Section::make('بازه اعتبار')->columns(2)->schema([
                DateTimePicker::make('starts_at')->label('شروع')->seconds(false),
                DateTimePicker::make('expires_at')->label('پایان')->seconds(false)
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
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'percent'    => 'درصدی',
                        'fixed_cart' => 'مبلغ سبد',
                        default      => 'مبلغ محصول',
                    })
                    ->color(fn(string $state) => match ($state) {
                        'percent'    => 'info',
                        'fixed_cart' => 'success',
                        default      => 'warning',
                    }),

                TextColumn::make('amount')->label('مقدار')
                    ->formatStateUsing(fn($state, Coupon $record) => $record->type === 'percent'
                        ? "{$state}%"
                        : number_format((float) $state) . ' ریال'),

                TextColumn::make('users_count')->counts('users')->label('کاربران خاص')
                    ->badge()
                    ->formatStateUsing(fn(?int $state) => ((int) $state) === 0 ? 'عمومی' : $state . ' کاربر')
                    ->color(fn(?int $state) => ((int) $state) === 0 ? 'gray' : 'primary'),

                TextColumn::make('usage_count')->label('مصرف')
                    ->formatStateUsing(fn($state, Coupon $record) => $record->usage_limit
                        ? "{$state}/{$record->usage_limit}"
                        : (string) $state),

                IconColumn::make('free_shipping')->label('ارسال رایگان')->boolean()->toggleable(),
                IconColumn::make('individual_use_only')->label('انفرادی')->boolean()->toggleable(),
                IconColumn::make('is_active')->label('فعال')->boolean(),

                TextColumn::make('expires_at')->label('انقضا')
                    ->dateTime('Y/m/d H:i')->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('وضعیت'),
                SelectFilter::make('type')->label('نوع')->options([
                    'percent'       => 'درصدی',
                    'fixed_cart'    => 'مبلغ سبد',
                    'fixed_product' => 'مبلغ محصول',
                ]),
                Filter::make('expired')->label('منقضی‌شده')
                    ->query(fn($query) => $query->whereNotNull('expires_at')->where('expires_at', '<', now())),
                Filter::make('user_restricted')->label('اختصاصی کاربران')
                    ->query(fn($query) => $query->has('users')),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductsRelationManager::class,
            RelationManagers\CategoriesRelationManager::class,
            RelationManagers\UsersRelationManager::class,
        ];
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit'   => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
