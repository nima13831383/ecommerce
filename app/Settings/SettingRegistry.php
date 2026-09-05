<?php

namespace App\Settings;

use App\Exceptions\UnknownSettingException;
use Illuminate\Validation\Rule;

final class SettingRegistry
{
    /** @return array<string, SettingDefinition> */
    public static function definitions(): array
    {
        return [
            'catalog.products_per_page' => new SettingDefinition(
                key: 'catalog.products_per_page',
                group: 'catalog',
                type: 'integer',
                default: 10,
                label: 'تعداد محصولات در هر صفحه',
                rules: ['required', 'integer', 'min:1', 'max:100'],
                description: 'تعداد محصولات نمایش‌داده‌شده در هر صفحه از آرشیو فروشگاه.',
            ),
            'blog.posts_per_page' => new SettingDefinition(
                key: 'blog.posts_per_page',
                group: 'blog',
                type: 'integer',
                default: 10,
                label: 'تعداد مطالب در هر صفحه',
                rules: ['required', 'integer', 'min:1', 'max:100'],
                description: 'تعداد مطالب نمایش‌داده‌شده در هر صفحه از آرشیو مجله.',
            ),
            'default_tax_class_id' => new SettingDefinition(
                key: 'default_tax_class_id',
                group: 'tax',
                type: 'integer',
                default: null,
                label: 'کلاس مالیاتی پیش‌فرض',
                rules: [
                    'nullable',
                    'integer',
                    Rule::exists('tax_classes', 'id')->where(fn ($query) => $query->where('is_active', true)),
                ],
                nullable: true,
                description: 'کلاس مالیاتی پیش‌فرض برای محصولاتی که کلاس جداگانه ندارند.',
            ),
            'shipping.mode' => new SettingDefinition(
                key: 'shipping.mode',
                group: 'shipping',
                type: 'string',
                default: 'calculator',
                label: 'روش محاسبه هزینه ارسال',
                rules: ['required', Rule::in(['calculator', 'fixed', 'free'])],
                description: 'حالت محاسبه هزینه ارسال فروشگاه.',
                options: ['calculator' => 'محاسبه‌گر اصلی', 'fixed' => 'نرخ ثابت', 'free' => 'ارسال رایگان'],
            ),
            'shipping.origin_province_id' => new SettingDefinition(
                key: 'shipping.origin_province_id',
                group: 'shipping',
                type: 'integer',
                default: null,
                label: 'استان مبدأ',
                rules: ['nullable', 'integer'],
                nullable: true,
                description: 'استان مبدأ واحد و سراسری فروشگاه.',
            ),
            'shipping.origin_city_id' => new SettingDefinition(
                key: 'shipping.origin_city_id',
                group: 'shipping',
                type: 'integer',
                default: null,
                label: 'شهر مبدأ',
                rules: ['nullable', 'integer'],
                nullable: true,
                description: 'شهر مبدأ واحد و سراسری فروشگاه.',
            ),
            'shipping.fixed_rate_amount' => new SettingDefinition(
                key: 'shipping.fixed_rate_amount',
                group: 'shipping',
                type: 'money',
                default: 0,
                label: 'مبلغ نرخ ثابت',
                rules: ['required', 'integer', 'min:0'],
                description: 'مبلغ ثابت ارسال به ریال.',
            ),
            'shipping.packages' => new SettingDefinition(
                key: 'shipping.packages',
                group: 'shipping',
                type: 'json',
                default: [],
                label: 'بسته‌بندی‌ها / کارتن‌ها',
                rules: ['array'],
                description: 'بسته‌بندی‌های فعال مورد استفاده محاسبه‌گر ارسال.',
            ),
            'payment.default_gateway' => new SettingDefinition(
                key: 'payment.default_gateway',
                group: 'payment',
                type: 'string',
                default: null,
                label: 'درگاه پرداخت پیش‌فرض',
                rules: ['nullable', Rule::in(['zarinpal'])],
                nullable: true,
                description: 'درگاه پرداخت فعال فروشگاه. تا زمان تکمیل تنظیمات، پرداخت غیرفعال می‌ماند.',
                options: ['zarinpal' => 'زرین‌پال'],
            ),
            'payment.zarinpal.enabled' => new SettingDefinition(
                key: 'payment.zarinpal.enabled',
                group: 'payment',
                type: 'boolean',
                default: false,
                label: 'فعال بودن زرین‌پال',
                rules: ['required', 'boolean'],
                description: 'فعال‌سازی تنها پس از ثبت مرچنت آیدی معتبر و انتخاب زرین‌پال ممکن است.',
            ),
            'payment.zarinpal.sandbox' => new SettingDefinition(
                key: 'payment.zarinpal.sandbox',
                group: 'payment',
                type: 'boolean',
                default: false,
                label: 'حالت آزمایشی زرین‌پال',
                rules: ['required', 'boolean'],
                description: 'فقط برای محیط توسعه/آزمایش استفاده شود.',
            ),
            'payment.zarinpal.merchant_id' => new SettingDefinition(
                key: 'payment.zarinpal.merchant_id',
                group: 'payment',
                type: 'string',
                default: null,
                label: 'مرچنت آیدی زرین‌پال',
                rules: ['nullable', 'uuid'],
                secret: true,
                nullable: true,
                description: 'اعتبارنامه رمزنگاری‌شده زرین‌پال؛ مقدار فعلی هرگز نمایش داده نمی‌شود.',
            ),
            'auth.customer_auth_mode' => new SettingDefinition(
                key: 'auth.customer_auth_mode',
                group: 'auth',
                type: 'string',
                default: 'email_password',
                label: 'روش ورود و ثبت‌نام',
                rules: ['required', Rule::in(['email_password', 'sms_otp'])],
                description: 'روش فعال ورود و ثبت‌نام مشتریان فروشگاه.',
                options: ['email_password' => 'ایمیل و رمز عبور', 'sms_otp' => 'شماره موبایل و کد تأیید'],
            ),
            'auth.otp.code_length' => new SettingDefinition(
                key: 'auth.otp.code_length',
                group: 'auth',
                type: 'integer',
                default: 5,
                label: 'طول کد تأیید',
                rules: ['required', 'integer', 'min:4', 'max:8'],
                description: 'تعداد رقم کد یک‌بارمصرف پیامکی.',
            ),
            'auth.otp.ttl_seconds' => new SettingDefinition(
                key: 'auth.otp.ttl_seconds',
                group: 'auth',
                type: 'integer',
                default: 120,
                label: 'اعتبار کد تأیید',
                rules: ['required', 'integer', 'min:60', 'max:900'],
                description: 'مدت اعتبار کد یک‌بارمصرف بر حسب ثانیه.',
            ),
            'auth.otp.resend_cooldown_seconds' => new SettingDefinition(
                key: 'auth.otp.resend_cooldown_seconds',
                group: 'auth',
                type: 'integer',
                default: 60,
                label: 'فاصله ارسال مجدد',
                rules: ['required', 'integer', 'min:30', 'max:600'],
                description: 'کمینه فاصله بین درخواست‌های ارسال مجدد کد بر حسب ثانیه.',
            ),
            'auth.otp.max_attempts' => new SettingDefinition(
                key: 'auth.otp.max_attempts',
                group: 'auth',
                type: 'integer',
                default: 5,
                label: 'حداکثر تلاش مجاز',
                rules: ['required', 'integer', 'min:3', 'max:10'],
                description: 'حداکثر تعداد تلاش ناموفق برای هر کد یک‌بارمصرف.',
            ),
            'sms.default_provider' => new SettingDefinition(
                key: 'sms.default_provider',
                group: 'sms',
                type: 'string',
                default: 'smsir',
                label: 'سرویس پیامکی',
                rules: ['required', Rule::in(['smsir'])],
                options: ['smsir' => 'SMS.ir'],
            ),
            'sms.smsir.enabled' => new SettingDefinition(
                key: 'sms.smsir.enabled',
                group: 'sms',
                type: 'boolean',
                default: false,
                label: 'فعال بودن SMS.ir',
                rules: ['required', 'boolean'],
            ),
            'sms.smsir.sandbox' => new SettingDefinition(
                key: 'sms.smsir.sandbox',
                group: 'sms',
                type: 'boolean',
                default: true,
                label: 'حالت Sandbox',
                rules: ['required', 'boolean'],
                description: 'فقط برای محیط توسعه و آزمایش؛ در تولید مجاز نیست.',
            ),
            'sms.smsir.api_key' => new SettingDefinition(
                key: 'sms.smsir.api_key',
                group: 'sms',
                type: 'string',
                default: null,
                label: 'API Key SMS.ir',
                rules: ['nullable', 'string', 'min:16', 'max:512'],
                secret: true,
                nullable: true,
                description: 'اعتبارنامه رمزنگاری‌شده SMS.ir؛ مقدار فعلی هرگز نمایش داده نمی‌شود.',
            ),
            'sms.smsir.verify_template_id' => new SettingDefinition(
                key: 'sms.smsir.verify_template_id',
                group: 'sms',
                type: 'integer',
                default: null,
                label: 'شناسه قالب Verify تولید',
                rules: ['nullable', 'integer', 'min:1', 'max:2147483647'],
                nullable: true,
                description: 'در حالت Sandbox از قالب ثابت ۱۲۳۴۵۶ استفاده می‌شود.',
            ),
            'sms.smsir.verify_parameter_name' => new SettingDefinition(
                key: 'sms.smsir.verify_parameter_name',
                group: 'sms',
                type: 'string',
                default: 'CODE',
                label: 'نام پارامتر کد',
                rules: ['required', 'regex:/^[A-Za-z][A-Za-z0-9_]{0,31}$/'],
                description: 'نام پارامتر قالب Verify در محیط تولید.',
            ),
        ];
    }

    /** @return array<string, SettingDefinition> */
    public static function coreDefinitions(): array
    {
        return array_filter(self::definitions(), static fn (SettingDefinition $definition): bool => $definition->core);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function get(string $key): SettingDefinition
    {
        return self::definitions()[$key]
            ?? throw new UnknownSettingException("Unknown setting key: {$key}");
    }
}
