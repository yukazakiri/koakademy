<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeneralSetting;

final class RegistrarReportingSettingsService
{
    private const string CONFIG_KEY = 'registrar_reporting';

    /** @return array{maximum_year_level: int} */
    public function get(): array
    {
        $settings = GeneralSetting::query()->first();
        $moreConfigs = is_array($settings?->more_configs) ? $settings->more_configs : [];
        $configured = is_array($moreConfigs[self::CONFIG_KEY] ?? null) ? $moreConfigs[self::CONFIG_KEY] : [];

        return [
            'maximum_year_level' => $this->normalizeMaximumYearLevel($configured['maximum_year_level'] ?? null),
        ];
    }

    public function maximumYearLevel(): int
    {
        return $this->get()['maximum_year_level'];
    }

    public function updateMaximumYearLevel(int $maximumYearLevel): void
    {
        $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => config('app.name')]);
        $moreConfigs = is_array($settings->more_configs) ? $settings->more_configs : [];
        $moreConfigs[self::CONFIG_KEY] = [
            'maximum_year_level' => $this->normalizeMaximumYearLevel($maximumYearLevel),
        ];

        $settings->update(['more_configs' => $moreConfigs]);
    }

    private function normalizeMaximumYearLevel(mixed $maximumYearLevel): int
    {
        return max(2, min(7, (int) ($maximumYearLevel ?? 4)));
    }
}
