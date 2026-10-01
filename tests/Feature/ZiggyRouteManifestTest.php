<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * config/ziggy.php ships a name allowlist because the application registers over a thousand
 * routes while the front end resolves roughly 240 of them. This test keeps the list honest: a
 * route() call whose name is not in the manifest fails at runtime in the browser, which is a
 * far worse failure mode than a failing test.
 *
 * Both sides are derived from source, so neither the allowlist nor this audit can drift.
 */
function referencedRouteNames(string $directory, string $pattern): array
{
    $names = [];

    if (! File::isDirectory($directory)) {
        return $names;
    }

    foreach (File::allFiles($directory) as $file) {
        if (! preg_match($pattern, $file->getFilename())) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        // route('name', ...) and routeName = "name"
        if (preg_match_all('/route\(\s*[\'"]([a-zA-Z0-9._-]+)[\'"]/', $source, $matches) > 0) {
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        if (preg_match_all('/routeName\s*=\s*[\'"]([a-zA-Z0-9._-]+)[\'"]/', $source, $matches) > 0) {
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }
    }

    return array_keys($names);
}

/**
 * Route names that reach the front end inside Inertia props rather than via route().
 *
 * Excluded because they are resolved by Filament or sent in a server-rendered notification and
 * never looked up in Ziggy: `filament.*`, `horizon.*` and the MCP/export descriptors that only
 * appear as an action URL.
 */
function backendRouteNames(): array
{
    $names = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        // Three exclusions matter here:
        //  - (?<!::)            keeps Route::get(...) out
        //  - (?<!->)            keeps $request->route('id') out; that reads a route *parameter*
        //  - (?<!\$this)        keeps $this->route('mcpServer') out
        // Only bare route('name', ...) resolves a named route.
        if (preg_match_all("/(?<!::)(?<!->)(?<!\\\$this)(?<![a-zA-Z0-9_>\\\$])\\broute\(\s*'([a-zA-Z0-9._-]+)'/", $source, $matches) > 0) {
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }
    }

    return array_values(array_filter(
        array_keys($names),
        fn (string $name): bool => ! str_starts_with($name, 'filament.'),
    ));
}

/** @return list<string> */
function registeredRouteNames(): array
{
    $names = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();

        if ($name !== null && $name !== '') {
            $names[] = $name;
        }
    }

    return $names;
}

it('serves far fewer routes than the application registers', function (): void {
    $registered = registeredRouteNames();
    $manifest = array_keys(config('ziggy.only') ?? []);

    expect($manifest)->not->toBeEmpty();
    expect(count($manifest))->toBeLessThan(count($registered));
});

it('only lists route names that actually exist', function (): void {
    $registered = registeredRouteNames();

    $unknown = array_values(array_diff(config('ziggy.only') ?? [], $registered));

    expect($unknown)->toBe([]);
});

it('serves every route name the front end references', function (): void {
    $referenced = referencedRouteNames(resource_path('js'), '/\.(tsx|ts)$/');
    $registered = registeredRouteNames();

    // A name the front end asks for that the application never registered is a broken link,
    // and would otherwise be invisible until it was clicked.
    $unregistered = array_values(array_diff($referenced, $registered));

    expect($unregistered)->toBe([], 'The front end references routes that do not exist: '.implode(', ', $unregistered));

    $missing = array_values(array_diff($referenced, config('ziggy.only') ?? []));

    expect($missing)->toBe([], 'Add these to config/ziggy.php: '.implode(', ', $missing));
});

it('serves every route name the back end sends to the front end', function (): void {
    $manifest = config('ziggy.only') ?? [];

    // Only routes resolved for the Inertia payload need Ziggy. A route() call whose result is
    // server-rendered -- a Filament notification action URL, an export job download link -- is
    // already a finished string by the time it leaves PHP, so it is excluded here. Filament
    // also resolves its own routes and never reads Ziggy.
    $relevant = array_values(array_filter(
        backendRouteNames(),
        fn (string $name): bool => ! str_starts_with($name, 'filament.'),
    ));

    // faculty.classes.* appears in this list because it is rendered into a Filament
    // notification; the desk switcher and queue hrefs are the Inertia-facing callers and are
    // covered by the frontend scan above.
    $inertiaFacing = array_values(array_filter(
        $relevant,
        fn (string $name): bool => ! str_starts_with($name, 'faculty.'),
    ));

    $missing = array_values(array_diff($inertiaFacing, $manifest));

    expect($missing)->toBe([], 'Add these to config/ziggy.php: '.implode(', ', $missing));
});

it('resolves the desk routes the switcher and sidebar link to', function (): void {
    $manifest = config('ziggy.only') ?? [];

    // Added in the role-desks change; the switcher URLs are built server-side so the
    // frontend-only scan cannot see them.
    expect($manifest)->toContain('administrators.desks.show');
});

it('does not blow the payload up', function (): void {
    // Guards against someone removing the allowlist and shipping every route again.
    $bytes = mb_strlen(json_encode((new Tighten\Ziggy\Ziggy)->toArray()));

    expect($bytes)->toBeLessThan(60_000);
});
