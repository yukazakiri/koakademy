<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark bg-white dark:bg-black">

<head>
    @php
        use App\Settings\SiteSettings;
        use Illuminate\Support\Facades\Storage;

        $siteSettings = app(SiteSettings::class);
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

            return Storage::disk('r2')->url($value);
        };

        // Detect current domain and select appropriate settings
        $currentHost = request()->getHost();
        $portalHost = env('PORTAL_HOST', 'portal.' . parse_url(config('app.url'), PHP_URL_HOST));
        $isPortalDomain = str_contains($currentHost, $portalHost);

        // Check if we're on a documentation page
        $isDocsPage = request()->is('docs/*') || request()->is('api-docs');
        
        // Get page-specific data from Inertia shared props (set by docs controller)
        $pageTitle = \Inertia\Inertia::getShared('page_title');
        $pageDescription = \Inertia\Inertia::getShared('page_description');
        $pageOgImage = \Inertia\Inertia::getShared('page_og_image');
        
        // Use page-specific data if on docs page, otherwise use site defaults
        if ($isDocsPage && $pageTitle) {
            $metaTitle = $pageTitle;
            $metaDescription = $pageDescription ?? '';
            $ogImage = $pageOgImage;
        } else {
            // Use portal-specific settings if on portal domain, otherwise use admin settings
            $metaTitle = $isPortalDomain
                ? ($siteSettings->portal_name ?: $siteSettings->getAppName())
                : $siteSettings->getAppName();

            $metaDescription = $isPortalDomain
                ? ($siteSettings->portal_description ?: $siteSettings->description)
                : $siteSettings->description;
                
            // Use portal-specific OG image if on portal domain
            if ($isPortalDomain && $siteSettings->portal_og_image) {
                $ogImage = $resolveAssetUrl($siteSettings->portal_og_image);
            } elseif ($siteSettings->og_image) {
                $ogImage = $resolveAssetUrl($siteSettings->og_image);
            } else {
                $ogImage = null;
            }
        }

        // Generate proper URLs for R2-stored files
        $faviconUrl = $resolveAssetUrl($siteSettings->favicon);
        
        // Current URL for canonical and OG
        $currentUrl = url()->current();
        
        // Get configurable locale
        $locale = config('app.locale', 'en');
        $ogLocale = str_replace('_', '-', $locale);
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @if($faviconUrl)
        <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
    @endif
    @if(config('app.env') !== 'production')
        <meta name="robots" content="noindex, nofollow">
    @endif

    <title inertia>{{ $metaTitle }}</title>
    <meta name="title" content="{{ $metaTitle }}">
    <meta name="description" content="{{ $metaDescription }}">
    
    {{-- Open Graph / Facebook --}}
    <meta property="og:site_name" content="{{ $siteSettings->getAppName() }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="{{ $isDocsPage ? 'article' : 'website' }}">
    <meta property="og:url" content="{{ $currentUrl }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    @endif
    <meta property="og:locale" content="{{ $ogLocale }}">
    
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="{{ $siteSettings->getAppName() }}">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    
    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ $currentUrl }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Antic&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap"
        rel="stylesheet">
    @if(config('app.env') !== 'production')
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- Expose appName to window for Inertia --}}
    <script>
        window.appName = "{{ $siteSettings->getAppName() }}";
    </script>

    @PwaHead

    @viteReactRefresh
    @vite(['resources/js/App.tsx', 'resources/css/app.css'])
    @inertiaHead
</head>

<body class="text-white font-sans">
    @routes
    @inertia
    @RegisterServiceWorkerScript
</body>

</html>
