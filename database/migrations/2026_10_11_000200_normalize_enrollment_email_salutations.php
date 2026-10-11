<?php

declare(strict_types=1);

use App\Email\EmailTemplateCatalog;
use App\Models\EnrollmentPolicyVersion;
use App\Models\GeneralSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Strips the salutation from stored email intro copy.
 *
 * Enrollment blades render "Dear <student>," as part of the email chrome, and the
 * earlier default presets also opened the custom body with a salutation. Both were
 * rendered, so applicants received a double greeting and the admin preview
 * disagreed with the email. Applying this once makes stored templates match what
 * they will render as.
 */
return new class extends Migration
{
    public const HANDLER = 'enrollment.notify';

    public const CONFIG_KEY = 'email_templates';

    public function up(): void
    {
        $this->normalizePolicyDrafts();
        $this->normalizeSystemTemplates();
    }

    public function down(): void
    {
        // The original salutations are recoverable from the catalog presets, so there
        // is no safe reverse transform worth writing.
    }

    private function normalizePolicyDrafts(): void
    {
        EnrollmentPolicyVersion::query()
            ->where('state', EnrollmentPolicyVersion::Draft)
            ->chunkById(100, function ($versions): void {
                foreach ($versions as $version) {
                    $this->normalizeConfiguration($version);
                }
            });
    }

    private function normalizeSystemTemplates(): void
    {
        $settings = GeneralSetting::query()->first();

        if (! $settings instanceof GeneralSetting) {
            return;
        }

        $configs = is_array($settings->more_configs) ? $settings->more_configs : [];
        $templates = is_array($configs[self::CONFIG_KEY] ?? null) ? $configs[self::CONFIG_KEY] : [];

        $changed = false;

        foreach ($templates as $key => $override) {
            if (! is_array($override) || ! is_string($override['custom_body'] ?? null)) {
                continue;
            }

            $stripped = EmailTemplateCatalog::stripSalutation($override['custom_body']);

            if ($stripped !== $override['custom_body']) {
                $templates[$key]['custom_body'] = $stripped;
                $changed = true;
            }
        }

        if ($changed) {
            $configs[self::CONFIG_KEY] = $templates;
            $settings->update(['more_configs' => $configs]);
        }
    }

    private function normalizeConfiguration(EnrollmentPolicyVersion $version): void
    {
        $configuration = json_decode((string) $version->getRawOriginal('configuration'), true);

        if (! is_array($configuration)) {
            return;
        }

        $steps = data_get($configuration, 'workflow.steps', []);

        if (! is_array($steps)) {
            return;
        }

        $changed = false;

        foreach ($steps as $index => $step) {
            if (! is_array($step) || ! is_array($step['actions'] ?? null)) {
                continue;
            }

            foreach ($step['actions'] as $actionIndex => $action) {
                if (! is_array($action) || ($action['handler'] ?? null) !== self::HANDLER) {
                    continue;
                }

                $body = $action['configuration']['custom_body'] ?? null;

                if (! is_string($body)) {
                    continue;
                }

                $stripped = EmailTemplateCatalog::stripSalutation($body);

                if ($stripped !== $body) {
                    $steps[$index]['actions'][$actionIndex]['configuration']['custom_body'] = $stripped;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $configuration['workflow']['steps'] = $steps;
            $version->update(['configuration' => $configuration]);
        }
    }
};
