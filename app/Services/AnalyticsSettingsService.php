<?php

declare(strict_types=1);

namespace App\Services;

final readonly class AnalyticsSettingsService
{
    public function __construct(
        private GeneralSettingsService $generalSettingsService,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     provider: 'google'|'ackee'|'umami'|'openpanel'|'custom'|null,
     *     script: string,
     *     settings: array{
     *         google_measurement_id: string,
     *         ackee_script_url: string,
     *         ackee_server_url: string,
     *         ackee_domain_id: string,
     *         umami_script_url: string,
     *         umami_website_id: string,
     *         umami_host_url: string,
     *         umami_domains: string,
     *         openpanel_script_url: string,
     *         openpanel_client_id: string,
     *         openpanel_api_url: string,
     *         openpanel_track_screen_views: bool,
     *         openpanel_track_outgoing_links: bool,
     *         openpanel_track_attributes: bool,
     *         openpanel_session_replay: bool
     *     }
     * }
     */
    public function getFrontendConfig(): array
    {
        $generalSetting = $this->generalSettingsService->getGlobalSettingsModel();
        $analyticsSettings = is_array($generalSetting?->analytics_settings)
            ? $generalSetting->analytics_settings
            : [];

        $provider = $generalSetting?->analytics_provider;

        return [
            'enabled' => (bool) ($generalSetting?->analytics_enabled ?? false),
            'provider' => in_array($provider, ['google', 'ackee', 'umami', 'openpanel', 'custom'], true) ? $provider : null,
            'script' => mb_trim($this->normalizeString($generalSetting?->analytics_script)),
            'settings' => [
                'google_measurement_id' => $this->normalizeString($analyticsSettings['google_measurement_id'] ?? $generalSetting?->google_analytics_id),
                'ackee_script_url' => $this->normalizeString($analyticsSettings['ackee_script_url'] ?? ''),
                'ackee_server_url' => $this->normalizeString($analyticsSettings['ackee_server_url'] ?? ''),
                'ackee_domain_id' => $this->normalizeString($analyticsSettings['ackee_domain_id'] ?? ''),
                'umami_script_url' => $this->normalizeString($analyticsSettings['umami_script_url'] ?? ''),
                'umami_website_id' => $this->normalizeString($analyticsSettings['umami_website_id'] ?? ''),
                'umami_host_url' => $this->normalizeString($analyticsSettings['umami_host_url'] ?? ''),
                'umami_domains' => $this->normalizeString($analyticsSettings['umami_domains'] ?? ''),
                'openpanel_script_url' => $this->normalizeString($analyticsSettings['openpanel_script_url'] ?? 'https://openpanel.dev/op1.js'),
                'openpanel_client_id' => $this->normalizeString($analyticsSettings['openpanel_client_id'] ?? ''),
                'openpanel_api_url' => $this->normalizeString($analyticsSettings['openpanel_api_url'] ?? ''),
                'openpanel_track_screen_views' => (bool) ($analyticsSettings['openpanel_track_screen_views'] ?? true),
                'openpanel_track_outgoing_links' => (bool) ($analyticsSettings['openpanel_track_outgoing_links'] ?? true),
                'openpanel_track_attributes' => (bool) ($analyticsSettings['openpanel_track_attributes'] ?? true),
                'openpanel_session_replay' => (bool) ($analyticsSettings['openpanel_session_replay'] ?? false),
            ],
        ];
    }

    public function renderHeadMarkup(): string
    {
        $configuration = $this->getFrontendConfig();

        if (! $configuration['enabled']) {
            return '';
        }

        if ($configuration['script'] !== '') {
            return $configuration['script'];
        }

        return match ($configuration['provider']) {
            'google' => $this->buildGoogleSnippet($configuration['settings']['google_measurement_id']),
            'ackee' => $this->buildAckeeSnippet(
                $configuration['settings']['ackee_script_url'],
                $configuration['settings']['ackee_server_url'],
                $configuration['settings']['ackee_domain_id'],
            ),
            'umami' => $this->buildUmamiSnippet(
                $configuration['settings']['umami_script_url'],
                $configuration['settings']['umami_website_id'],
                $configuration['settings']['umami_host_url'],
                $configuration['settings']['umami_domains'],
            ),
            'openpanel' => $this->buildOpenPanelSnippet($configuration['settings']),
            default => '',
        };
    }

    private function buildGoogleSnippet(string $measurementId): string
    {
        if ($measurementId === '') {
            return '';
        }

        $escapedMeasurementId = $this->escapeAttribute($measurementId);

        return <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$escapedMeasurementId}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
window.gtag = window.gtag || gtag;
gtag('js', new Date());
gtag('config', '{$escapedMeasurementId}', { send_page_view: false });
</script>
HTML;
    }

    private function buildAckeeSnippet(string $scriptUrl, string $serverUrl, string $domainId): string
    {
        if ($scriptUrl === '' || $serverUrl === '' || $domainId === '') {
            return '';
        }

        return sprintf(
            '<script async src="%s" data-ackee-server="%s" data-ackee-domain-id="%s"></script>',
            $this->escapeAttribute($scriptUrl),
            $this->escapeAttribute($serverUrl),
            $this->escapeAttribute($domainId),
        );
    }

    private function buildUmamiSnippet(string $scriptUrl, string $websiteId, string $hostUrl, string $domains): string
    {
        if ($scriptUrl === '' || $websiteId === '') {
            return '';
        }

        $attributes = [
            sprintf('defer src="%s"', $this->escapeAttribute($scriptUrl)),
            sprintf('data-website-id="%s"', $this->escapeAttribute($websiteId)),
        ];

        if ($hostUrl !== '') {
            $attributes[] = sprintf('data-host-url="%s"', $this->escapeAttribute($hostUrl));
        }

        if ($domains !== '') {
            $attributes[] = sprintf('data-domains="%s"', $this->escapeAttribute($domains));
        }

        return sprintf('<script %s></script>', implode(' ', $attributes));
    }

    /**
     * @param  array{
     *     openpanel_script_url: string,
     *     openpanel_client_id: string,
     *     openpanel_api_url: string,
     *     openpanel_track_screen_views: bool,
     *     openpanel_track_outgoing_links: bool,
     *     openpanel_track_attributes: bool,
     *     openpanel_session_replay: bool
     * }  $settings
     */
    private function buildOpenPanelSnippet(array $settings): string
    {
        if (
            $settings['openpanel_script_url'] === ''
            || $settings['openpanel_client_id'] === ''
            || $settings['openpanel_api_url'] === ''
        ) {
            return '';
        }

        $payload = [
            'apiUrl' => $settings['openpanel_api_url'],
            'clientId' => $settings['openpanel_client_id'],
            'trackScreenViews' => $settings['openpanel_track_screen_views'],
            'trackOutgoingLinks' => $settings['openpanel_track_outgoing_links'],
            'trackAttributes' => $settings['openpanel_track_attributes'],
        ];

        if ($settings['openpanel_session_replay']) {
            $payload['sessionReplay'] = [
                'enabled' => true,
            ];
        }

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($encodedPayload)) {
            return '';
        }

        $escapedScriptUrl = $this->escapeAttribute($settings['openpanel_script_url']);

        return <<<HTML
<script>
window.op = window.op || function () {
    var queue = [];
    return new Proxy(function () {
        if (arguments.length) {
            queue.push([].slice.call(arguments));
        }
    }, {
        get: function (target, property) {
            if (property === 'q') {
                return queue;
            }

            return function () {
                queue.push([property].concat([].slice.call(arguments)));
            };
        },
        has: function (target, property) {
            return property === 'q';
        }
    });
}();
window.op('init', {$encodedPayload});
</script>
<script src="{$escapedScriptUrl}" defer async></script>
HTML;
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function normalizeString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string) $value;

        if ($stringValue === '') {
            return '';
        }

        if (mb_check_encoding($stringValue, 'UTF-8')) {
            return $stringValue;
        }

        return mb_convert_encoding($stringValue, 'UTF-8', 'UTF-8');
    }
}
