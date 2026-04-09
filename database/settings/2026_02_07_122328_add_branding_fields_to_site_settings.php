<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Core application branding
        $this->migrator->add('site.app_name', 'DCCP HUB');
        $this->migrator->add('site.app_short_name', 'DCCP');

        // Organization details
        $this->migrator->add('site.organization_name', 'KoAcademy');
        $this->migrator->add('site.organization_short_name', 'DCCP');
        $this->migrator->add('site.organization_address', '');

        // Contact information
        $this->migrator->add('site.support_email', '');
        $this->migrator->add('site.support_phone', '');

        // Additional branding
        $this->migrator->add('site.tagline', '');
        $this->migrator->add('site.copyright_text', '');

        // Theme
        $this->migrator->add('site.theme_color', '#0f172a');
        $this->migrator->add('site.currency', 'PHP');
    }
};
