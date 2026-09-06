<?php

namespace App\Services\Storefront;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StorefrontBranding
{
    private const FALLBACK_LOGO = 'storefront/luxira-icon.png';

    public function __construct(private readonly SettingsService $settings) {}

    public function logoUrl(): string
    {
        $path = $this->settings->get('branding.logo_path');

        if (! is_string($path) || blank($path)) {
            return asset(self::FALLBACK_LOGO);
        }

        $path = trim(str_replace('\\', '/', $path));

        if (Str::startsWith($path, '/') || Str::contains($path, '..') || ! Storage::disk('public')->exists($path)) {
            return asset(self::FALLBACK_LOGO);
        }

        return Storage::disk('public')->url($path);
    }
}
