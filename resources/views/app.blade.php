<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark bg-white dark:bg-black">

<head>
    @php
        use App\Services\GeneralSettingsService;
        use App\Settings\SiteSettings;
        use Illuminate\Support\Facades\Storage;

        $siteSettings = app(SiteSettings::class);
        $generalSettings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
        $resolveAssetUrl = static function (?string $value): ?string {
            if (! is_string($value) || mb_trim($value) === '') {
                return null;
            }

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }

            if (str_starts_with($value, '/')) {
                return $value;
            }

            return Storage::url($value);
        };

        // Detect current domain and select appropriate settings
        $currentHost = request()->getHost();
        $portalHost = (string) config('app.portal_host');
        $isPortalDomain = app(\App\Services\SettingsShareService::class)->isPortalDomain(request());

        // Check if we're on a documentation page
        $isDocsPage = request()->is('docs/*') || request()->is('api-docs');
        $resolvedAppName = $isPortalDomain ? $siteSettings->getPortalName() : $siteSettings->getAppName();
        $seoMetadata = is_array($generalSettings?->seo_metadata) ? $generalSettings->seo_metadata : [];
        $siteName = $generalSettings?->site_name ?: $resolvedAppName;
        $siteDescription = $generalSettings?->site_description ?: ($siteSettings->description ?: '');
        $seoTitle = $generalSettings?->seo_title ?: $siteName;
        $seoKeywords = $generalSettings?->seo_keywords;
        $robots = app()->environment('production') ? data_get($seoMetadata, 'robots', 'index, follow') : 'noindex, nofollow';
        $twitterCard = data_get($seoMetadata, 'twitter_card', 'summary_large_image');
        $twitterHandle = data_get($seoMetadata, 'twitter_handle');
        $canonicalOverride = data_get($seoMetadata, 'canonical_url');

        // Get page-specific SEO data from Inertia shared props
        $pageSeo = \Inertia\Inertia::getShared('page_seo');
        $pageTitle = \Inertia\Inertia::getShared('page_title');
        $pageDescription = \Inertia\Inertia::getShared('page_description');
        $pageOgImage = \Inertia\Inertia::getShared('page_og_image');
        $ogSiteName = $resolvedAppName;
        $ogType = $isDocsPage ? 'article' : 'website';
        $structuredDataJson = null;

        // Use page-specific SEO when provided by a public page controller (e.g. Online Resources Hub)
        if (is_array($pageSeo) && ! empty($pageSeo['title'])) {
            $metaTitle = (string) $pageSeo['title'];
            $metaDescription = (string) ($pageSeo['description'] ?? '');
            $ogTitle = (string) ($pageSeo['og_title'] ?? $metaTitle);
            $ogDescription = (string) ($pageSeo['og_description'] ?? $metaDescription);
            $ogSiteName = (string) ($pageSeo['site_name'] ?? $resolvedAppName);
            $ogType = (string) ($pageSeo['og_type'] ?? 'website');
            $ogImage = $resolveAssetUrl(is_string($pageSeo['og_image'] ?? null) ? $pageSeo['og_image'] : null);
            $seoKeywords = is_string($pageSeo['keywords'] ?? null) ? $pageSeo['keywords'] : $seoKeywords;
            $twitterCard = is_string($pageSeo['twitter_card'] ?? null) ? $pageSeo['twitter_card'] : $twitterCard;

            if (is_string($pageSeo['robots'] ?? null) && mb_trim($pageSeo['robots']) !== '') {
                $robots = app()->environment('production')
                    ? $pageSeo['robots']
                    : 'noindex, nofollow';
            }

            if (is_string($pageSeo['canonical_url'] ?? null) && mb_trim($pageSeo['canonical_url']) !== '') {
                $canonicalOverride = $pageSeo['canonical_url'];
            }

            if (is_array($pageSeo['structured_data'] ?? null) && ($pageSeo['allow_indexing'] ?? false) === true) {
                $structuredDataJson = json_encode(
                    $pageSeo['structured_data'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
                );
            }
        } elseif ($isDocsPage && $pageTitle) {
            $metaTitle = $pageTitle;
            $metaDescription = $pageDescription ?? '';
            $ogTitle = $metaTitle;
            $ogDescription = $metaDescription;
            $ogImage = $pageOgImage;
        } else {
            $metaTitle = $seoTitle;
            $metaDescription = $siteDescription;
            $ogTitle = $metaTitle;
            $ogDescription = $metaDescription;

            if (data_get($seoMetadata, 'og_image')) {
                $ogImage = $resolveAssetUrl(data_get($seoMetadata, 'og_image'));
            } elseif ($isPortalDomain && $siteSettings->portal_og_image) {
                $ogImage = $resolveAssetUrl($siteSettings->portal_og_image);
            } elseif ($siteSettings->og_image) {
                $ogImage = $resolveAssetUrl($siteSettings->og_image);
            } else {
                $ogImage = null;
            }
        }

        // Generate proper URLs for R2-stored files
        $faviconUrl = $siteSettings->getFavicon();
        $appleTouchIconUrl = $siteSettings->getLogo();
        $themeColor = $siteSettings->getThemeColor();

        // Current URL for canonical and OG
        $currentUrl = url()->current();
        $canonicalUrl = is_string($canonicalOverride) && mb_trim($canonicalOverride) !== '' ? $canonicalOverride : $currentUrl;

        // Get configurable locale
        $locale = config('app.locale', 'en');
        $ogLocale = str_replace('_', '-', $locale);
        $pusherConfig = [
            'key' => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'port' => config('broadcasting.connections.pusher.options.port'),
            'scheme' => config('broadcasting.connections.pusher.options.scheme'),
        ];
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $appleTouchIconUrl }}">
    <link rel="manifest" href="{{ url('/app.webmanifest') }}">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="application-name" content="{{ $resolvedAppName }}">
    <meta name="apple-mobile-web-app-title" content="{{ $resolvedAppName }}">
    <meta name="robots" content="{{ $robots }}">

    <title inertia>{{ $metaTitle }}</title>
    <meta name="title" content="{{ $metaTitle }}">
    <meta name="description" content="{{ $metaDescription }}">
    @if(is_string($seoKeywords) && mb_trim($seoKeywords) !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif

    {{-- Open Graph / Facebook --}}
    <meta property="og:site_name" content="{{ $ogSiteName }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $currentUrl }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    @endif
    <meta property="og:locale" content="{{ $ogLocale }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ $twitterCard }}">
    @if(is_string($twitterHandle) && mb_trim($twitterHandle) !== '')
        <meta name="twitter:site" content="{{ $twitterHandle }}">
    @endif
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    @if($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ $canonicalUrl }}">

    @if(is_string($structuredDataJson) && $structuredDataJson !== '')
        <script type="application/ld+json">{!! $structuredDataJson !!}</script>
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Antic&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap"
        rel="stylesheet">

    {{-- Instant pre-hydration theme applicator to prevent flash --}}
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('ui-theme') || 'system';
                var root = document.documentElement;
                if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    root.classList.add('dark');
                    root.classList.remove('light');
                } else if (theme === 'light') {
                    root.classList.add('light');
                    root.classList.remove('dark');
                }
            } catch (e) {}
        })();
    </script>
    @includeIf('theme-tools::initialize', ['storageKey' => 'ui-color-theme'])
    {{-- Expose appName to window for Inertia --}}
    <script>
        window.appName = @json($resolvedAppName);
        window.pusherConfig = @json($pusherConfig);
    </script>

    @if(app()->environment('demo'))
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.getRegistrations().then((registrations) => {
                        registrations.forEach((registration) => registration.unregister());
                    });

                    if ('caches' in window) {
                        caches.keys().then((keys) => {
                            keys.forEach((key) => caches.delete(key));
                        });
                    }
                });
            }
        </script>
    @endif

    @viteReactRefresh
    @vite(['resources/js/App.tsx', 'resources/css/app.css'])
    {!! app(\App\Services\SentrySettingsService::class)->renderHeadMarkup() !!}
    @inertiaHead
</head>

<body class="bg-background text-foreground font-sans">
    @routes
    @inertia
    @unless(app()->environment('demo'))
        @RegisterServiceWorkerScript
    @endunless
</body>

</html>
