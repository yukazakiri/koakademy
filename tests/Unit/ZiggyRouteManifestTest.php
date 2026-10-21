<?php

declare(strict_types=1);

it('exposes global search routes in the frontend manifest', function (string $routeName): void {
    $manifest = require dirname(__DIR__, 2).'/config/ziggy.php';

    expect($manifest['only'])->toContain($routeName);
})->with([
    'administrator search' => ['administrators.search'],
    'administrator results' => ['administrators.search.results'],
    'faculty class search' => ['faculty.search.classes'],
    'faculty student search' => ['faculty.search.students'],
    'faculty results' => ['faculty.search.results'],
    'faculty student details' => ['faculty.students.show'],
    'student search' => ['student.search'],
    'student results' => ['student.search.results'],
]);

it('exposes administrator user management routes in the frontend manifest', function (string $routeName): void {
    $manifest = require dirname(__DIR__, 2).'/config/ziggy.php';

    expect($manifest['only'])->toContain($routeName);
})->with([
    'user directory' => ['administrators.users.index'],
    'create user' => ['administrators.users.create'],
    'store user' => ['administrators.users.store'],
    'user details' => ['administrators.users.show'],
    'edit user' => ['administrators.users.edit'],
    'update user' => ['administrators.users.update'],
    'delete user' => ['administrators.users.destroy'],
    'update account access' => ['administrators.users.access.update'],
    'revoke sessions' => ['administrators.users.sessions.destroy'],
    'revoke token' => ['administrators.users.tokens.destroy'],
    'impersonate user' => ['administrators.users.impersonate'],
    'stop impersonating' => ['administrators.users.stop-impersonating'],
    'reset password' => ['administrators.users.reset-password'],
    'verify email' => ['administrators.users.verify-email'],
]);

it('exposes the admissions inbox routes in the frontend manifest', function (string $routeName): void {
    $manifest = require dirname(__DIR__, 2).'/config/ziggy.php';

    expect($manifest['only'])->toContain($routeName);
})->with([
    'applicants inbox' => ['administrators.enrollments.applicants'],
    'applicant dossier' => ['administrators.enrollments.applicants.show'],
    'applicant document' => ['administrators.enrollments.applicants.document'],
    'cancel application' => ['administrators.enrollments.applicants.cancel'],
    'preview cancellation' => ['administrators.enrollments.applicants.cancel.preview'],
    'reverse cancellation' => ['administrators.enrollments.applicants.cancel.revert'],
]);

/**
 * A route name that is missing from the manifest makes Ziggy throw. Because the
 * throw happens before the request is sent, the failure is silent in the network
 * tab and the UI simply stops responding, so every name the frontend resolves is
 * checked against the manifest here.
 */
it('exposes every route the frontend resolves in the manifest', function (): void {
    $manifest = require dirname(__DIR__, 2).'/config/ziggy.php';
    $allowed = array_flip($manifest['only']);

    $frontend = dirname(__DIR__, 2).'/resources/js';
    $pattern = '/\broute\(\s*([\'"])([a-z0-9_.-]+)\1/';

    $missing = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($frontend)) as $file) {
        if (! $file->isFile()) {
            continue;
        }

        if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (preg_match_all($pattern, $contents, $matches) === 0) {
            continue;
        }

        foreach ($matches[2] as $name) {
            if (! isset($allowed[$name])) {
                $missing[$name] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname());
            }
        }
    }

    expect($missing)->toBe([], 'Every route() name used in the frontend must be listed in config/ziggy.php, otherwise Ziggy throws before the request is sent.');
});
