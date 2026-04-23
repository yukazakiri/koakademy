<?php

declare(strict_types=1);

uses()->group('pdf');

test('pdf render profile resolves named profile options', function (): void {
    $options = \App\Support\PdfRenderProfile::resolve('assessment_form');

    expect($options)->toHaveKey('format', 'A4')
        ->and($options)->toHaveKey('landscape', true)
        ->and($options)->toHaveKey('print-background', true);
});

test('pdf render profile strips browsershot-only keys for non-browsershot driver', function (): void {
    config()->set('laravel-pdf.driver', 'dompdf');

    $options = \App\Support\PdfRenderProfile::resolve('student_list');

    expect($options)->not->toHaveKey('no-sandbox')
        ->and($options)->not->toHaveKey('disable-gpu')
        ->and($options)->not->toHaveKey('virtual-time-budget');
});

test('pdf render profile preserves browsershot-only keys for browsershot driver', function (): void {
    config()->set('laravel-pdf.driver', 'browsershot');

    $options = \App\Support\PdfRenderProfile::resolve('student_list');

    expect($options)->toHaveKey('no-sandbox', true)
        ->and($options)->toHaveKey('disable-gpu', true)
        ->and($options)->toHaveKey('virtual-time-budget', 10000);
});

test('pdf render profile merges with overrides', function (): void {
    config()->set('laravel-pdf.driver', 'dompdf');

    $options = \App\Support\PdfRenderProfile::resolveWithOverrides('assessment_form', [
        'landscape' => false,
        'custom-key' => 'value',
    ]);

    expect($options['landscape'])->toBeFalse()
        ->and($options['custom-key'])->toBe('value')
        ->and($options['format'])->toBe('A4');
});

test('pdf render profile throws for unknown profile', function (): void {
    \App\Support\PdfRenderProfile::resolve('nonexistent_profile');
})->throws(InvalidArgumentException::class);

test('pdf generation service merges profile options correctly', function (): void {
    $service = app(\App\Services\PdfGenerationService::class);

    // Use reflection to test the private resolveProfileOptions method
    $reflection = new ReflectionMethod($service, 'resolveProfileOptions');
    $reflection->setAccessible(true);

    config()->set('laravel-pdf.driver', 'browsershot');

    $resolved = $reflection->invoke($service, 'assessment_form', ['custom' => 'value']);

    expect($resolved)->toHaveKey('format', 'A4')
        ->and($resolved)->toHaveKey('landscape', true)
        ->and($resolved)->toHaveKey('custom', 'value');
});

test('pdf generation service resolves profile options without profile', function (): void {
    $service = app(\App\Services\PdfGenerationService::class);

    $reflection = new ReflectionMethod($service, 'resolveProfileOptions');
    $reflection->setAccessible(true);

    $resolved = $reflection->invoke($service, null, ['direct' => 'option']);

    expect($resolved)->toHaveKey('direct', 'option')
        ->and($resolved)->not->toHaveKey('format');
});

test('all registered profiles are resolvable', function (string $profileName): void {
    config()->set('laravel-pdf.driver', 'dompdf');

    $options = \App\Support\PdfRenderProfile::resolve($profileName);

    expect($options)->toBeArray();
})->with([
    'browsershot_headless',
    'assessment_form',
    'attendance_report',
    'timetable_landscape',
    'timetable_portrait',
    'enrollment_report',
    'student_soa',
    'student_list',
]);
