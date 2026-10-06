<?php

declare(strict_types=1);

use App\Enrollment\EnrollmentPolicyCompiler;
use App\Enrollment\EnrollmentPolicyPreset;
use App\Enrollment\EnrollmentPolicyRegistry;
use Illuminate\Validation\ValidationException;

it('compiles the bundled preset into a stable checksum', function (): void {
    $compiler = app(EnrollmentPolicyCompiler::class);

    $first = $compiler->compile([['version_id' => 10, 'configuration' => EnrollmentPolicyPreset::standard()]]);
    $second = $compiler->compile([['version_id' => 10, 'configuration' => EnrollmentPolicyPreset::standard()]]);

    expect($first->checksum)->toHaveLength(64)->toBe($second->checksum)
        ->and($first->sourceVersionIds)->toBe([10]);
});

it('compiles every operator preset without custom code', function (): void {
    $compiler = app(EnrollmentPolicyCompiler::class);

    foreach (array_keys(EnrollmentPolicyPreset::catalog()) as $preset) {
        expect($compiler->compile([[
            'version_id' => 1,
            'configuration' => EnrollmentPolicyPreset::configuration($preset),
        ]])->checksum)->toHaveLength(64);
    }
});

it('merges keyed rules while replacing workflow atomically', function (): void {
    $compiler = app(EnrollmentPolicyCompiler::class);
    $base = EnrollmentPolicyPreset::standard();
    $override = [
        'schema_version' => 1,
        'rules' => [
            ['key' => 'enrollment_channels', 'enabled' => false],
            ['key' => 'types', 'handler' => 'eligibility.student_type', 'configuration' => ['allowed' => ['college']]],
        ],
        'workflow' => $base['workflow'],
    ];

    $compiled = $compiler->compile([
        ['version_id' => 1, 'configuration' => $base],
        ['version_id' => 2, 'configuration' => $override],
    ]);

    expect(collect($compiled->configuration['rules'])->pluck('key')->all())
        ->toBe(['duplicate_period', 'types'])
        ->and($compiled->sourceVersionIds)->toBe([1, 2]);
});

it('rejects cycles and unknown handlers before publication', function (): void {
    $compiler = app(EnrollmentPolicyCompiler::class);
    $cyclic = EnrollmentPolicyPreset::standard();
    array_unshift($cyclic['workflow']['steps'][1]['transitions'], [
        'key' => 'again', 'to' => 'submitted', 'fallback' => false,
        'conditions' => [['handler' => 'eligibility.clearance', 'configuration' => ['fact_key' => 'cleared']]],
    ]);

    expect(fn () => $compiler->compile([['version_id' => 1, 'configuration' => $cyclic]]))
        ->toThrow(ValidationException::class, 'cycles');

    $unknown = EnrollmentPolicyPreset::standard();
    $unknown['rules'][] = ['key' => 'unsafe', 'handler' => 'Vendor\\ExecutableRule', 'configuration' => []];

    expect(fn () => $compiler->compile([['version_id' => 1, 'configuration' => $unknown]]))
        ->toThrow(ValidationException::class, 'Unknown enrollment rule handler');

    $script = EnrollmentPolicyPreset::standard();
    $script['billing']['configuration']['script'] = 'return total * 0.5';

    expect(fn () => $compiler->compile([['version_id' => 1, 'configuration' => $script]]))
        ->toThrow(ValidationException::class, 'cannot be imported');

    $url = EnrollmentPolicyPreset::standard();
    $url['requirements'][] = ['key' => 'unsafe', 'label' => 'Unsafe', 'description' => 'https://example.com/webhook'];

    expect(fn () => $compiler->compile([['version_id' => 1, 'configuration' => $url]]))
        ->toThrow(ValidationException::class, 'Arbitrary URLs');
});

it('reports source provenance for inherited keyed and atomic settings', function (): void {
    $compiler = app(EnrollmentPolicyCompiler::class);
    $base = EnrollmentPolicyPreset::standard();
    $override = [
        'schema_version' => 1,
        'rules' => [[
            'key' => 'student_types',
            'handler' => 'eligibility.student_type',
            'configuration' => ['allowed' => ['college']],
        ]],
        'billing' => $base['billing'],
    ];

    $compiled = $compiler->compile([
        [
            'version_id' => 11,
            'version' => 1,
            'policy_id' => 1,
            'policy_name' => 'Global foundation',
            'scope' => [],
            'configuration' => $base,
        ],
        [
            'version_id' => 22,
            'version' => 2,
            'policy_id' => 2,
            'policy_name' => 'College override',
            'scope' => ['student_type' => 'College'],
            'configuration' => $override,
        ],
    ]);

    expect($compiled->sourceLayers)->toHaveCount(2)
        ->and($compiled->sourceMap['rules.duplicate_period']['policy_name'])->toBe('Global foundation')
        ->and($compiled->sourceMap['rules.student_types']['policy_name'])->toBe('College override')
        ->and($compiled->sourceMap['billing']['version_id'])->toBe(22);
});

it('publishes rich operator metadata and validates conditional fields only when visible', function (): void {
    $manifest = app(EnrollmentPolicyRegistry::class)->manifest();
    $dateWindow = $manifest['rules']['availability.date_window']['operator_schema'];

    expect($dateWindow)
        ->toHaveKeys(['description', 'what_it_does', 'impact', 'example', 'docs_anchor', 'fields'])
        ->and($dateWindow['fields'][0])->toHaveKeys(['description', 'placeholder', 'example', 'recommended', 'min', 'max', 'step']);

    $compiler = app(EnrollmentPolicyCompiler::class);
    $hiddenValue = EnrollmentPolicyPreset::standard();
    $hiddenValue['billing']['configuration'] = [
        'discount_percentage' => 0,
        'minimum_payment_type' => 'none',
    ];
    expect($compiler->compile([['version_id' => 1, 'configuration' => $hiddenValue]])->checksum)->toHaveLength(64);

    $visibleValue = $hiddenValue;
    $visibleValue['billing']['configuration']['minimum_payment_type'] = 'fixed';
    expect(fn () => $compiler->compile([['version_id' => 1, 'configuration' => $visibleValue]]))
        ->toThrow(ValidationException::class, 'required');
});
