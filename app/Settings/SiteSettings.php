<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Settings;

final class SiteSettings extends Settings
{
    /**
     * Default branding values for backward compatibility.
     * These are used when settings are not configured in the database.
     */
    private const string DEFAULT_APP_NAME = 'KoAkademy';

    private const string DEFAULT_APP_SHORT_NAME = 'KOA';

    private const string DEFAULT_ORG_NAME = 'KoAkademy';

    private const string DEFAULT_ORG_SHORT_NAME = 'KOA';

    private const string DEFAULT_THEME_COLOR = '#0f172a';

    private const string DEFAULT_CURRENCY = 'PHP';

    private const string DEFAULT_AUTH_LAYOUT = 'split';

    private const string DEFAULT_TAGLINE = 'Your Campus, Your Connection';

    // Core site identity
    public ?string $name = null;

    public ?string $description = null;

    public ?string $logo = null;

    public ?string $favicon = null;

    public ?string $og_image = null;

    // Application branding
    public ?string $app_name = null;

    public ?string $app_short_name = null;

    // Organization details
    public ?string $organization_name = null;

    public ?string $organization_short_name = null;

    public ?string $organization_address = null;

    // Contact information
    public ?string $support_email = null;

    public ?string $support_phone = null;

    // Additional branding
    public ?string $tagline = null;

    public ?string $copyright_text = null;

    // Theme settings
    public ?string $theme_color = null;

    public ?string $currency = null;

    public ?string $auth_layout = null;

    // Portal-specific settings
    public ?string $portal_name = null;

    public ?string $portal_description = null;

    public ?string $portal_og_image = null;

    public static function group(): string
    {
        return 'site';
    }

    /**
     * Get the application name with fallback to the default branding.
     */
    public function getAppName(): string
    {
        return $this->app_name ?? $this->name ?? self::DEFAULT_APP_NAME;
    }

    /**
     * Get the short app name with fallback.
     */
    public function getAppShortName(): string
    {
        return $this->app_short_name ?? self::DEFAULT_APP_SHORT_NAME;
    }

    /**
     * Get the organization name with fallback to the default organization name.
     */
    public function getOrganizationName(): string
    {
        return $this->organization_name ?? self::DEFAULT_ORG_NAME;
    }

    /**
     * Get the organization short name with fallback to the default short name.
     */
    public function getOrganizationShortName(): string
    {
        return $this->organization_short_name ?? self::DEFAULT_ORG_SHORT_NAME;
    }

    /**
     * Get the tagline with fallback.
     */
    public function getTagline(): string
    {
        return $this->tagline ?? self::DEFAULT_TAGLINE;
    }

    /**
     * Get the theme color with fallback.
     */
    public function getThemeColor(): string
    {
        return $this->theme_color ?? self::DEFAULT_THEME_COLOR;
    }

    /**
     * Get the currency with fallback.
     */
    public function getCurrency(): string
    {
        return $this->currency ?? self::DEFAULT_CURRENCY;
    }

    public function getAuthLayout(): string
    {
        return $this->auth_layout ?? self::DEFAULT_AUTH_LAYOUT;
    }

    /**
     * Get the support email with fallback.
     */
    public function getSupportEmail(): ?string
    {
        return $this->support_email;
    }

    /**
     * Get the support phone with fallback.
     */
    public function getSupportPhone(): ?string
    {
        return $this->support_phone;
    }

    /**
     * Get the organization address.
     */
    public function getOrganizationAddress(): ?string
    {
        return $this->organization_address;
    }

    /**
     * Get the copyright text with automatic year.
     */
    public function getCopyrightText(): string
    {
        if ($this->copyright_text) {
            return $this->copyright_text;
        }

        $year = date('Y');
        $org = $this->getOrganizationName();

        return "{$year} {$org}. All rights reserved.";
    }

    /**
     * Get the logo URL with fallback.
     */
    public function getLogo(): string
    {
        return $this->resolveAssetUrl($this->logo, '/web-app-manifest-192x192.png');
    }

    /**
     * Get the favicon URL with fallback.
     */
    public function getFavicon(): string
    {
        return $this->resolveAssetUrl($this->favicon, '/web-app-manifest-192x192.png');
    }

    /**
     * Get all branding settings as an array for frontend consumption.
     *
     * @return array<string, mixed>
     */
    public function getBrandingArray(): array
    {
        return [
            'appName' => $this->getAppName(),
            'appShortName' => $this->getAppShortName(),
            'organizationName' => $this->getOrganizationName(),
            'organizationShortName' => $this->getOrganizationShortName(),
            'organizationAddress' => $this->organization_address,
            'supportEmail' => $this->support_email,
            'supportPhone' => $this->support_phone,
            'tagline' => $this->getTagline(),
            'copyrightText' => $this->getCopyrightText(),
            'themeColor' => $this->getThemeColor(),
            'currency' => $this->getCurrency(),
            'authLayout' => $this->getAuthLayout(),
            'logo' => $this->getLogo(),
            'favicon' => $this->getFavicon(),
        ];
    }

    private function resolveAssetUrl(?string $value, string $fallback): string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return $fallback;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        return Storage::url($value);
    }
}
