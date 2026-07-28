<?php

declare(strict_types=1);

use App\Http\Controllers\EnrollmentPolicyContextController;
use App\Http\Controllers\EnrollmentRegistrationController;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/enrollment', [EnrollmentRegistrationController::class, 'create'])
    ->name('enrollment.create');

Route::post('/enrollment', [EnrollmentRegistrationController::class, 'store'])
    ->name('enrollment.store');

Route::post('/enrollment/lookup', [EnrollmentRegistrationController::class, 'lookup'])
    ->name('enrollment.lookup');

Route::post('/enrollment/subjects', [EnrollmentRegistrationController::class, 'subjectsFor'])
    ->name('enrollment.subjects');

Route::get('/enrollment/policy-context', EnrollmentPolicyContextController::class)
    ->middleware('throttle:60,1')
    ->name('enrollment.policy-context.show');

Route::post('/enrollment/continuing', [EnrollmentRegistrationController::class, 'storeContinuing'])
    ->name('enrollment.continuing.store');

Route::get('/api-docs', fn () => redirect('/docs/v1/introduction'))->name('api-docs');

Route::get('/docs', fn () => redirect('/docs/v1'))->name('docs');

Route::get('/docs/v1/{slug?}', function (?string $slug = null) {
    if (! $slug) {
        return redirect('/docs/v1/introduction');
    }

    $legacyGuideDirectory = base_path('docs/guide');
    $legacyApiDirectory = base_path('docs/api');
    $currentDocumentationDirectory = base_path('docs/src/content/docs');
    $usesLegacyDocumentation = File::isDirectory($legacyGuideDirectory) && File::isDirectory($legacyApiDirectory);
    $guideDirectory = $usesLegacyDocumentation ? $legacyGuideDirectory : $currentDocumentationDirectory;
    $apiDirectory = $usesLegacyDocumentation ? $legacyApiDirectory : "{$currentDocumentationDirectory}/api";

    abort_unless(File::isDirectory($guideDirectory) && File::isDirectory($apiDirectory), 404);

    $operatorDocumentationPaths = [
        'introduction' => 'user-guide/introduction',
    ];
    $developerDocumentationPaths = [
        'developer-introduction' => 'start-here/introduction',
        'installation' => 'self-hosting/installation',
        'docker' => 'self-hosting/deployment',
        'configuration' => 'self-hosting/configuration',
        'troubleshooting' => 'self-hosting/troubleshooting',
        'contributing' => 'start-here/contributing',
        'development' => 'start-here/development',
        'enrollment-policy-extensions' => 'development/enrollment-policy-extensions',
    ];

    $type = 'guide';
    if (Str::startsWith($slug, 'api-') || File::exists("{$apiDirectory}/{$slug}.mdx")) {
        $type = 'api';
    } elseif (! $usesLegacyDocumentation && array_key_exists($slug, $developerDocumentationPaths)) {
        $type = 'developer';
    }

    $path = null;

    if ($type !== 'api') {
        $currentDocumentationPaths = [...$operatorDocumentationPaths, ...$developerDocumentationPaths];
        $currentDocumentationPath = $currentDocumentationPaths[$slug] ?? null;

        if (! $usesLegacyDocumentation && $currentDocumentationPath && File::exists("{$guideDirectory}/{$currentDocumentationPath}.mdx")) {
            $path = "{$guideDirectory}/{$currentDocumentationPath}.mdx";
        }

        $files = File::allFiles($guideDirectory);
        foreach ($files as $file) {
            if ($path) {
                break;
            }

            if (! $usesLegacyDocumentation) {
                $allowedDirectories = $type === 'developer'
                    ? ['start-here', 'system', 'maintainers', 'self-hosting', 'development']
                    : ['user-guide', 'enrollment-policies'];
                $allowedPrefixes = array_map(
                    fn (string $directory): string => "{$guideDirectory}/{$directory}".DIRECTORY_SEPARATOR,
                    $allowedDirectories,
                );

                if (! Str::startsWith($file->getPathname(), $allowedPrefixes)) {
                    continue;
                }
            }

            if ($file->getFilenameWithoutExtension() === $slug) {
                $path = $file->getPathname();
                break;
            }
        }
    } else {
        $apiFile = "{$apiDirectory}/{$slug}.mdx";
        if (File::exists($apiFile)) {
            $path = $apiFile;
        } elseif (File::exists("{$apiDirectory}/{$slug}.md")) {
            $path = "{$apiDirectory}/{$slug}.md";
        }
    }

    if (! $path || ! File::exists($path)) {
        if ($slug === 'index') {
            return redirect('/docs/v1/introduction');
        }
        abort(404);
    }

    $rawContent = File::get($path);
    $frontmatter = [];
    $body = $rawContent;
    $tableOfContents = [];

    if (preg_match('/^---\n(.*?)\n---\n(.*)/s', $rawContent, $matches)) {
        $body = $matches[2];
        foreach (explode("\n", $matches[1]) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = mb_trim($parts[0]);
                $value = mb_trim($parts[1]);
                $frontmatter[$key] = $value;
            }
        }
    }

    if (! $usesLegacyDocumentation) {
        $body = preg_replace('/^import\s+.+@astrojs\/starlight\/components[\'\"];\s*$/m', '', $body) ?? $body;
        $body = preg_replace('/^[\t ]*<Card\s+[^>]*title=[\'\"]([^\'\"]+)[\'\"][^>]*>/im', '### $1', $body) ?? $body;
        $body = preg_replace('/^[\t ]*<TabItem\s+[^>]*label=[\'\"]([^\'\"]+)[\'\"][^>]*>/im', '### $1', $body) ?? $body;
        $body = preg_replace('/<\/?(?:Card|CardGrid|Steps|Tabs|TabItem)(?:\s[^>]*)?>/i', '', $body) ?? $body;
        $body = preg_replace_callback(
            '/^:::(note|tip|caution|danger)(?:\[([^\]]+)\])?\s*\R(.*?)^:::\s*$/ms',
            static function (array $matches): string {
                $title = $matches[2] ?? Str::title($matches[1]);

                return "### {$title}\n\n".mb_trim($matches[3]);
            },
            $body,
        ) ?? $body;
        $body = preg_replace('/!\[([^\]]+)]\(([^)]+)\)/', '[$1]($2)', $body) ?? $body;
    }

    if ($usesLegacyDocumentation) {
        preg_match_all('/<!--\s*title-nav:\s*(.+?)\s*-->/', $body, $tocMatches);
        foreach ($tocMatches[1] ?? [] as $title) {
            $tableOfContents[] = [
                'id' => Str::slug(mb_trim($title)),
                'title' => mb_trim($title),
                'level' => 2,
            ];
        }
    } else {
        preg_match_all('/^(#{2,3})\s+(.+)$/m', $body, $headingMatches, PREG_SET_ORDER);
        foreach ($headingMatches as $heading) {
            $title = mb_trim($heading[2]);
            $tableOfContents[] = [
                'id' => Str::slug($title),
                'title' => $title,
                'level' => mb_strlen($heading[1]),
            ];
        }
    }

    $navigation = [];

    if ($type !== 'api') {
        $documentationSections = $usesLegacyDocumentation
            ? collect(File::directories($guideDirectory))
                ->mapWithKeys(fn (string $directory): array => [basename($directory) => Str::title(str_replace('-', ' ', basename($directory)))])
                ->all()
            : match ($type) {
                'developer' => [
                    'start-here' => 'Getting started',
                    'system' => 'System internals',
                    'maintainers' => 'Maintainers',
                    'self-hosting' => 'Self-hosting',
                    'development' => 'Development and extensions',
                ],
                default => [
                    'user-guide' => 'Using KoAkademy',
                    'enrollment-policies' => 'Enrollment Blueprints',
                ],
            };

        foreach ($documentationSections as $directoryName => $title) {
            $dir = "{$guideDirectory}/{$directoryName}";
            if (! File::isDirectory($dir)) {
                continue;
            }

            $files = $usesLegacyDocumentation ? File::files($dir) : File::allFiles($dir);

            $children = [];

            foreach ($files as $file) {
                $fileContent = File::get($file->getPathname());
                $fileTitle = null;
                if (preg_match('/^title:\s*(.*)$/m', $fileContent, $m)) {
                    $fileTitle = mb_trim($m[1]);
                }

                $relativePath = Str::of($file->getPathname())
                    ->after($guideDirectory.DIRECTORY_SEPARATOR)
                    ->beforeLast('.')
                    ->replace(DIRECTORY_SEPARATOR, '/')
                    ->toString();
                $navigationSlug = match ($relativePath) {
                    'user-guide/introduction' => 'introduction',
                    'start-here/introduction' => 'developer-introduction',
                    default => $file->getFilenameWithoutExtension(),
                };

                $children[] = [
                    'id' => $navigationSlug,
                    'title' => $fileTitle ?? Str::title(str_replace('-', ' ', $file->getFilenameWithoutExtension())),
                    'type' => 'page',
                ];
            }

            if (! empty($children)) {
                $navigation[] = [
                    'id' => $directoryName,
                    'title' => $title,
                    'type' => 'category',
                    'children' => $children,
                ];
            }
        }
    } else {
        $files = File::files($apiDirectory);
        $children = [];
        foreach ($files as $file) {
            $fileContent = File::get($file->getPathname());
            $fileTitle = null;
            // Try title-nav first (for navigation), fallback to title
            if (preg_match('/^title-nav:\s*(.*)$/m', $fileContent, $m)) {
                $fileTitle = mb_trim($m[1]);
            } elseif (preg_match('/^title:\s*(.*)$/m', $fileContent, $m)) {
                $fileTitle = mb_trim($m[1]);
            }

            $children[] = [
                'id' => $file->getFilenameWithoutExtension(),
                'title' => $fileTitle ?? Str::title(str_replace('-', ' ', $file->getFilenameWithoutExtension())),
                'type' => 'page',
            ];
        }

        $navigation[] = [
            'id' => 'api-reference',
            'title' => 'API Reference',
            'type' => 'category',
            'children' => $children,
        ];
    }

    // SEO Configuration
    $siteSettings = app(SiteSettings::class);
    $pageTitle = $frontmatter['title'] ?? 'Documentation';
    $pageDescription = $frontmatter['description'] ?? '';
    $appName = $siteSettings->getAppName();
    $fullTitle = "{$pageTitle} - {$appName} Documentation";
    $currentUrl = URL::current();

    // Use site's OG image or a default one
    $ogImageUrl = $siteSettings->og_image
        ? Storage::url($siteSettings->og_image)
        : URL::to('/images/og-default.png');

    // SEO Meta Data
    $seo = [
        'title' => $fullTitle,
        'description' => $pageDescription ?: "Complete documentation for {$appName}. Learn how to integrate with our API and build amazing applications.",
        'keywords' => $frontmatter['keywords'] ?? 'API, Documentation, Developer, Integration, '.$appName,
        'canonical' => $currentUrl,
        'og' => [
            'title' => $fullTitle,
            'description' => $pageDescription ?: "Complete documentation for {$appName}",
            'type' => 'article',
            'url' => $currentUrl,
            'image' => $ogImageUrl,
            'site_name' => $appName,
            'locale' => str_replace('_', '-', config('app.locale', 'en')),
        ],
        'twitter' => [
            'card' => 'summary_large_image',
            'title' => $fullTitle,
            'description' => $pageDescription ?: "Complete documentation for {$appName}",
            'image' => $ogImageUrl,
        ],
        'structured_data' => [
            '@context' => 'https://schema.org',
            '@type' => 'TechArticle',
            'headline' => $pageTitle,
            'description' => $pageDescription,
            'url' => $currentUrl,
            'author' => [
                '@type' => 'Organization',
                'name' => $appName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $appName,
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $currentUrl,
            ],
        ],
    ];

    // Share page-specific data for Blade template SEO
    Inertia::share([
        'page_title' => $fullTitle,
        'page_description' => $pageDescription ?: "Complete documentation for {$appName}. Learn how to integrate with our API and build amazing applications.",
        'page_og_image' => $ogImageUrl,
    ]);

    return Inertia::render('docs/index', [
        'slug' => $slug,
        'type' => $type,
        'page' => [
            'title' => $pageTitle,
            'description' => $pageDescription,
            'content' => $body,
            'tableOfContents' => $tableOfContents,
        ],
        'navigation' => $navigation,
        'seo' => $seo,
    ]);
})->name('docs.v1.slug');
