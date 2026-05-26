<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineExecutionService;
use App\Services\EnrollmentPipelineService;

it('returns default enrollment pipeline when no settings are stored', function () {
    GeneralSetting::query()->delete();

    $pipeline = app(EnrollmentPipelineService::class)->getConfiguration();

    expect($pipeline['steps'])->toHaveCount(3)
        ->and($pipeline['schema_version'])->toBe(2)
        ->and($pipeline['entry_step_key'])->toBe('pending')
        ->and($pipeline['completion_step_key'])->toBe('payment_verification')
        ->and($pipeline['pending_status'])->toBe('Pending')
        ->and($pipeline['cashier_verified_status'])->toBe('Verified By Cashier')
        ->and($pipeline['steps'][0]['node_actions'][0]['type'])->toBe('change_status')
        ->and($pipeline['steps'][0]['node_conditions'])->toBe([]);
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
                        'node_conditions' => [
                            [
                                'type' => 'complete_student_profile',
                                'order' => 1,
                                'config' => ['required_fields' => ['first_name', 'last_name', 'email']],
                            ],
                        ],
                        'node_actions' => [
                            ['type' => 'change_status', 'order' => 2, 'config' => []],
                            ['type' => 'send_email', 'order' => 1, 'config' => ['template' => 'enrollment']],
                        ],
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
        ->and($pipeline['schema_version'])->toBe(2)
        ->and($pipeline['entry_step_key'])->toBe('initial_review')
        ->and($pipeline['completion_step_key'])->toBe('final_release')
        ->and($pipeline['pending_status'])->toBe('Awaiting Department Review')
        ->and($pipeline['cashier_verified_status'])->toBe('Awaiting Payment Validation')
        ->and($pipeline['steps'])->toHaveCount(3)
        ->and($pipeline['steps'][0]['node_actions'][0]['type'])->toBe('send_email')
        ->and($pipeline['steps'][0]['node_actions'][1]['type'])->toBe('change_status')
        ->and($pipeline['steps'][0]['node_conditions'][0]['type'])->toBe('complete_student_profile');
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

it('maps legacy actions to node actions when node actions are missing', function () {
    GeneralSetting::query()->delete();

    $service = app(EnrollmentPipelineService::class);
    $sanitized = $service->sanitizeForStorage([
        'steps' => [
            [
                'key' => 'first',
                'status' => 'Pending',
                'label' => 'First',
                'color' => 'blue',
                'action_type' => 'standard',
                'actions' => ['advance_status'],
            ],
            [
                'key' => 'second',
                'status' => 'Verified',
                'label' => 'Second',
                'color' => 'green',
                'action_type' => 'department_verification',
                'actions' => ['department_verification'],
            ],
        ],
    ]);

    expect($sanitized['steps'][0]['node_actions'][0]['type'])->toBe('change_status')
        ->and($sanitized['steps'][0]['node_actions'][0]['config']['status'])->toBe('Pending')
        ->and($sanitized['steps'][1]['node_actions'][0]['type'])->toBe('department_verification')
        ->and($sanitized['steps'][0]['node_conditions'])->toBe([]);
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

it('normalizes legacy pipeline actions into structured node actions', function () {
    GeneralSetting::query()->delete();

    $sanitized = app(EnrollmentPipelineService::class)->sanitizeForStorage([
        'submitted_label' => 'Submitted',
        'entry_step_key' => 'pending',
        'completion_step_key' => 'payment_verification',
        'steps' => [
            [
                'key' => 'pending',
                'status' => 'Pending',
                'label' => 'Pending',
                'color' => 'yellow',
                'allowed_roles' => [],
                'action_type' => 'standard',
                'actions' => ['advance_status'],
            ],
            [
                'key' => 'payment_verification',
                'status' => 'Verified By Cashier',
                'label' => 'Payment Verification',
                'color' => 'green',
                'allowed_roles' => [],
                'action_type' => 'cashier_verification',
                'actions' => ['cashier_verification'],
            ],
        ],
    ]);

    expect($sanitized['schema_version'])->toBe(2)
        ->and($sanitized['steps'][0]['actions'])->toBe(['advance_status'])
        ->and($sanitized['steps'][0]['node_actions'][0]['type'])->toBe('change_status')
        ->and($sanitized['steps'][1]['node_actions'][0]['type'])->toBe('cashier_verification')
        ->and($sanitized['steps'][0]['node_conditions'])->toBe([]);
});

it('keeps ordered structured node actions and conditions', function () {
    $sanitized = app(EnrollmentPipelineService::class)->sanitizeForStorage([
        'submitted_label' => 'Submitted',
        'steps' => [
            [
                'key' => 'profile_review',
                'status' => 'Profile Reviewed',
                'label' => 'Profile Review',
                'color' => 'blue',
                'allowed_roles' => [],
                'action_type' => 'standard',
                'actions' => ['advance_status'],
                'node_actions' => [
                    ['type' => 'send_notification', 'order' => 20, 'config' => ['title' => 'Reviewed']],
                    ['type' => 'change_status', 'order' => 10, 'config' => []],
                ],
                'node_conditions' => [
                    [
                        'type' => 'complete_student_profile',
                        'order' => 10,
                        'config' => ['required_fields' => ['first_name', 'email']],
                        'message' => 'Complete profile first.',
                    ],
                ],
            ],
        ],
    ]);

    expect($sanitized['steps'][0]['node_actions'])->toHaveCount(2)
        ->and($sanitized['steps'][0]['node_actions'][0]['type'])->toBe('change_status')
        ->and($sanitized['steps'][0]['node_actions'][1]['type'])->toBe('send_notification')
        ->and($sanitized['steps'][0]['node_conditions'][0]['type'])->toBe('complete_student_profile')
        ->and($sanitized['steps'][0]['node_conditions'][0]['message'])->toBe('Complete profile first.');
});

it('derives the legacy action type from structured verification actions', function () {
    $sanitized = app(EnrollmentPipelineService::class)->sanitizeForStorage([
        'submitted_label' => 'Submitted',
        'steps' => [
            [
                'key' => 'review',
                'status' => 'Department Cleared',
                'label' => 'Department Review',
                'color' => 'blue',
                'allowed_roles' => [],
                'action_type' => 'standard',
                'actions' => ['advance_status'],
                'node_actions' => [
                    ['type' => 'department_verification', 'order' => 1, 'config' => []],
                ],
            ],
        ],
    ]);

    expect($sanitized['steps'][0]['action_type'])->toBe('department_verification');
});

it('blocks node execution when required student profile fields are missing', function () {
    $student = Student::factory()->create(['email' => null]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Pending',
    ]);

    $result = app(EnrollmentPipelineExecutionService::class)->execute($enrollment, [
        'status' => 'Profile Reviewed',
        'label' => 'Profile Review',
        'actions' => ['advance_status'],
        'node_actions' => [['type' => 'change_status', 'order' => 1, 'config' => []]],
        'node_conditions' => [[
            'type' => 'complete_student_profile',
            'order' => 1,
            'config' => ['required_fields' => ['email']],
            'message' => 'Complete profile first.',
        ]],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Complete profile first')
        ->and($enrollment->refresh()->status)->toBe('Pending');
});

it('executes a change status node action after conditions pass', function () {
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Pending',
    ]);

    $result = app(EnrollmentPipelineExecutionService::class)->execute($enrollment, [
        'status' => 'Profile Reviewed',
        'label' => 'Profile Review',
        'actions' => ['advance_status'],
        'node_actions' => [['type' => 'change_status', 'order' => 1, 'config' => []]],
        'node_conditions' => [[
            'type' => 'complete_student_profile',
            'order' => 1,
            'config' => ['required_fields' => ['email']],
        ]],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($enrollment->refresh()->status)->toBe('Profile Reviewed');
});

it('executes a configured change status value for legacy status compatibility', function () {
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Pending',
    ]);

    $result = app(EnrollmentPipelineExecutionService::class)->execute($enrollment, [
        'status' => 'Profile Reviewed',
        'label' => 'Profile Review',
        'actions' => ['advance_status'],
        'node_actions' => [
            [
                'type' => 'change_status',
                'order' => 1,
                'config' => ['status' => 'Waiting For Cashier'],
            ],
        ],
        'node_conditions' => [],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['action_results'][0]['status'])->toBe('Waiting For Cashier')
        ->and($enrollment->refresh()->status)->toBe('Waiting For Cashier');
});
