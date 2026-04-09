<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Services\EnrollmentPipelineService;

it('returns default enrollment pipeline when no settings are stored', function () {
    GeneralSetting::query()->delete();

    $pipeline = app(EnrollmentPipelineService::class)->getConfiguration();

    expect($pipeline['steps'])->toHaveCount(3)
        ->and($pipeline['entry_step_key'])->toBe('pending')
        ->and($pipeline['completion_step_key'])->toBe('payment_verification')
        ->and($pipeline['pending_status'])->toBe('Pending')
        ->and($pipeline['cashier_verified_status'])->toBe('Verified By Cashier');
});

it('returns configured enrollment pipeline from general settings', function () {
    GeneralSetting::query()->delete();

    GeneralSetting::query()->create([
        'site_name' => 'Test',
        'more_configs' => [
            'enrollment_pipeline' => [
                'submitted_label' => 'Application Submitted',
                'entry_step_key' => 'initial_review',
                'completion_step_key' => 'final_release',
                'steps' => [
                    [
                        'key' => 'initial_review',
                        'status' => 'Awaiting Department Review',
                        'label' => 'Initial Review',
                        'color' => 'amber',
                        'allowed_roles' => ['registrar'],
                        'action_type' => 'standard',
                    ],
                    [
                        'key' => 'finance_review',
                        'status' => 'Awaiting Payment Validation',
                        'label' => 'Finance Validation',
                        'color' => 'blue',
                        'allowed_roles' => ['cashier'],
                        'action_type' => 'cashier_verification',
                    ],
                    [
                        'key' => 'final_release',
                        'status' => 'Enrollment Complete',
                        'label' => 'Completed',
                        'color' => 'green',
                        'allowed_roles' => [],
                        'action_type' => 'standard',
                    ],
                ],
            ],
        ],
    ]);

    $pipeline = app(EnrollmentPipelineService::class)->getConfiguration();

    expect($pipeline['submitted_label'])->toBe('Application Submitted')
        ->and($pipeline['entry_step_key'])->toBe('initial_review')
        ->and($pipeline['completion_step_key'])->toBe('final_release')
        ->and($pipeline['pending_status'])->toBe('Awaiting Department Review')
        ->and($pipeline['cashier_verified_status'])->toBe('Awaiting Payment Validation')
        ->and($pipeline['steps'])->toHaveCount(3);
});

it('resets duplicate configured statuses to defaults', function () {
    GeneralSetting::query()->delete();

    $service = app(EnrollmentPipelineService::class);

    $sanitized = $service->sanitizeForStorage([
        'steps' => [
            ['key' => 'step_1', 'status' => 'Same Status', 'label' => 'One', 'color' => 'blue', 'action_type' => 'standard'],
            ['key' => 'step_2', 'status' => 'Same Status', 'label' => 'Two', 'color' => 'green', 'action_type' => 'standard'],
        ],
    ]);

    expect($sanitized['steps'])->toHaveCount(3)
        ->and($sanitized['pending_status'])->toBe('Pending')
        ->and($sanitized['cashier_verified_status'])->toBe('Verified By Cashier');
});

it('sanitizes and persists enrollment stats configuration', function () {
    GeneralSetting::query()->delete();

    $service = app(EnrollmentPipelineService::class);
    $sanitized = $service->sanitizeStatsForStorage([
        'cards' => [
            [
                'key' => 'pending_queue',
                'label' => 'Pending Queue',
                'metric' => 'status_count',
                'statuses' => ['Pending', 'Invalid Status'],
                'color' => 'amber',
            ],
        ],
    ]);

    expect($sanitized['cards'])->toHaveCount(1)
        ->and($sanitized['cards'][0]['label'])->toBe('Pending Queue')
        ->and($sanitized['cards'][0]['statuses'])->toBe(['Pending']);
});

it('detects when enrollment workflow setup is missing', function () {
    GeneralSetting::query()->delete();

    $hasSetup = app(EnrollmentPipelineService::class)->hasWorkflowSetup();

    expect($hasSetup)->toBeFalse();
});

it('detects when enrollment workflow setup exists', function () {
    GeneralSetting::query()->delete();

    GeneralSetting::query()->create([
        'site_name' => 'Test',
        'more_configs' => [
            'enrollment_pipeline' => [
                'steps' => [
                    [
                        'key' => 'initial_review',
                        'status' => 'Awaiting Department Review',
                        'label' => 'Initial Review',
                        'color' => 'amber',
                        'allowed_roles' => ['registrar'],
                        'action_type' => 'standard',
                    ],
                ],
            ],
        ],
    ]);

    $hasSetup = app(EnrollmentPipelineService::class)->hasWorkflowSetup();

    expect($hasSetup)->toBeTrue();
});
