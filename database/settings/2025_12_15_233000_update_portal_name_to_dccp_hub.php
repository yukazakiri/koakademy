<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('site.portal_name', fn ($name): string => 'DCCP HUB');
        $this->migrator->update('site.name', fn ($name): string => 'DCCP HUB');
    }
};
