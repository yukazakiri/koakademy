<?php

declare(strict_types=1);

use App\Enums\SubjectEnrolledEnum;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Subject;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

it('allows administrative users to view curriculum management pages', function (
    string $path,
    string $component,
    Closure $assertions
): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $courseType = CourseType::factory()->create();

    $primaryProgram = Course::factory()->create([
        'code' => 'BSCS',
        'curriculum_year' => '2024 - 2025',
        'is_active' => true,
        'course_type_id' => $courseType->id,
    ]);

    $secondaryProgram = Course::factory()->create([
        'code' => 'BSBA',
        'curriculum_year' => '2018 - 2019',
        'is_active' => false,
    ]);

    Subject::factory()->for($primaryProgram)->create([
        'code' => 'MATH101',
        'pre_riquisite' => ['ENG101'],
    ]);

    Subject::factory()->for($primaryProgram)->create([
        'code' => 'IT102',
    ]);

    Subject::factory()->for($secondaryProgram)->create([
        'code' => 'BUS201',
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators($path))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $assertions(
            $page->component($component, false)
                ->has('user')
        ));
})->with([
    'curriculum overview' => [
        '/administrators/curriculum',
        'administrators/curriculum/index',
        fn (AssertableInertia $page): AssertableInertia => $page
            ->has('stats')
            ->where('stats.programs', 2)
            ->where('stats.subjects', 3)
            ->has('versions', 2)
            ->has('departments'),
    ],
    'programs' => [
        '/administrators/curriculum/programs',
        'administrators/curriculum/programs',
        fn (AssertableInertia $page): AssertableInertia => $page
            ->has('stats')
            ->where('stats.programs', 2)
            ->where('stats.subjects', 3)
            ->where('stats.subjects_with_requisites', 1)
            ->has('versions', 2)
            ->has('programs', 2)
            ->has('departments'),
    ],
]);

it('shows program details with subject relationships', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $program = Course::factory()->create([
        'code' => 'BSCS',
    ]);

    Subject::factory()->for($program)->create([
        'code' => 'IT101',
        'academic_year' => 1,
        'semester' => 1,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators("/administrators/curriculum/programs/{$program->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/curriculum/programs/show', false)
            ->has('program')
            ->where('stats.subjects', 1)
            ->has('subjects', 1)
            ->has('subject_options', 1)
            ->has('classification_options')
        );
});

it('updates program details and manages subjects', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $department1 = \App\Models\Department::factory()->create(['code' => 'CBA']);
    $department2 = \App\Models\Department::factory()->create(['code' => 'CCS']);

    $courseType1 = CourseType::factory()->create();
    $courseType2 = CourseType::factory()->create();

    $program = Course::factory()->create([
        'code' => 'BSBA',
        'title' => 'Business Administration',
        'department_id' => $department1->id,
        'course_type_id' => $courseType1->id,
    ]);

    $prerequisite = Subject::factory()->for($program)->create([
        'code' => 'BUS100',
    ]);

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/curriculum/programs/{$program->id}"), [
            'code' => 'BSIT',
            'title' => 'Information Technology',
            'description' => 'Updated program description',
            'department_id' => $department2->id,
            'course_type_id' => $courseType2->id,
            'lec_per_unit' => 120,
            'lab_per_unit' => 80,
            'remarks' => 'Revised curriculum',
            'curriculum_year' => '2024 - 2025',
            'miscelaneous' => 3600,
        ])
        ->assertRedirect();

    expect($program->refresh())
        ->code->toBe('BSIT')
        ->title->toBe('Information Technology')
        ->department_id->toBe($department2->id);

    actingAs($user)
        ->post(portalUrlForAdministrators("/administrators/curriculum/programs/{$program->id}/subjects"), [
            'code' => 'IT201',
            'title' => 'Data Structures',
            'classification' => SubjectEnrolledEnum::CREDITED->value,
            'units' => 3,
            'lecture' => 2,
            'laboratory' => 1,
            'academic_year' => 2,
            'semester' => 1,
            'group' => 'A',
            'is_credited' => true,
            'pre_riquisite' => [$prerequisite->id],
        ])
        ->assertRedirect();

    $subject = Subject::query()->where('code', 'IT201')->firstOrFail();

    expect($subject->course_id)->toBe($program->id);
    expect($subject->pre_riquisite)->toContain($prerequisite->id);

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/curriculum/programs/{$program->id}/subjects/{$subject->id}"), [
            'code' => 'IT201',
            'title' => 'Advanced Data Structures',
            'classification' => SubjectEnrolledEnum::CREDITED->value,
            'units' => 4,
            'lecture' => 3,
            'laboratory' => 1,
            'academic_year' => 2,
            'semester' => 2,
            'group' => 'B',
            'is_credited' => true,
            'pre_riquisite' => [$prerequisite->id],
        ])
        ->assertRedirect();

    expect($subject->refresh())
        ->title->toBe('Advanced Data Structures')
        ->semester->toBe(2);

    actingAs($user)
        ->delete(portalUrlForAdministrators("/administrators/curriculum/programs/{$program->id}/subjects/{$subject->id}"))
        ->assertRedirect();

    expect(Subject::query()->whereKey($subject->id)->exists())->toBeFalse();
});

it('toggles program active status', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $program = Course::factory()->create([
        'code' => 'BSCS',
        'is_active' => true,
    ]);

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/curriculum/programs/{$program->id}/toggle-status"))
        ->assertRedirect();

    expect($program->refresh())->is_active->toBeFalse();

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/curriculum/programs/{$program->id}/toggle-status"))
        ->assertRedirect();

    expect($program->refresh())->is_active->toBeTrue();
});
