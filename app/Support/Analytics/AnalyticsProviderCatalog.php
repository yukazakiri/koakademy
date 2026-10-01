<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * Single source of truth for every analytics provider Koakademy can inject.
 *
 * Adding a provider means adding one entry here. The admin UI renders its
 * fields, the controller derives its validation rules, and the service derives
 * its tracking snippet, so there is no second list to keep in sync.
 *
 * Providers are opt-in per instance. Nothing is injected unless an
 * administrator saves an enabled row.
 */
final class AnalyticsProviderCatalog
{
    public const CATEGORY_SELF_HOSTED = 'self_hosted';

    public const CATEGORY_CLOUD = 'cloud';

    /**
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     docs_url: string,
     *     category: string,
     *     self_hosted: bool,
     *     consent_note: string|null,
     *     fields: array<int, array<string, mixed>>,
     *     builder: callable(array<string, mixed>): string
     * }>
     */
    public static function all(): array
    {
        static $definitions = null;

        if ($definitions !== null) {
            return $definitions;
        }

        $definitions = [
            'umami' => self::umami(),
            'plausible' => self::plausible(),
            'matomo' => self::matomo(),
            'posthog' => self::posthog(),
            'fathom' => self::fathom(),
            'openpanel' => self::openpanel(),
            'counter_dev' => self::counterDev(),
            'goatcounter' => self::goatCounter(),
            'countly' => self::countly(),
            'cloudflare' => self::cloudflare(),
            'google' => self::google(),
            'clarity' => self::clarity(),
            'simple_analytics' => self::simpleAnalytics(),
            'statcounter' => self::statCounter(),
            'yandex' => self::yandex(),
            'custom' => self::custom(),
        ];

        return $definitions;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Catalog metadata for the admin UI. Field descriptors are plain data so
     * the React settings page can render any provider generically.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontend(): array
    {
        return array_values(array_map(static function (array $definition): array {
            $fields = array_map(static function (array $field): array {
                return [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'placeholder' => $field['placeholder'],
                    'help' => $field['help'] ?? null,
                    'options' => $field['options'] ?? [],
                    'default' => $field['default'] ?? null,
                ];
            }, $definition['fields']);

            return [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'docs_url' => $definition['docs_url'],
                'category' => $definition['category'],
                'self_hosted' => $definition['self_hosted'],
                'consent_note' => $definition['consent_note'],
                'fields' => $fields,
            ];
        }, self::all()));
    }

    /**
     * Validation rules for a single provider row, derived from the catalog.
     *
     * @return array<string, string>
     */
    public static function rulesFor(string $key, string $prefix = ''): array
    {
        $definition = self::get($key);

        if ($definition === null) {
            return [];
        }

        $rules = [];

        foreach ($definition['fields'] as $field) {
            $rule = match ($field['type']) {
                'url' => 'nullable|url|max:2048',
                'textarea' => 'nullable|string|max:20000',
                'toggle' => 'nullable|boolean',
                'select' => 'nullable|string|max:255',
                default => 'nullable|string|max:'.($field['max'] ?? 255),
            };

            $rules[$prefix.$field['key']] = $rule;
        }

        return $rules;
    }

    /**
     * Default field values used when an administrator adds a new instance.
     *
     * @return array<string, mixed>
     */
    public static function defaultsFor(string $key): array
    {
        $definition = self::get($key);

        if ($definition === null) {
            return [];
        }

        $defaults = [];

        foreach ($definition['fields'] as $field) {
            $defaults[$field['key']] = $field['default'] ?? ($field['type'] === 'toggle' ? false : '');
        }

        return $defaults;
    }

    /**
     * Build the tracking snippet for a provider, or an empty string when the
     * instance is not fully configured.
     *
     * @param  array<string, mixed>  $values
     */
    public static function buildSnippet(string $key, array $values): string
    {
        $definition = self::get($key);

        if ($definition === null) {
            return '';
        }

        $snippet = ($definition['builder'])($values);

        return mb_trim($snippet);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function text(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_scalar($value) ? mb_trim((string) $value) : '';
    }

    private static function flag(array $values, string $key): bool
    {
        return filter_var($values[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private static function umami(): array
    {
        return [
            'key' => 'umami',
            'label' => 'Umami',
            'description' => 'Privacy-focused analytics with click and scroll heatmaps and session replay.',
            'docs_url' => 'https://umami.is/docs',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::urlField('script_url', 'Script URL', 'https://umami.example.com/script.js'),
                self::textField('website_id', 'Website ID', 'c3f8e397-1612-4b95-8963-c20a654f02f6'),
                self::urlField('host_url', 'Host URL (optional)', 'https://umami.example.com'),
                self::textField('domains', 'Domains (optional)', 'portal.example.edu,admin.example.edu'),
                self::toggleField(
                    'heatmaps',
                    'Click & scroll heatmaps',
                    'Loads recorder.js in addition to the tracker. Requires Umami v3.2+. Heatmaps are aggregated, so no individual session is identifiable.',
                ),
            ],
            'builder' => self::buildUmami(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildUmami(array $v): string
    {
        $scriptUrl = self::text($v, 'script_url');
        $websiteId = self::text($v, 'website_id');

        if ($scriptUrl === '' || $websiteId === '') {
            return '';
        }

        $attributes = [
            sprintf('defer src="%s"', self::attr($scriptUrl)),
            sprintf('data-website-id="%s"', self::attr($websiteId)),
        ];

        $hostUrl = self::text($v, 'host_url');
        $domains = self::text($v, 'domains');

        if ($hostUrl !== '') {
            $attributes[] = sprintf('data-host-url="%s"', self::attr($hostUrl));
        }

        if ($domains !== '') {
            $attributes[] = sprintf('data-domains="%s"', self::attr($domains));
        }

        $scripts = sprintf('<script %s></script>', implode(' ', $attributes));

        if (self::flag($v, 'heatmaps')) {
            $recorderUrl = self::recorderUrlFor($scriptUrl);
            $scripts .= "\n".sprintf(
                '<script defer src="%s" data-website-id="%s"></script>',
                self::attr($recorderUrl),
                self::attr($websiteId),
            );
        }

        return $scripts;
    }

    /**
     * Derive recorder.js from the configured script.js so administrators only
     * ever paste one URL.
     */
    private static function recorderUrlFor(string $scriptUrl): string
    {
        if (str_ends_with($scriptUrl, '/script.js')) {
            return mb_substr($scriptUrl, 0, -mb_strlen('script.js')).'recorder.js';
        }

        $segments = pathinfo($scriptUrl);

        if (isset($segments['dirname']) && $segments['dirname'] !== '') {
            $base = mb_rtrim($segments['dirname'], '/');

            return $base.'/recorder.js';
        }

        return $scriptUrl;
    }

    /**
     * @return array<string, mixed>
     */
    private static function plausible(): array
    {
        return [
            'key' => 'plausible',
            'label' => 'Plausible Analytics',
            'description' => 'Lightweight, open-source, privacy-friendly web analytics.',
            'docs_url' => 'https://plausible.io/docs',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::urlField('script_url', 'Script URL', 'https://plausible.io/js/script.js'),
                self::textField('domain', 'Site Domain', 'portal.example.edu'),
                self::textField('api_host', 'API Host (optional)', 'https://plausible.example.com'),
            ],
            'builder' => self::buildPlausible(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildPlausible(array $v): string
    {
        $scriptUrl = self::text($v, 'script_url');
        $domain = self::text($v, 'domain');

        if ($scriptUrl === '' || $domain === '') {
            return '';
        }

        $attributes = [
            'defer',
            'data-domain="'.self::attr($domain).'"',
            'src="'.self::attr($scriptUrl).'"',
        ];

        $apiHost = self::text($v, 'api_host');

        if ($apiHost !== '') {
            $attributes[] = 'data-api="'.self::attr($apiHost).'"';
        }

        return '<script '.implode(' ', $attributes).'></script>';
    }

    /**
     * @return array<string, mixed>
     */
    private static function matomo(): array
    {
        return [
            'key' => 'matomo',
            'label' => 'Matomo',
            'description' => 'Full-featured analytics. Heatmaps and session recording are paid plugins, including when self-hosted.',
            'docs_url' => 'https://matomo.org/docs',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => 'Matomo can be configured to be fully cookieless and consent-free.',
            'fields' => [
                self::urlField('base_url', 'Matomo URL', 'https://matomo.example.com/'),
                self::textField('site_id', 'Site ID', '1'),
            ],
            'builder' => self::buildMatomo(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildMatomo(array $v): string
    {
        $baseUrl = self::text($v, 'base_url');
        $siteId = self::text($v, 'site_id');

        if ($baseUrl === '' || $siteId === '') {
            return '';
        }

        $base = mb_rtrim($baseUrl, '/').'/';
        $baseJs = self::attr($base);
        $baseJs = html_entity_decode($baseJs, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<script>
var _paq = window._paq = window._paq || [];
_paq.push(['trackPageView']);
_paq.push(['enableLinkTracking']);
(function () {
    var u = '{$baseJs}/';
    _paq.push(['setTrackerUrl', u + 'matomo.php']);
    _paq.push(['setSiteId', '{$siteId}']);
    var d = document,
        g = d.createElement('script'),
        s = d.getElementsByTagName('script')[0];
    g.async = true;
    g.src = u + 'matomo.js';
    s.parentNode.insertBefore(g, s);
})();
</script>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function posthog(): array
    {
        return [
            'key' => 'posthog',
            'label' => 'PostHog',
            'description' => 'Product analytics with autocapture, funnels, path analysis, and session replay.',
            'docs_url' => 'https://posthog.com/docs',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::urlField('api_host', 'API Host', 'https://analytics.example.com'),
                self::textField('project_token', 'Project Token', 'phc_xxxxxxxxxxxxxxxxx'),
                self::toggleField(
                    'autocapture',
                    'Autocapture',
                    'Captures button clicks, form submissions, and rage clicks. This is the data behind PostHog clickmaps.',
                ),
            ],
            'builder' => self::buildPostHog(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildPostHog(array $v): string
    {
        $apiHost = self::text($v, 'api_host');
        $token = self::text($v, 'project_token');

        if ($apiHost === '' || $token === '') {
            return '';
        }

        $apiHost = mb_rtrim($apiHost, '/');
        $hostJs = self::attr($apiHost);
        $hostJs = html_entity_decode($hostJs, ENT_QUOTES, 'UTF-8');
        $tokenJs = self::attr($token);
        $tokenJs = html_entity_decode($tokenJs, ENT_QUOTES, 'UTF-8');
        $autocapture = self::flag($v, 'autocapture') ? 'true' : 'false';

        return <<<HTML
<script>
window.posthog = window.posthog || [];
posthog.init('{$tokenJs}', { api_host: '{$hostJs}', autocapture: {$autocapture} });
(function () {
    var s = document.createElement('script');
    s.src = '{$hostJs}/static/array.js';
    s.async = true;
    (document.head || document.body).appendChild(s);
})();
</script>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fathom(): array
    {
        return [
            'key' => 'fathom',
            'label' => 'Fathom',
            'description' => 'Simple, privacy-focused analytics. Use Fathom Lite for a self-hosted instance.',
            'docs_url' => 'https://usefathom.com/docs',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::selectField('flavour', 'Flavour', [
                    ['value' => 'lite', 'label' => 'Fathom Lite (self-hosted)'],
                    ['value' => 'cloud', 'label' => 'Fathom Cloud'],
                ], 'lite'),
                self::urlField('script_url', 'Script URL', 'https://cdn.usefathom.com/script.js'),
                self::textField('site_id', 'Site ID', 'ABCDEFGH'),
            ],
            'builder' => self::buildFathom(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildFathom(array $v): string
    {
        $scriptUrl = self::text($v, 'script_url');
        $siteId = self::text($v, 'site_id');

        if ($scriptUrl === '' || $siteId === '') {
            return '';
        }

        $attribute = self::text($v, 'flavour') === 'cloud' ? 'site' : 'data-site';
        $url = self::attr($scriptUrl);
        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<script src="%s" %s="%s" defer></script>',
            $url,
            $attribute,
            self::attr($siteId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function openpanel(): array
    {
        return [
            'key' => 'openpanel',
            'label' => 'OpenPanel',
            'description' => 'Cookie-free product and web analytics with session replay. Runs on a small Docker Compose stack.',
            'docs_url' => 'https://openpanel.dev/docs',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::urlField('script_url', 'Script URL', 'https://openpanel.example.com/op1.js'),
                self::urlField('api_url', 'API URL', 'https://openpanel.example.com/api'),
                self::textField('client_id', 'Client ID', 'e4e45149-bbde-44d7-b436-f9a0ae1042b0'),
                self::toggleField('track_screen_views', 'Track screen views', null, true),
                self::toggleField('track_outgoing_links', 'Track outgoing links', null, true),
                self::toggleField('track_attributes', 'Track attributes', null, true),
                self::toggleField(
                    'session_replay',
                    'Session replay',
                    'Records DOM mutations with all text and inputs masked. Review data-protection rules before enabling on pages showing student data.',
                ),
            ],
            'builder' => self::buildOpenPanel(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildOpenPanel(array $v): string
    {
        $scriptUrl = self::text($v, 'script_url');
        $apiUrl = self::text($v, 'api_url');
        $clientId = self::text($v, 'client_id');

        if ($scriptUrl === '' || $apiUrl === '' || $clientId === '') {
            return '';
        }

        $config = [
            'apiUrl' => $apiUrl,
            'clientId' => $clientId,
            'trackScreenViews' => self::flag($v, 'track_screen_views'),
            'trackOutgoingLinks' => self::flag($v, 'track_outgoing_links'),
            'trackAttributes' => self::flag($v, 'track_attributes'),
        ];

        if (self::flag($v, 'session_replay')) {
            $config['sessionReplay'] = ['enabled' => true];
        }

        $encoded = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($encoded)) {
            return '';
        }

        $scriptUrlAttr = self::attr($scriptUrl);
        $scriptUrlAttr = html_entity_decode($scriptUrlAttr, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<script>
window.op = window.op || function () {
    var queue = [];
    return new Proxy(
        function () {
            if (arguments.length) {
                queue.push([].slice.call(arguments));
            }
        },
        {
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
            },
        },
    );
})();
window.op('init', {$encoded});
</script>
<script src="{$scriptUrlAttr}" defer async></script>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function counterDev(): array
    {
        return [
            'key' => 'counter_dev',
            'label' => 'Counter.dev',
            'description' => 'Free, open-source, cookieless analytics with no signup required.',
            'docs_url' => 'https://counter.dev',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => false,
            'consent_note' => null,
            'fields' => [
                self::urlField('script_url', 'Script URL', 'https://cdn.counter.dev/script.js'),
            ],
            'builder' => self::buildCounterDev(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildCounterDev(array $v): string
    {
        $scriptUrl = self::text($v, 'script_url');

        if ($scriptUrl === '') {
            return '';
        }

        return sprintf('<script async src="%s"></script>', self::attr($scriptUrl));
    }

    /**
     * @return array<string, mixed>
     */
    private static function goatCounter(): array
    {
        return [
            'key' => 'goatcounter',
            'label' => 'GoatCounter',
            'description' => 'Open-source, self-hosted, privacy-friendly analytics with no cookies.',
            'docs_url' => 'https://www.goatcounter.org',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::urlField('script_url', 'Script URL', 'https://gc.example.com/count.js'),
                self::textField('site_code', 'Site Code', 'my-school-portal'),
                self::toggleField('no_onload', 'Skip onload attribute', 'Prevents GoatCounter from firing a pageview when the script finishes loading.', false),
            ],
            'builder' => self::buildGoatCounter(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildGoatCounter(array $v): string
    {
        $scriptUrl = self::text($v, 'script_url');
        $code = self::text($v, 'site_code');

        if ($scriptUrl === '' || $code === '') {
            return '';
        }

        $attributes = [
            'data-goatcounter="'.self::attr($code).'"',
            'async',
            'src="'.self::attr($scriptUrl).'"',
        ];

        if (self::flag($v, 'no_onload')) {
            $attributes[] = 'data-no-onload';
        }

        return '<script '.implode(' ', $attributes).'></script>';
    }

    /**
     * @return array<string, mixed>
     */
    private static function countly(): array
    {
        return [
            'key' => 'countly',
            'label' => 'Countly',
            'description' => 'Self-hosted product analytics. The open-source Community Edition is feature-limited.',
            'docs_url' => 'https://count.ly/docs',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::urlField('server_url', 'Server URL', 'https://countly.example.com'),
                self::textField('app_key', 'App Key', 'your-app-key'),
                self::toggleField('require_consent', 'Require consent', null, false),
            ],
            'builder' => self::buildCountly(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildCountly(array $v): string
    {
        $serverUrl = self::text($v, 'server_url');
        $appKey = self::text($v, 'app_key');

        if ($serverUrl === '' || $appKey === '') {
            return '';
        }

        $base = mb_rtrim($serverUrl, '/');
        $baseJs = self::attr($base);
        $baseJs = html_entity_decode($baseJs, ENT_QUOTES, 'UTF-8');
        $keyJs = self::attr($appKey);
        $keyJs = html_entity_decode($keyJs, ENT_QUOTES, 'UTF-8');
        $consent = self::flag($v, 'require_consent') ? 'true' : 'false';

        return <<<HTML
<script src="{$baseJs}/api/v1/js/sdn.cjs" crossorigin="anonymous"></script>
<script>
var countly = countly || [];
countly.q = countly.q || [];
countly.init({ app_key: '{$keyJs}', url: '{$baseJs}', require_consent: {$consent} });
</script>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cloudflare(): array
    {
        return [
            'key' => 'cloudflare',
            'label' => 'Cloudflare Web Analytics',
            'description' => 'Free, cookieless analytics served through your existing Cloudflare zone.',
            'docs_url' => 'https://developers.cloudflare.com/web-analytics',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => false,
            'consent_note' => 'Cookie-free and privacy-first. No consent banner is required.',
            'fields' => [
                self::textField('token', 'Beacon Token', '00000000000000000000000000000000'),
            ],
            'builder' => self::buildCloudflare(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildCloudflare(array $v): string
    {
        $token = self::text($v, 'token');

        if ($token === '') {
            return '';
        }

        $json = json_encode(['token' => $token], JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            return '';
        }

        return sprintf(
            '<script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon=%s></script>',
            self::attr($json),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function google(): array
    {
        return [
            'key' => 'google',
            'label' => 'Google Analytics 4',
            'description' => 'Google-hosted analytics. Data is processed by Google, so it requires a consent notice on most jurisdictions.',
            'docs_url' => 'https://support.google.com/analytics/answer/10085881',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => false,
            'consent_note' => 'Third-party processor. Student data leaves your server, so this needs an explicit legal and guardian-consent basis.',
            'fields' => [
                self::textField('measurement_id', 'Measurement ID', 'G-XXXXXXXXXX'),
            ],
            'builder' => self::buildGoogle(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildGoogle(array $v): string
    {
        $measurementId = self::text($v, 'measurement_id');

        if ($measurementId === '') {
            return '';
        }

        // Page views are sent manually on Inertia navigation, so automatic
        // sending is disabled here to avoid double counting.
        $id = self::attr($measurementId);

        return <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
window.gtag = window.gtag || gtag;
gtag('js', new Date());
gtag('config', '{$id}', { send_page_view: false });
</script>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function clarity(): array
    {
        return [
            'key' => 'clarity',
            'label' => 'Microsoft Clarity',
            'description' => 'Free session recordings and click heatmaps. Hosted by Microsoft.',
            'docs_url' => 'https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-setup',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => false,
            'consent_note' => 'Session recordings are Microsoft-hosted and can capture student data on screen. Review your data-protection obligations first.',
            'fields' => [
                self::textField('project_id', 'Project ID', 'abcdefghijklmnop'),
            ],
            'builder' => self::buildClarity(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildClarity(array $v): string
    {
        $projectId = self::text($v, 'project_id');

        if ($projectId === '') {
            return '';
        }

        $id = self::attr($projectId);
        $id = html_entity_decode($id, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<script type="text/javascript">
(function (c, l, a, r, i, t, y) {
    c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
    t = l.createElement(r);
    t.async = 1;
    t.src = 'https://www.clarity.ms/tag/' + i;
    y = l.getElementsByTagName(r)[0];
    y.parentNode.insertBefore(t, y);
})(window, document, 'clarity', 'script', '{$id}');
</script>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function simpleAnalytics(): array
    {
        return [
            'key' => 'simple_analytics',
            'label' => 'Simple Analytics',
            'description' => 'Cookie-free, privacy-first analytics with a generous free tier.',
            'docs_url' => 'https://www.simpleanalytics.com/docs',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => false,
            'consent_note' => null,
            'fields' => [
                self::urlField('script_url', 'Script URL', 'https://cdn.simpleanalyticscdn.com/latest.js'),
            ],
            'builder' => self::buildSimpleAnalytics(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildSimpleAnalytics(array $v): string
    {
        $scriptUrl = self::text($v, 'script_url');

        if ($scriptUrl === '') {
            return '';
        }

        return sprintf('<script async src="%s"></script>', self::attr($scriptUrl));
    }

    /**
     * @return array<string, mixed>
     */
    private static function statCounter(): array
    {
        return [
            'key' => 'statcounter',
            'label' => 'StatCounter',
            'description' => 'Long-established hosted analytics with a free tier.',
            'docs_url' => 'https://statcounter.com/',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => false,
            'consent_note' => 'Third-party processor. Requires a consent notice where applicable.',
            'fields' => [
                self::textField('project_code', 'Project Code', '0000000'),
                self::textField('security_code', 'Security Code', '00000000'),
            ],
            'builder' => self::buildStatCounter(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildStatCounter(array $v): string
    {
        $project = self::text($v, 'project_code');
        $security = self::text($v, 'security_code');

        if ($project === '' || $security === '') {
            return '';
        }

        $p = self::attr($project);
        $p = html_entity_decode($p, ENT_QUOTES, 'UTF-8');
        $s = self::attr($security);
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<script type="text/javascript">
var sc_project = '{$p}';
var sc_invisibility = 1;
var sc_security = '{$s}';
</script>
<script type="text/javascript" src="https://www.statcounter.com/counter/counter.js"></script>
<noscript><img src="https://c.statcounter.com/{$p}/0/{$s}/1/" alt="StatCounter" referrerPolicy="no-referrer-when-downgrade" /></noscript>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function yandex(): array
    {
        return [
            'key' => 'yandex',
            'label' => 'Yandex Metrica',
            'description' => 'Hosted analytics popular in Eastern Europe and Central Asia, with session recording.',
            'docs_url' => 'https://yandex.com/support/metrica/',
            'category' => self::CATEGORY_CLOUD,
            'self_hosted' => false,
            'consent_note' => 'Third-party processor with session recording. Requires an explicit legal basis.',
            'fields' => [
                self::textField('counter_id', 'Counter ID', '12345678'),
                self::toggleField('clickmap', 'Enable click map', null, true),
            ],
            'builder' => self::buildYandex(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildYandex(array $v): string
    {
        $counterId = self::text($v, 'counter_id');

        if ($counterId === '') {
            return '';
        }

        $id = self::attr($counterId);
        $id = html_entity_decode($id, ENT_QUOTES, 'UTF-8');
        $clickmap = self::flag($v, 'clickmap') ? 'true' : 'false';

        return <<<HTML
<script type="text/javascript">
(function (m, e, t, r, i, k, a) {
    m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
    a = e.createElement(t);
    a.async = 1;
    a.src = 'https://mc.yandex.ru/metrika/tag.js';
    k = e.getElementsByTagName(t)[0];
    k.parentNode.insertBefore(a, k);
})(window, document, 'yandex_metrika_callbacks');
window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
window.ym.l = Number(new Date());
window.ym('init', '{$id}', { clickmap: {$clickmap} });
</script>
<noscript><img src="https://mc.yandex.ru/watch/{$id}" style="position:absolute; left:-9999px;" alt="" /></noscript>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private static function custom(): array
    {
        return [
            'key' => 'custom',
            'label' => 'Custom Snippet',
            'description' => 'Paste any tracking snippet. Add as many instances as you need.',
            'docs_url' => '',
            'category' => self::CATEGORY_SELF_HOSTED,
            'self_hosted' => true,
            'consent_note' => null,
            'fields' => [
                self::textareaField('snippet', 'Snippet', '<script async src="https://example.com/analytics.js"></script>'),
            ],
            'builder' => self::buildCustom(...),
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function buildCustom(array $v): string
    {
        $snippet = self::text($v, 'snippet');

        return $snippet === '' ? '' : $snippet;
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     * @return array<string, mixed>
     */
    private static function selectField(string $key, string $label, array $options, string $default): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'placeholder' => '',
            'options' => $options,
            'default' => $default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function urlField(string $key, string $label, string $placeholder, ?string $help = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'url',
            'placeholder' => $placeholder,
            'help' => $help,
            'default' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textField(string $key, string $label, string $placeholder, int $max = 255): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'text',
            'placeholder' => $placeholder,
            'max' => $max,
            'default' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textareaField(string $key, string $label, string $placeholder): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'textarea',
            'placeholder' => $placeholder,
            'default' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function toggleField(string $key, string $label, ?string $help = null, bool $default = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'toggle',
            'placeholder' => '',
            'help' => $help,
            'default' => $default,
        ];
    }
}
