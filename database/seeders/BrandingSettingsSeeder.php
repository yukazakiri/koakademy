<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Settings\SiteSettings;
use Illuminate\Database\Seeder;

final class BrandingSettingsSeeder extends Seeder
{
    public function run(SiteSettings $settings): void
    {
        // Only set if not already set
        if (! $settings->app_name) {
            $settings->app_name = 'KoAkademy';
        }

        if (! $settings->app_short_name) {
            $settings->app_short_name = 'KOA';
        }

        if (! $settings->organization_name) {
            $settings->organization_name = 'KoAkademy';
        }

        if (! $settings->organization_short_name) {
            $settings->organization_short_name = 'KOA';
        }

        if (! $settings->tagline) {
            $settings->tagline = 'Your Campus, Your Connection';
        }

        if (! $settings->theme_color) {
            $settings->theme_color = '#0f172a';
        }

        if (! $settings->currency) {
            $settings->currency = 'PHP';
        }

        $settings->save();
    }
}
