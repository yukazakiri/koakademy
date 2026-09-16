<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers administrator and portal routes when portal host is empty', function (): void {
    config([
        'app.portal_host' => '',
        'app.portal_host_aliases' => [],
        'app.admin_host' => '',
    ]);

    // Re-evaluate routes/web.php with empty hosts
    require base_path('routes/web.php');

    expect(Route::has('administrators.registrar.analytics.index'))->toBeTrue()
        ->and(Route::has('administrators.enrollments.index'))->toBeTrue()
        ->and(Route::has('admin.system-management.brand.appearance'))->toBeTrue()
        ->and(Route::has('finance-documents.verify'))->toBeTrue();
});

it('registers portal routes for each configured alias', function (): void {
    config([
        'app.portal_host' => 'school.example',
        'app.portal_host_aliases' => ['portal.school.example'],
    ]);

    require base_path('routes/web.php');

    expect(Route::has('administrators.registrar.analytics.index'))->toBeTrue();
});
