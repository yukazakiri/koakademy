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
