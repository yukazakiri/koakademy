<?php

declare(strict_types=1);

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

Route::post('/enrollment/continuing', [EnrollmentRegistrationController::class, 'storeContinuing'])
    ->name('enrollment.continuing.store');

Route::get('/api-docs', fn () => redirect('/docs/v1/introduction'))->name('api-docs');

Route::get('/docs', fn () => redirect('/docs/v1'))->name('docs');

Route::get('/docs/v1/{slug?}', function (?string $slug = null) {
    if (! $slug) {
        return redirect('/docs/v1/introduction');
    }

    $type = 'guide';
    if (Str::startsWith($slug, 'api-') || File::exists(base_path("docs/api/{$slug}.mdx"))) {
        $type = 'api';
    }

    $path = null;

    if ($type === 'guide') {
        $files = File::allFiles(base_path('docs/guide'));
        foreach ($files as $file) {
            if ($file->getFilenameWithoutExtension() === $slug) {
                $path = $file->getPathname();
                break;
            }
        }
    } else {
        $apiFile = base_path("docs/api/{$slug}.mdx");
        if (File::exists($apiFile)) {
            $path = $apiFile;
        } elseif (File::exists(base_path("docs/api/{$slug}.md"))) {
            $path = base_path("docs/api/{$slug}.md");
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

    preg_match_all('/<!--\s*title-nav:\s*(.+?)\s*-->/', $body, $tocMatches);
    foreach ($tocMatches[1] ?? [] as $title) {
        $id = Str::slug(mb_trim($title));
        $tableOfContents[] = [
            'id' => $id,
            'title' => mb_trim($title),
            'level' => 2,
        ];
    }

    $navigation = [];

    if ($type === 'guide') {
        $directories = File::directories(base_path('docs/guide'));
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $title = Str::title(str_replace('-', ' ', $dirName));

            $files = File::files($dir);
            $children = [];

            foreach ($files as $file) {
                $fileContent = File::get($file->getPathname());
                $fileTitle = null;
                if (preg_match('/^title:\s*(.*)$/m', $fileContent, $m)) {
                    $fileTitle = mb_trim($m[1]);
                }

                $children[] = [
                    'id' => $file->getFilenameWithoutExtension(),
                    'title' => $fileTitle ?? Str::title(str_replace('-', ' ', $file->getFilenameWithoutExtension())),
                    'type' => 'page',
                ];
            }

            if (! empty($children)) {
                $navigation[] = [
                    'id' => $dirName,
                    'title' => $title,
                    'type' => 'category',
                    'children' => $children,
                ];
            }
        }
    } else {
        $files = File::files(base_path('docs/api'));
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
