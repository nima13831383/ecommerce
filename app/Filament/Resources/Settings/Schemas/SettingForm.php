<?php

namespace App\Filament\Resources\Settings\Schemas;

use App\Models\TaxClass;
use App\Services\Settings\SettingsService;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Settings\SettingRegistry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group')->label('گروه')->disabled()->dehydrated(false),
            TextInput::make('key')->label('کلید داخلی')->disabled()->dehydrated(false),
            TextInput::make('type')->label('نوع مقدار')->disabled()->dehydrated(),
            Select::make('value_string')
                ->label('روش محاسبه هزینه ارسال')
                ->options(SettingRegistry::get('shipping.mode')->options)
                ->visible(fn (Get $get): bool => $get('key') === 'shipping.mode')
                ->required(fn (Get $get): bool => $get('key') === 'shipping.mode'),
            Select::make('value_string')
                ->label('درگاه پرداخت پیش‌فرض')
                ->options(SettingRegistry::get('payment.default_gateway')->options)
                ->placeholder('غیرفعال')
                ->visible(fn (Get $get): bool => $get('key') === 'payment.default_gateway')
                ->nullable()
                ->helperText('برای فعال‌سازی پرداخت، زرین‌پال را انتخاب کنید.'),
            Select::make('value_string')
                ->label('روش ورود و ثبت‌نام')
                ->options(SettingRegistry::get('auth.customer_auth_mode')->options)
                ->visible(fn (Get $get): bool => $get('key') === 'auth.customer_auth_mode')
                ->required(fn (Get $get): bool => $get('key') === 'auth.customer_auth_mode'),
            Select::make('value_string')
                ->label('سرویس پیامکی')
                ->options(SettingRegistry::get('sms.default_provider')->options)
                ->visible(fn (Get $get): bool => $get('key') === 'sms.default_provider')
                ->required(fn (Get $get): bool => $get('key') === 'sms.default_provider'),
            Toggle::make('value_boolean')
                ->label('فعال بودن زرین‌پال')
                ->visible(fn (Get $get): bool => $get('key') === 'payment.zarinpal.enabled')
                ->helperText('فقط پس از انتخاب زرین‌پال و ثبت مرچنت آیدی معتبر فعال می‌شود.'),
            Toggle::make('value_boolean')
                ->label('حالت آزمایشی زرین‌پال')
                ->visible(fn (Get $get): bool => $get('key') === 'payment.zarinpal.sandbox')
                ->helperText('فقط برای محیط توسعه/آزمایش؛ در محیط تولید پذیرفته نمی‌شود.'),
            Toggle::make('value_boolean')
                ->label('فعال بودن SMS.ir')
                ->visible(fn (Get $get): bool => $get('key') === 'sms.smsir.enabled'),
            Toggle::make('value_boolean')
                ->label('حالت Sandbox SMS.ir')
                ->visible(fn (Get $get): bool => $get('key') === 'sms.smsir.sandbox')
                ->helperText('در Sandbox از قالب ثابت با شناسه ۱۲۳۴۵۶ و پارامتر CODE استفاده می‌شود؛ فقط برای توسعه/آزمایش.'),
            TextInput::make('value_secret')
                ->label('مرچنت آیدی زرین‌پال')
                ->password()
                ->revealable()
                ->visible(fn (Get $get): bool => $get('key') === 'payment.zarinpal.merchant_id')
                ->helperText(fn (): string => app(SettingsService::class)->get('payment.zarinpal.merchant_id') !== null
                    ? 'اعتبارنامه تنظیم شده است؛ خالی گذاشتن این فیلد مقدار فعلی را حفظ می‌کند.'
                    : 'مرچنت آیدی معتبر زرین‌پال را وارد کنید.')
                ->rule('uuid')
                ->dehydrated(fn ($state): bool => filled($state))
                ->nullable(),
            TextInput::make('value_secret')
                ->label('API Key SMS.ir')
                ->password()
                ->revealable()
                ->visible(fn (Get $get): bool => $get('key') === 'sms.smsir.api_key')
                ->helperText(fn (): string => app(SettingsService::class)->get('sms.smsir.api_key') !== null
                    ? 'اعتبارنامه تنظیم شده است؛ خالی گذاشتن این فیلد مقدار فعلی را حفظ می‌کند.'
                    : 'در Sandbox از API Key مخصوص Sandbox و در تولید از API Key تولید استفاده کنید.')
                ->dehydrated(fn ($state): bool => filled($state))
                ->nullable(),
            Select::make('value_number')
                ->label('کلاس مالیاتی پیش‌فرض')
                ->visible(fn (Get $get): bool => $get('key') === 'default_tax_class_id')
                ->options(fn (Get $get): array => match ($get('key')) {
                    'default_tax_class_id' => TaxClass::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
                    default => [],
                })
                ->searchable()
                ->nullable()
                ->helperText('این مقدار برای محصولاتی استفاده می‌شود که کلاس مالیاتی جداگانه ندارند.')
                ->required(false),
            Select::make('value_number')
                ->label('استان مبدأ ارسال')
                ->visible(fn (Get $get): bool => $get('key') === 'shipping.origin_province_id')
                ->options(fn (): array => app(WordpressShippingDataLoader::class)->provinces())
                ->searchable()
                ->nullable(),
            Select::make('value_number')
                ->label('شهر مبدأ ارسال')
                ->visible(fn (Get $get): bool => $get('key') === 'shipping.origin_city_id')
                ->options(fn (): array => app(WordpressShippingDataLoader::class)->cities((int) app(SettingsService::class)->get('shipping.origin_province_id', 0)))
                ->searchable()
                ->nullable(),
            TextInput::make('value_number')
                ->label('هزینه ثابت ارسال (ریال)')
                ->visible(fn (Get $get): bool => $get('key') === 'shipping.fixed_rate_amount')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->nullable(),
            TextInput::make('value_number')
                ->label(fn (Get $get): string => match ($get('key')) {
                    'catalog.products_per_page' => 'تعداد محصولات در هر صفحه',
                    'blog.posts_per_page' => 'تعداد مطالب در هر صفحه',
                    default => 'تعداد در هر صفحه',
                })
                ->visible(fn (Get $get): bool => in_array($get('key'), ['catalog.products_per_page', 'blog.posts_per_page'], true))
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(100)
                ->required()
                ->helperText('عدد صحیح بین ۱ تا ۱۰۰.'),
            TextInput::make('value_number')
                ->label(fn (Get $get): string => match ($get('key')) {
                    'auth.otp.code_length' => 'طول کد تأیید',
                    'auth.otp.ttl_seconds' => 'اعتبار کد تأیید (ثانیه)',
                    'auth.otp.resend_cooldown_seconds' => 'فاصله ارسال مجدد (ثانیه)',
                    'auth.otp.max_attempts' => 'حداکثر تلاش مجاز',
                    'sms.smsir.verify_template_id' => 'شناسه قالب Verify تولید',
                    default => 'عدد صحیح',
                })
                ->visible(fn (Get $get): bool => in_array($get('key'), [
                    'auth.otp.code_length',
                    'auth.otp.ttl_seconds',
                    'auth.otp.resend_cooldown_seconds',
                    'auth.otp.max_attempts',
                    'sms.smsir.verify_template_id',
                ], true))
                ->numeric()
                ->integer()
                ->nullable()
                ->helperText(fn (Get $get): ?string => $get('key') === 'sms.smsir.verify_template_id'
                    ? 'در حالت Sandbox این مقدار ذخیره می‌شود اما غیرفعال است.'
                    : null),
            TextInput::make('value_string')
                ->label('نام پارامتر کد Verify')
                ->visible(fn (Get $get): bool => $get('key') === 'sms.smsir.verify_parameter_name')
                ->required(fn (Get $get): bool => $get('key') === 'sms.smsir.verify_parameter_name')
                ->helperText('در Sandbox پارامتر CODE به‌صورت ثابت استفاده می‌شود.'),
            Textarea::make('value_json')
                ->label('بسته‌بندی‌ها / کارتن‌ها (JSON)')
                ->visible(fn (Get $get): bool => $get('key') === 'shipping.packages')
                ->helperText('هر بسته باید id، name، capacity_volume، max_weight، code و active داشته باشد.'),
        ])->columns(2);
    }
}
