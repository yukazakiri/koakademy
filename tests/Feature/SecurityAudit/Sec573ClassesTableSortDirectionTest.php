<?php

declare(strict_types=1);

use App\Models\Classes;

it('normalizes sort direction and scopes select in ClassesTable faculty_name sortable query', function (): void {
    $source = (string) file_get_contents(app_path('Filament/Resources/Classes/Tables/ClassesTable.php'));

    expect($source)
        ->not->toContain("orderByRaw(\"CONCAT(faculty.last_name, ', ', faculty.first_name) \".\$direction")
        ->toContain("mb_strtolower(\$direction) === 'desc' ? 'desc' : 'asc'")
        ->toContain("->select('classes.*')");

    // Verify executing the normalized query against SQLite succeeds without errors
    $query = Classes::query()
        ->leftJoin('faculty', 'classes.faculty_id', '=', 'faculty.id')
        ->select('classes.*');

    $safeDirection = mb_strtolower('desc; DROP TABLE users;--') === 'desc' ? 'desc' : 'asc';
    expect($safeDirection)->toBe('asc')
        ->and($query->orderByRaw("faculty.last_name {$safeDirection}")->get())->toBeEmpty();
});
