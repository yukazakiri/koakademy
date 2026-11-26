<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\SiteSettings;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class SchoolBrandingService
{
    public function __construct(private SiteSettings $siteSettings) {}

    /** @return array{name: string, address: string, logo: string, logo_embedded: string, tagline: string, email: string, phone: string} */
    public function resolve(): array
    {
        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
        $storedLogo = $settings?->school_portal_logo ?: $this->siteSettings->getLogo();
        $logo = $this->resolveLogo($storedLogo);

        return [
            'name' => $settings?->school_portal_title ?: $settings?->site_name ?: $this->siteSettings->getOrganizationName(),
            'address' => $this->siteSettings->getOrganizationAddress() ?? '',
            'logo' => $logo,
            'logo_embedded' => $this->embedLogo($storedLogo, $logo),
            'tagline' => $this->siteSettings->getTagline(),
            'email' => $settings?->support_email ?: $this->siteSettings->getSupportEmail() ?? '',
            'phone' => $settings?->support_phone ?: $this->siteSettings->getSupportPhone() ?? '',
        ];
    }

    public function resolveLogo(?string $value): string
    {
        if (is_string($value) && mb_trim($value) !== '') {
            if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, '/')) {
                return $value;
            }

            try {
                return Storage::disk('supabase')->url($value);
            } catch (Throwable) {
                try {
                    return Storage::url($value);
                } catch (Throwable) {
                    return asset($value);
                }
            }
        }

        return asset('logo.png');
    }

    private function embedLogo(?string $storedPath, string $resolvedUrl): string
    {
        if (is_string($storedPath) && mb_trim($storedPath) !== '' && ! filter_var($storedPath, FILTER_VALIDATE_URL) && ! str_starts_with($storedPath, '/')) {
            try {
                $contents = Storage::disk('supabase')->get($storedPath);
                $mime = Storage::disk('supabase')->mimeType($storedPath) ?: 'image/png';

                return 'data:'.$mime.';base64,'.base64_encode((string) $contents);
            } catch (Throwable) {
                // The PDF renderer can still load the public URL.
            }
        }

        return $resolvedUrl;
    }
}
