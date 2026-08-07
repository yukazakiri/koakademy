<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('uses the legacy introduction URL for the operator user guide', function () {
    $this->withoutMiddleware()
        ->get(portalUrlForAdministrators('/docs/v1/introduction'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/index', false)
            ->where('slug', 'introduction')
            ->where('type', 'guide')
            ->where('page.title', 'Using KoAkademy')
            ->where('page.content', fn (string $content): bool => str_contains($content, 'people who operate the school')
                && str_contains($content, 'Configure enrollment without code')
                && ! str_contains($content, 'composer install'))
            ->where('page.tableOfContents.0.title', 'Start with your assigned workspace')
            ->where('navigation', fn (Collection $navigation): bool => $navigation->pluck('id')->all() === [
                'user-guide',
                'enrollment-policies',
            ]),
        );
});

it('keeps setup and extension documentation in a separate developer area', function () {
    $this->withoutMiddleware()
        ->get(portalUrlForAdministrators('/docs/v1/developer-introduction'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/index', false)
            ->where('slug', 'developer-introduction')
            ->where('type', 'developer')
            ->where('page.title', 'Introduction')
            ->where('page.content', fn (string $content): bool => str_contains($content, 'Pick your path'))
            ->where('navigation', fn (Collection $navigation): bool => $navigation->pluck('id')->all() === [
                'start-here',
                'system',
                'maintainers',
                'self-hosting',
                'development',
            ]),
        );
});

it('renders the legacy API documentation URL from the current API source', function () {
    $this->withoutMiddleware()
        ->get(portalUrlForAdministrators('/docs/v1/api-overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/index', false)
            ->where('slug', 'api-overview')
            ->where('type', 'api')
            ->where('page.title', 'API Overview'),
        );
});
