<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GradingPolicy;
use App\Models\School;
use App\Models\User;
use App\Support\SystemManagementPermissions;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function gradingPolicyAdministrator(): array
{
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    foreach ([SystemManagementPermissions::viewPermission('grading'), SystemManagementPermissions::updatePermission('grading')] as $permissionName) {
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        $user->givePermissionTo($permissionName);
    }

    Session::put('current_school_id', $school->id);

    return [$user, $school];
}

it('publishes a school scoped policy with configurable bands and components', function (): void {
    [$user, $school] = gradingPolicyAdministrator();

    actingAs($user)
        ->put(route('administrators.system-management.grading.update'), [
            'name' => 'UK-style assessment policy',
            'input_type' => 'numeric',
            'numeric_min' => 0,
            'numeric_max' => 20,
            'direction' => 'higher_is_better',
            'decimal_places' => 1,
            'include_failed_in_gwa' => true,
            'excluded_keywords' => [],
            'excluded_subject_ids' => [],
            'bands' => [
                ['id' => 'pass', 'label' => 'Pass', 'symbol' => null, 'min' => 10, 'max' => 20, 'outcome' => 'pass', 'quality_points' => 1, 'color' => 'success', 'sort_order' => 0],
                ['id' => 'fail', 'label' => 'Fail', 'symbol' => null, 'min' => 0, 'max' => 9.999, 'outcome' => 'fail', 'quality_points' => 0, 'color' => 'destructive', 'sort_order' => 1],
            ],
            'components' => [
                ['id' => 'coursework', 'key' => 'coursework', 'label' => 'Coursework', 'weight' => 40, 'required' => true, 'sort_order' => 0],
                ['id' => 'exam', 'key' => 'exam', 'label' => 'Exam', 'weight' => 60, 'required' => true, 'sort_order' => 1],
            ],
        ])
        ->assertRedirect();

    $policy = GradingPolicy::query()->with('activeVersion')->where('school_id', $school->id)->firstOrFail();

    expect($policy->name)->toBe('UK-style assessment policy')
        ->and($policy->activeVersion?->version)->toBe(1)
        ->and($policy->activeVersion?->configuration['numeric_max'])->toEqual(20)
        ->and($policy->activeVersion?->configuration['components'])->toHaveCount(2);
});

it('keeps a published policy version immutable by publishing a successor', function (): void {
    [$user, $school] = gradingPolicyAdministrator();

    $payload = [
        'name' => 'Policy', 'input_type' => 'numeric', 'numeric_min' => 0, 'numeric_max' => 100, 'direction' => 'higher_is_better', 'decimal_places' => 2,
        'include_failed_in_gwa' => true, 'excluded_keywords' => [], 'excluded_subject_ids' => [],
        'bands' => [
            ['id' => 'pass', 'label' => 'Pass', 'symbol' => null, 'min' => 75, 'max' => 100, 'outcome' => 'pass', 'quality_points' => null, 'color' => 'success', 'sort_order' => 0],
            ['id' => 'fail', 'label' => 'Fail', 'symbol' => null, 'min' => 0, 'max' => 74.999, 'outcome' => 'fail', 'quality_points' => null, 'color' => 'destructive', 'sort_order' => 1],
        ],
        'components' => [
            ['id' => 'prelim', 'key' => 'prelim', 'label' => 'Prelim', 'weight' => 30, 'required' => true, 'sort_order' => 0],
            ['id' => 'midterm', 'key' => 'midterm', 'label' => 'Midterm', 'weight' => 30, 'required' => true, 'sort_order' => 1],
            ['id' => 'final', 'key' => 'final', 'label' => 'Final', 'weight' => 40, 'required' => true, 'sort_order' => 2],
        ],
    ];

    actingAs($user)->put(route('administrators.system-management.grading.update'), $payload)->assertRedirect();
    $firstVersion = GradingPolicy::query()->where('school_id', $school->id)->firstOrFail()->activeVersion;

    $payload['bands'][0]['min'] = 60;
    $payload['bands'][1]['max'] = 59.999;

    actingAs($user)->put(route('administrators.system-management.grading.update'), $payload)->assertRedirect();
    $policy = GradingPolicy::query()->with('versions')->where('school_id', $school->id)->firstOrFail();

    expect($policy->versions)->toHaveCount(2)
        ->and($firstVersion?->fresh()->configuration['bands'][0]['min'])->toEqual(75)
        ->and($policy->activeVersion?->configuration['bands'][0]['min'])->toEqual(60);
});
