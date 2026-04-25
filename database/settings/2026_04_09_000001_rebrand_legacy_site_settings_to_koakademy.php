<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('site.name', fn (?string $value): string => $this->replaceIfLegacy($value, [
            null,
            '',
            'KoAkademy',
            'KOA Faculty Portal',
        ], 'KoAkademy'));

        $this->migrator->update('site.portal_name', fn (?string $value): string => $this->replaceIfLegacy($value, [
            null,
            '',
            'KoAkademy',
            'KOA Faculty Portal',
        ], 'KoAkademy'));

        $this->migrator->update('site.portal_description', fn (?string $value): string => $this->replaceIfLegacy($value, [
            null,
            '',
            'Divine Child Catholic Parish Faculty Portal - Manage your classes, students, and schedules',
        ], 'KoAkademy portal - manage your classes, students, and schedules'));

        $this->migrator->update('site.app_name', fn (?string $value): string => $this->replaceIfLegacy($value, [
            null,
            '',
            'KoAkademy',
        ], 'KoAkademy'));

        $this->migrator->update('site.app_short_name', fn (?string $value): string => $this->replaceIfLegacy($value, [
            null,
            '',
            'DCCP',
            'HUB',
        ], 'KOA'));

        $this->migrator->update('site.organization_name', fn (?string $value): string => $this->replaceIfLegacy($value, [
            null,
            '',
            'KoAcademy',
        ], 'KoAkademy'));

        $this->migrator->update('site.organization_short_name', fn (?string $value): string => $this->replaceIfLegacy($value, [
            null,
            '',
            'DCCP',
        ], 'KOA'));
    }

    /**
     * @param  array<int, string|null>  $legacyValues
     */
    private function replaceIfLegacy(?string $value, array $legacyValues, string $replacement): string
    {
        if (in_array($value, $legacyValues, true)) {
            return $replacement;
        }

        return (string) $value;
    }
};
