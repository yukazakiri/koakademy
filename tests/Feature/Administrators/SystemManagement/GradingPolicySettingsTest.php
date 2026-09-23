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
    $school = School::factory()->create(['description' => 'Test school description']);
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

it('publishes and persists configurable GWA calculation settings', function (): void {
    [$user, $school] = gradingPolicyAdministrator();

    $payload = [
        'name' => 'Custom Institutional Policy',
        'input_type' => 'numeric',
        'numeric_min' => 1,
        'numeric_max' => 5,
        'direction' => 'lower_is_better',
        'decimal_places' => 4,
        'include_failed_in_gwa' => true,
        'gwa_formula' => 'weighted_subjects',
        'gwa_subject_divisor_basis' => 'enrolled_subjects',
        'gwa_calculation_metric' => 'quality_points',
        'retake_strategy' => 'highest',
        'include_credited_in_gwa' => false,
        'zero_is_dropped' => true,
        'treat_incomplete_as' => 'fail',
        'exclude_zero_unit_subjects' => true,
        'excluded_keywords' => ['NSTP', 'PE'],
        'excluded_subject_ids' => [],
        'bands' => [
            ['id' => 'pass', 'label' => 'Passed', 'symbol' => null, 'min' => 1, 'max' => 3, 'outcome' => 'pass', 'quality_points' => 3.0, 'color' => 'success', 'sort_order' => 0],
            ['id' => 'fail', 'label' => 'Failed', 'symbol' => null, 'min' => 3.0001, 'max' => 5, 'outcome' => 'fail', 'quality_points' => 0.0, 'color' => 'destructive', 'sort_order' => 1],
        ],
        'components' => [
            ['id' => 'midterm', 'key' => 'midterm', 'label' => 'Midterm', 'weight' => 50, 'required' => true, 'sort_order' => 0],
            ['id' => 'final', 'key' => 'final', 'label' => 'Final', 'weight' => 50, 'required' => true, 'sort_order' => 1],
        ],
    ];

    actingAs($user)->put(route('administrators.system-management.grading.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $policy = GradingPolicy::query()->with('activeVersion')->where('school_id', $school->id)->firstOrFail();
    $config = $policy->activeVersion?->configuration;

    expect($config['gwa_formula'])->toBe('weighted_subjects')
        ->and($config['gwa_subject_divisor_basis'])->toBe('enrolled_subjects')
        ->and($config['gwa_calculation_metric'])->toBe('quality_points')
        ->and($config['retake_strategy'])->toBe('highest')
        ->and($config['include_credited_in_gwa'])->toBeFalse()
        ->and($config['zero_is_dropped'])->toBeTrue()
        ->and($config['treat_incomplete_as'])->toBe('fail')
        ->and($config['exclude_zero_unit_subjects'])->toBeTrue();
});

it('applies retake_strategy and zero_is_dropped to checklist display', function (): void {
    [$user, $school] = gradingPolicyAdministrator();

    $gradingSystem = app(App\Services\GradingSystemService::class);
    $gradingSystem->publishForSchool($school, [
        ...$gradingSystem->defaults(),
        'retake_strategy' => 'latest',
        'zero_is_dropped' => false,
    ], author: $user);

    $course = App\Models\Course::factory()->create();
    $student = App\Models\Student::factory()->create(['course_id' => $course->id, 'school_id' => $school->id]);
    $subject = App\Models\Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);

    $studentEnrollmentPast = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2022 - 2023',
        'semester' => 1,
    ]);

    App\Models\SubjectEnrollment::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollmentPast->id,
        'subject_id' => $subject->id,
        'grade' => 70.0,
        'academic_year' => 1,
        'school_year' => '2022 - 2023',
        'semester' => 1,
        'classification' => App\Enums\SubjectEnrolledEnum::INTERNAL->value,
    ]);

    $studentEnrollmentCurrent = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2023 - 2024',
        'semester' => 1,
    ]);

    App\Models\SubjectEnrollment::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollmentCurrent->id,
        'subject_id' => $subject->id,
        'grade' => 88.0,
        'academic_year' => 1,
        'school_year' => '2023 - 2024',
        'semester' => 1,
        'classification' => App\Enums\SubjectEnrolledEnum::INTERNAL->value,
    ]);

    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->has('student.checklist.0.semesters.0.subjects', 1)
            ->where('student.checklist.0.semesters.0.subjects.0.grade', 88)
            ->where('student.checklist.0.semesters.0.subjects.0.status', 'Completed')
            ->where('student.checklist.0.semesters.0.subjects.0.is_enrolled', true)
        );

    // Switch policy to first attempt
    $gradingSystem->publishForSchool($school, [
        ...$gradingSystem->defaults(),
        'retake_strategy' => 'first',
        'zero_is_dropped' => false,
    ], author: $user);

    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('student.checklist.0.semesters.0.subjects.0.grade', 70)
            ->where('student.checklist.0.semesters.0.subjects.0.status', 'Failed')
        );

    // Update grade to 0 and verify zero_is_dropped
    $latestEnrollment = App\Models\SubjectEnrollment::where('id', $studentEnrollmentCurrent->id)->first()
        ?? App\Models\SubjectEnrollment::where('student_id', $student->id)->orderByDesc('id')->first();
    $latestEnrollment->update(['grade' => 0.0, 'grade_outcome' => null]);

    // zero_is_dropped = false -> Status should be Failed
    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('student.checklist.0.semesters.0.subjects.0.status', 'Failed')
        );

    // Switch policy to zero_is_dropped = true -> Status should be Dropped
    $gradingSystem->publishForSchool($school, [
        ...$gradingSystem->defaults(),
        'retake_strategy' => 'latest',
        'zero_is_dropped' => true,
    ], author: $user);

    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->where('student.checklist.0.semesters.0.subjects.0.status', 'Dropped')
        );
});
