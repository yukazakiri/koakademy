<?php

declare(strict_types=1);

namespace App\Email;

use App\Models\GeneralSetting;

/**
 * Installation-wide overrides for non-enrollment email templates.
 *
 * Enrollment emails are stored per workflow step on the policy draft because they
 * are scoped to a specific admissions pipeline. Everything else is stored once here,
 * under `more_configs.email_templates`, so one edited template applies everywhere.
 */
final class SystemEmailTemplateStore
{
    public const CONFIG_KEY = 'email_templates';

    /**
     * All stored overrides, keyed by template key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = GeneralSetting::query()->first()?->more_configs[self::CONFIG_KEY] ?? [];

        return is_array($stored) ? $stored : [];
    }

    /**
     * The stored override for one template, or an empty array when untouched.
     *
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        $stored = $this->all()[$key] ?? [];

        return is_array($stored) ? $stored : [];
    }

    /**
     * Merge a partial override into the store for one template.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(string $key, array $values): void
    {
        $settings = $this->settings();
        $configs = $settings->more_configs ?? [];

        $templates = is_array($configs[self::CONFIG_KEY] ?? null) ? $configs[self::CONFIG_KEY] : [];
        $existing = is_array($templates[$key] ?? null) ? $templates[$key] : [];

        // Empty strings mean "revert to the built-in default" rather than "store blank".
        $merged = array_filter([
            ...$existing,
            ...$values,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $templates[$key] = $merged;
        $configs[self::CONFIG_KEY] = $templates;

        $settings->update(['more_configs' => $configs]);
    }

    /**
     * Drop a stored override so the email falls back to its built-in default.
     */
    public function forget(string $key): void
    {
        $settings = GeneralSetting::query()->first();

        if (! $settings instanceof GeneralSetting) {
            return;
        }

        $configs = $settings->more_configs ?? [];
        $templates = is_array($configs[self::CONFIG_KEY] ?? null) ? $configs[self::CONFIG_KEY] : [];

        unset($templates[$key]);
        $configs[self::CONFIG_KEY] = $templates;

        $settings->update(['more_configs' => $configs]);
    }

    /**
     * The single global settings row. Created on demand so a fresh installation can
     * store an override before any other code has created the row.
     */
    private function settings(): GeneralSetting
    {
        $settings = GeneralSetting::query()->first();

        if ($settings instanceof GeneralSetting) {
            return $settings;
        }

        return GeneralSetting::query()->create([
            'site_name' => (string) config('app.name'),
        ]);
    }
}
