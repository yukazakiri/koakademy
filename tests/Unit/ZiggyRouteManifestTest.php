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
