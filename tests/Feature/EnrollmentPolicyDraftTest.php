<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enrollment\EnrollmentPolicyManager;
use App\Models\EnrollmentPolicy;
use App\Models\User;

it('persists modified sub-actions array and reordered sequence in policy draft', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $manager = app(EnrollmentPolicyManager::class);

    $policy = EnrollmentPolicy::first() ?? $manager->create(['name' => 'Enrollment Studio Policy'], $user);
    $draft = $policy->versions()->draft()->first();
    expect($draft)->not->toBeNull();

    $config = $draft->configuration;
    $steps = data_get($config, 'workflow.steps', []);
    expect($steps)->not->toBeEmpty();

    // 1. Persist sub-actions with custom confirmation email configuration
    $steps[0]['actions'] = [
        [
            'key' => 'notify_academic',
            'handler' => 'enrollment.notify',
            'configuration' => ['notification' => 'academic_verified'],
        ],
        [
            'key' => 'calc_tuition',
            'handler' => 'enrollment.calculate_tuition',
            'configuration' => [],
        ],
        [
            'key' => 'reserve_classes',
            'handler' => 'enrollment.assign_classes',
            'configuration' => ['mode' => 'runtime_payload'],
        ],
    ];
    $config['workflow']['steps'] = $steps;

    $response = $this->actingAs($user)->put("/administrators/system-management/enrollment-policies/{$policy->id}/draft", [
        'configuration' => $config,
        'change_notes' => 'Configured sub-actions pipeline via studio canvas',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $draft->refresh();
    $persisted = data_get($draft->configuration, 'workflow.steps.0.actions');
    expect($persisted)->toHaveCount(3)
        ->and($persisted[0]['handler'])->toBe('enrollment.notify')
        ->and($persisted[0]['configuration']['notification'])->toBe('academic_verified')
        ->and($persisted[1]['handler'])->toBe('enrollment.calculate_tuition')
        ->and($persisted[2]['handler'])->toBe('enrollment.assign_classes');

    // 2. Reorder actions (swap #1 and #2) and persist
    $steps[0]['actions'] = [
        $persisted[1],
        $persisted[0],
        $persisted[2],
    ];
    $config['workflow']['steps'] = $steps;

    $swapResponse = $this->actingAs($user)->put("/administrators/system-management/enrollment-policies/{$policy->id}/draft", [
        'configuration' => $config,
        'change_notes' => 'Swapped order of calculate tuition and notify student',
    ]);

    $swapResponse->assertSessionHasNoErrors();
    $swapResponse->assertRedirect();

    $draft->refresh();
    $reordered = data_get($draft->configuration, 'workflow.steps.0.actions');
    expect($reordered)->toHaveCount(3)
        ->and($reordered[0]['handler'])->toBe('enrollment.calculate_tuition')
        ->and($reordered[1]['handler'])->toBe('enrollment.notify')
        ->and($reordered[1]['configuration']['notification'])->toBe('academic_verified')
        ->and($reordered[2]['handler'])->toBe('enrollment.assign_classes');
});
