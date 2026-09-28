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
    private const string DEFAULT_APP_NAME = 'Portal';

    private const string DEFAULT_APP_SHORT_NAME = 'PORTAL';

    private const string DEFAULT_ORG_NAME = 'Academic Portal';

    private const string DEFAULT_ORG_SHORT_NAME = 'PORTAL';

    private const string DEFAULT_THEME_COLOR = '#0f172a';

    private const string DEFAULT_CURRENCY = 'PHP';

    private const string DEFAULT_AUTH_LAYOUT = 'split';

    private const string DEFAULT_TAGLINE = 'Your Campus, Your Connection';

    private const string DEFAULT_COUNTRY_CODE = '+63';

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

    public ?string $auth_layout = self::DEFAULT_AUTH_LAYOUT;

    // Regional defaults
    public ?string $default_country_code = null;

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
        $name = $this->app_name ?? $this->name;

        if (is_string($name) && mb_trim($name) !== '') {
            return mb_trim($name);
        }

        $configName = (string) config('app.name');

        return $configName !== '' ? $configName : self::DEFAULT_APP_NAME;
    }

    public function getPortalName(): string
    {
        $portalName = $this->portal_name;

        if (! is_string($portalName) || mb_trim($portalName) === '') {
            return $this->getAppName();
        }

        $trimmedPortalName = mb_trim($portalName);

        return $trimmedPortalName;
    }

    /**
     * Get the short app name with fallback.
     */
    public function getAppShortName(): string
    {
        if (is_string($this->app_short_name) && mb_trim($this->app_short_name) !== '') {
            return mb_trim($this->app_short_name);
        }

        $appName = $this->getAppName();

        return mb_strtoupper((string) str($appName)->limit(4, ''));
    }

    /**
     * Get the organization name with fallback to the default organization name.
     */
    public function getOrganizationName(): string
    {
        if (is_string($this->organization_name) && mb_trim($this->organization_name) !== '') {
            return mb_trim($this->organization_name);
        }

        return $this->getAppName();
    }

    /**
     * Get the organization short name with fallback to the default short name.
     */
    public function getOrganizationShortName(): string
    {
        if (is_string($this->organization_short_name) && mb_trim($this->organization_short_name) !== '') {
            return mb_trim($this->organization_short_name);
        }

        return $this->getAppShortName();
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
     * Get the default country calling code with fallback.
     */
    public function getDefaultCountryCode(): string
    {
        return $this->default_country_code ?? self::DEFAULT_COUNTRY_CODE;
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
        return $this->resolveAssetUrl($this->logo, '/logo.png');
    }

    /**
     * Get the favicon URL with fallback.
     */
    public function getFavicon(): string
    {
        return $this->resolveAssetUrl($this->favicon, '/logo.png');
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
            'defaultCountryCode' => $this->getDefaultCountryCode(),
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
