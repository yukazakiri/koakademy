<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.portal_name', 'DCCP Faculty Portal');
        $this->migrator->add('site.portal_description', 'Divine Child Catholic Parish Faculty Portal - Manage your classes, students, and schedules');
        $this->migrator->add('site.portal_og_image', '');
    }
};
