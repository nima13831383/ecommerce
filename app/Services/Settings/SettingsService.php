<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentGatewayConfiguration;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\ShippingOptionCatalog;
use App\Services\Sms\SmsGatewayConfiguration;
use App\Settings\SettingDefinition;
use App\Settings\SettingRegistry;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    public function __construct(
        private readonly WordpressShippingDataLoader $geography,
        private readonly ShippingOptionCatalog $shippingOptions,
    ) {}

    public function get(string $key, mixed $default = null, ?string $group = null): mixed
    {
        $definition = SettingRegistry::get($key);
        $setting = Setting::query()
            ->where('key', $definition->key)
            ->where('group', $group ?? $definition->group)
            ->first();

        if ($setting !== null) {
            return $this->deserializeValue($definition, $setting->typed_value);
        }

        return $default ?? $definition->default;
    }

    public function getDefinition(string $key): SettingDefinition
    {
        return SettingRegistry::get($key);
    }

    public function update(string $key, mixed $value, ?User $actor = null): Setting
    {
        $definition = SettingRegistry::get($key);
        $normalized = $this->normalize($definition, $value);
        $previousMode = $key === 'shipping.mode' ? $this->get('shipping.mode') : null;

        $setting = DB::transaction(function () use ($definition, $normalized, $previousMode): Setting {
            $this->assertPaymentSetting($definition->key, $normalized);

            $setting = Setting::query()->updateOrCreate(
                ['group' => $definition->group, 'key' => $definition->key],
                [
                    'value' => $this->serializeValue($definition, $normalized),
                    'type' => $definition->type,
                    'is_public' => false,
                ],
            );

            if ($definition->key === 'shipping.mode' && $normalized === 'calculator' && $previousMode !== 'calculator') {
                $this->assertCalculatorConfiguration();
            }

            return $setting;
        });

        Log::info('settings.updated', [
            'actor_user_id' => $actor?->getKey() ?? auth()->id(),
            'setting_key' => $definition->key,
            'group' => $definition->group,
        ]);

        return $setting;
    }

    /**
     * Ensure every registered core key has a row without changing existing values.
     *
     * @return array{added: array<int, string>, existing: array<int, string>}
     */
    public function sync(bool $dryRun = false): array
    {
        $added = [];
        $existing = [];
        $now = now();

        foreach (SettingRegistry::coreDefinitions() as $definition) {
            $query = Setting::query()
                ->where('group', $definition->group)
                ->where('key', $definition->key);

            if ($query->exists()) {
                $existing[] = $definition->key;

                continue;
            }

            $added[] = $definition->key;

            if (! $dryRun) {
                Setting::query()->insertOrIgnore([
                    'group' => $definition->group,
                    'key' => $definition->key,
                    'value' => $this->serializeValue($definition, $definition->default),
                    'type' => $definition->type,
                    'is_public' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return ['added' => $added, 'existing' => $existing];
    }

    /**
     * @return array{registered: int, persisted: int, missing: array<int, string>, unknown: array<int, array{group: string, key: string}>, needs_configuration: array<int, string>, payment: array{default_gateway: string, enabled: bool, sandbox: bool, merchant_configured: bool, merchant_valid: bool, operational: bool}, sms: array{provider: string, enabled: bool, sandbox: bool, api_key_configured: bool, template_id: int|null, auth_mode: string, operational: bool}}
     */
    public function status(): array
    {
        $definitions = SettingRegistry::coreDefinitions();
        $rows = Setting::query()->get(['group', 'key']);
        $persistedKeys = [];

        foreach ($rows as $row) {
            $persistedKeys[$row->group.':'.$row->key] = true;
        }

        $missing = [];
        foreach ($definitions as $definition) {
            if (! isset($persistedKeys[$definition->group.':'.$definition->key])) {
                $missing[] = $definition->key;
            }
        }

        $unknown = $rows
            ->reject(fn (Setting $row): bool => SettingRegistry::has($row->key)
                && SettingRegistry::get($row->key)->group === $row->group)
            ->map(fn (Setting $row): array => ['group' => $row->group, 'key' => $row->key])
            ->values()
            ->all();

        $needsConfiguration = [];
        if ($this->get('shipping.mode') === 'calculator') {
            if ($this->get('shipping.origin_province_id') === null) {
                $needsConfiguration[] = 'shipping.origin_province_id';
            }
            if ($this->get('shipping.origin_city_id') === null) {
                $needsConfiguration[] = 'shipping.origin_city_id';
            }
            if ($this->get('shipping.packages') === []) {
                $needsConfiguration[] = 'shipping.packages';
            }
        }

        if ($this->get('payment.default_gateway') === 'zarinpal' && $this->get('payment.zarinpal.enabled') === true) {
            $merchantId = $this->get('payment.zarinpal.merchant_id');

            if (! PaymentGatewayConfiguration::validMerchantId($merchantId)) {
                $needsConfiguration[] = 'payment.zarinpal.merchant_id';
            }

            if (app()->isProduction() && $this->get('payment.zarinpal.sandbox') === true) {
                $needsConfiguration[] = 'payment.zarinpal.sandbox';
            }
        }

        $authMode = $this->get('auth.customer_auth_mode');
        $smsConfiguration = app(SmsGatewayConfiguration::class);
        if ($authMode === 'sms_otp' && ! $smsConfiguration->operational()) {
            $needsConfiguration[] = 'sms.customer_authentication';
        }

        $merchantId = $this->get('payment.zarinpal.merchant_id');
        $payment = [
            'default_gateway' => $this->get('payment.default_gateway') ?? 'not configured',
            'enabled' => $this->get('payment.zarinpal.enabled') === true,
            'sandbox' => $this->get('payment.zarinpal.sandbox') === true,
            'merchant_configured' => $merchantId !== null,
            'merchant_valid' => PaymentGatewayConfiguration::validMerchantId($merchantId),
            'operational' => app(PaymentGatewayConfiguration::class)->zarinPal() !== null,
        ];
        $sandbox = $this->get('sms.smsir.sandbox') === true;
        $sms = [
            'provider' => $this->get('sms.default_provider') ?? 'not configured',
            'enabled' => $this->get('sms.smsir.enabled') === true,
            'sandbox' => $sandbox,
            'api_key_configured' => filled($this->get('sms.smsir.api_key')),
            'template_id' => $sandbox ? SmsGatewayConfiguration::SANDBOX_TEMPLATE_ID : $this->get('sms.smsir.verify_template_id'),
            'auth_mode' => $authMode,
            'operational' => $smsConfiguration->operational(),
        ];

        return [
            'registered' => count($definitions),
            'persisted' => count(array_filter($definitions, fn (SettingDefinition $definition): bool => isset($persistedKeys[$definition->group.':'.$definition->key]))),
            'missing' => $missing,
            'unknown' => $unknown,
            'needs_configuration' => $needsConfiguration,
            'payment' => $payment,
            'sms' => $sms,
        ];
    }

    public function canManage(Setting $setting): bool
    {
        return SettingRegistry::has($setting->key)
            && SettingRegistry::get($setting->key)->group === $setting->group;
    }

    private function normalize(SettingDefinition $definition, mixed $value): mixed
    {
        $validated = Validator::make(
            ['value' => $value],
            ['value' => $definition->rules],
        )->validate()['value'];

        $normalized = match ($definition->type) {
            'integer', 'money' => $validated === null ? null : (int) $validated,
            'boolean' => (bool) $validated,
            'json' => $validated,
            default => is_string($validated) ? trim($validated) : $validated,
        };

        if ($definition->key === 'payment.zarinpal.merchant_id' && $normalized !== null) {
            $normalized = strtolower($normalized);
        }

        $this->assertShippingSetting($definition->key, $normalized);
        $this->assertSmsSetting($definition->key, $normalized);

        return $normalized;
    }

    private function assertCalculatorConfiguration(): void
    {
        $province = $this->get('shipping.origin_province_id');
        $city = $this->get('shipping.origin_city_id');
        $packages = $this->get('shipping.packages', []);

        if ($province === null || $city === null || $packages === []) {
            throw ValidationException::withMessages([
                'value' => 'برای حالت محاسبه‌گر، استان، شهر و حداقل یک بسته‌بندی باید تنظیم شود.',
            ]);
        }
    }

    private function serializeValue(SettingDefinition $definition, mixed $value): ?string
    {
        if ($definition->secret && $value !== null) {
            return Crypt::encryptString((string) $value);
        }

        return match (true) {
            $value === null => null,
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private function deserializeValue(SettingDefinition $definition, mixed $value): mixed
    {
        if (! $definition->secret || $value === null) {
            return $value;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            Log::error('settings.secret_decryption_failed', ['setting_key' => $definition->key]);

            return null;
        }
    }

    private function assertShippingSetting(string $key, mixed $value): void
    {
        if ($key === 'shipping.origin_province_id' && $value !== null && $this->geography->provinceName((int) $value) === null) {
            throw ValidationException::withMessages(['value' => 'استان مبدأ ارسال نامعتبر است.']);
        }

        if ($key === 'shipping.origin_city_id' && $value !== null) {
            $province = (int) $this->get('shipping.origin_province_id', 0);
            if (! $this->geography->cityBelongsToProvince((int) $value, $province)) {
                throw ValidationException::withMessages(['value' => 'شهر مبدأ با استان مبدأ همخوانی ندارد.']);
            }
        }

        if ($key !== 'shipping.packages') {
            return;
        }

        $codes = array_keys($this->shippingOptions->packageSizes());
        foreach ($value as $package) {
            if (! is_array($package) || blank($package['id'] ?? null) || blank($package['name'] ?? null) || ! is_numeric($package['capacity_volume'] ?? null) || (float) $package['capacity_volume'] <= 0 || ! is_numeric($package['max_weight'] ?? null) || (float) $package['max_weight'] <= 0 || ! in_array((int) ($package['code'] ?? 0), $codes, true)) {
                throw ValidationException::withMessages(['value' => 'تنظیم بسته‌بندی نامعتبر است.']);
            }
        }
    }

    private function assertPaymentSetting(string $key, mixed $value): void
    {
        if (! str_starts_with($key, 'payment.')) {
            return;
        }

        if ($key === 'payment.zarinpal.sandbox' && $value === true && app()->isProduction()) {
            throw ValidationException::withMessages(['value' => 'حالت آزمایشی زرین‌پال در محیط تولید مجاز نیست.']);
        }

        $defaultGateway = $key === 'payment.default_gateway' ? $value : $this->get('payment.default_gateway');
        $enabled = $key === 'payment.zarinpal.enabled' ? $value : $this->get('payment.zarinpal.enabled');
        $merchantId = $key === 'payment.zarinpal.merchant_id' ? $value : $this->get('payment.zarinpal.merchant_id');

        if ($enabled !== true) {
            return;
        }

        if ($defaultGateway !== 'zarinpal') {
            throw ValidationException::withMessages(['value' => 'برای فعال‌سازی زرین‌پال، درگاه پیش‌فرض باید زرین‌پال باشد.']);
        }

        if (! PaymentGatewayConfiguration::validMerchantId($merchantId)) {
            throw ValidationException::withMessages(['value' => 'برای فعال‌سازی زرین‌پال، مرچنت آیدی معتبر لازم است.']);
        }
    }

    private function assertSmsSetting(string $key, mixed $value): void
    {
        if (! str_starts_with($key, 'sms.') && $key !== 'auth.customer_auth_mode') {
            return;
        }

        if ($key === 'sms.smsir.sandbox' && $value === true && app()->isProduction()) {
            throw ValidationException::withMessages(['value' => 'حالت Sandbox پیامک در محیط تولید مجاز نیست.']);
        }

        $mode = $key === 'auth.customer_auth_mode' ? $value : $this->get('auth.customer_auth_mode');
        if ($mode !== 'sms_otp') {
            return;
        }

        $provider = $key === 'sms.default_provider' ? $value : $this->get('sms.default_provider');
        $enabled = $key === 'sms.smsir.enabled' ? $value : $this->get('sms.smsir.enabled');
        $sandbox = $key === 'sms.smsir.sandbox' ? $value : $this->get('sms.smsir.sandbox');
        $apiKey = $key === 'sms.smsir.api_key' ? $value : $this->get('sms.smsir.api_key');
        $templateId = $key === 'sms.smsir.verify_template_id' ? $value : $this->get('sms.smsir.verify_template_id');
        $parameter = $key === 'sms.smsir.verify_parameter_name' ? $value : $this->get('sms.smsir.verify_parameter_name');

        if ($provider !== 'smsir' || $enabled !== true || ! is_string($apiKey) || blank($apiKey)) {
            throw ValidationException::withMessages(['value' => 'برای فعال‌سازی ورود پیامکی، SMS.ir باید با API Key معتبر فعال باشد.']);
        }

        if ($sandbox === true) {
            if (app()->isProduction()) {
                throw ValidationException::withMessages(['value' => 'ورود پیامکی Sandbox در محیط تولید مجاز نیست.']);
            }

            return;
        }

        if (! is_int($templateId) || $templateId < 1 || ! is_string($parameter) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,31}$/', $parameter)) {
            throw ValidationException::withMessages(['value' => 'قالب Verify و نام پارامتر تولید برای ورود پیامکی لازم است.']);
        }
    }
}
