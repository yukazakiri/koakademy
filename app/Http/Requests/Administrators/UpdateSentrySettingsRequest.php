<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use App\Services\ErrorReportingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSentrySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateObservability', GeneralSetting::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Present only on the probe endpoint; selects which provider to test.
            'provider' => ['nullable', 'string', Rule::in(ErrorReportingService::PROVIDER_KEYS)],
            'providers' => ['required', 'array'],
            'providers.sentry' => ['required', 'array'],
            'providers.sentry.enabled' => ['required', 'boolean'],
            'providers.sentry.dsn' => ['nullable', 'string', 'max:2048'],
            'providers.sentry.environment' => ['nullable', 'string', 'max:64'],
            'providers.sentry.release' => ['nullable', 'string', 'max:255'],
            'providers.sentry.sample_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'providers.sentry.traces_sample_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'providers.sentry.profiles_sample_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'providers.sentry.send_default_pii' => ['required', 'boolean'],
            'providers.sentry.enable_logs' => ['required', 'boolean'],
            'providers.sentry.frontend_enabled' => ['required', 'boolean'],
            'providers.sentry.frontend_dsn' => ['nullable', 'string', 'max:2048'],
            'providers.sentry.frontend_script' => ['nullable', 'string', 'max:20000'],
            'providers.sentry.frontend_traces_sample_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'providers.sentry.frontend_replays_session_sample_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'providers.sentry.frontend_replays_on_error_sample_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'providers.flare' => ['required', 'array'],
            'providers.flare.enabled' => ['required', 'boolean'],
            'providers.flare.api_key' => ['nullable', 'string', 'max:2048'],
            'providers.flare.environment' => ['nullable', 'string', 'max:64'],
            'providers.flare.release' => ['nullable', 'string', 'max:255'],
            'providers.bugsnag' => ['required', 'array'],
            'providers.bugsnag.enabled' => ['required', 'boolean'],
            'providers.bugsnag.api_key' => ['nullable', 'string', 'max:2048'],
            'providers.bugsnag.environment' => ['nullable', 'string', 'max:64'],
            'providers.bugsnag.release' => ['nullable', 'string', 'max:255'],
            'providers.honeybadger' => ['required', 'array'],
            'providers.honeybadger.enabled' => ['required', 'boolean'],
            'providers.honeybadger.api_key' => ['nullable', 'string', 'max:2048'],
            'providers.honeybadger.environment' => ['nullable', 'string', 'max:64'],
            'providers.honeybadger.release' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $providers = $this->input('providers', []);

            if (! is_array($providers)) {
                return;
            }

            $this->validateSentry($validator, $providers['sentry'] ?? []);

            foreach (['flare', 'bugsnag', 'honeybadger'] as $key) {
                $row = $providers[$key] ?? [];

                if (is_array($row) && ($row['enabled'] ?? false) && mb_trim((string) ($row['api_key'] ?? '')) === '') {
                    $label = ucfirst($key);
                    $validator->errors()->add("providers.{$key}.api_key", "An API key is required when {$label} reporting is enabled.");
                }
            }
        });
    }

    private function validateSentry(Validator $validator, mixed $sentry): void
    {
        if (! is_array($sentry) || ! ($sentry['enabled'] ?? false)) {
            return;
        }

        $dsn = mb_trim((string) ($sentry['dsn'] ?? ''));

        if ($dsn === '' && mb_trim((string) env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN', ''))) === '') {
            $validator->errors()->add('providers.sentry.dsn', 'A Sentry DSN is required when error reporting is enabled.');
        }

        if (($sentry['frontend_enabled'] ?? false)) {
            $frontendDsn = mb_trim((string) ($sentry['frontend_dsn'] ?? ''));
            $frontendScript = mb_trim((string) ($sentry['frontend_script'] ?? ''));

            if ($frontendDsn === '' && $frontendScript === '' && $dsn === '') {
                $validator->errors()->add('providers.sentry.frontend_dsn', 'A frontend DSN or a manual browser snippet is required when the browser integration is enabled.');
            }
        }
    }
}
