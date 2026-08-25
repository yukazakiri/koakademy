<?php

declare(strict_types=1);

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\School;
use App\Models\SchoolCurriculumCapability;
use App\Models\User;
use App\Services\CurriculumCapabilityResolver;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

it('uses the school configuration as a safe fallback until curriculum capabilities are explicitly saved', function (): void {
    $school = School::factory()->create([
        'school_level' => SchoolLevel::Elementary,
        'curriculum_framework' => CurriculumFramework::DepedMatatag,
    ]);

    $capability = app(CurriculumCapabilityResolver::class)->forSchool($school)->sole();

    expect($capability)
        ->toHaveKey('school_level', SchoolLevel::Elementary->value)
        ->toHaveKey('curriculum_framework', CurriculumFramework::DepedMatatag->value)
        ->toHaveKey('is_derived', true);
});

it('stores multiple supported pathways for a school without changing its primary level', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    $permissions = collect(['View:SystemManagementSchool', 'Update:SystemManagementSchool'])
        ->map(fn (string $name): Permission => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    $admin->givePermissionTo($permissions);
    $school = School::factory()->create(['school_level' => SchoolLevel::Elementary]);

    $this->actingAs($admin)
        ->put(route('administrators.system-management.schools.curriculum-capabilities.update', $school), [
            'capabilities' => [
                ['school_level' => SchoolLevel::Elementary->value, 'curriculum_framework' => CurriculumFramework::DepedMatatag->value],
                ['school_level' => SchoolLevel::TechnicalVocational->value, 'curriculum_framework' => CurriculumFramework::TesdaTr->value],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($school->refresh()->school_level)->toBe(SchoolLevel::Elementary)
        ->and(SchoolCurriculumCapability::query()->where('school_id', $school->id)->where('is_enabled', true)->count())->toBe(2);
});

it('allows administrators to create an elementary grade pathway without college-only fields', function (): void {
    $school = School::factory()->create([
        'school_level' => SchoolLevel::Elementary,
        'curriculum_framework' => CurriculumFramework::DepedMatatag,
    ]);
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $school->id,
    ]);
    $capability = SchoolCurriculumCapability::factory()->create([
        'school_id' => $school->id,
        'school_level' => SchoolLevel::Elementary,
        'curriculum_framework' => CurriculumFramework::DepedMatatag,
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.curriculum.programs.store'), [
            'code' => 'G1',
            'title' => 'Grade 1 Learning Pathway',
            'capability_id' => (string) $capability->id,
            'curriculum_kind' => 'grade_pathway',
            'curriculum_stage' => 'Grade 1',
            'description' => 'The Grade 1 learning area structure.',
            'curriculum_year' => '2026-2027',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Program created successfully.');

    $pathway = Course::query()->where('code', 'G1')->sole();

    expect($pathway)
        ->school_id->toBe($school->id)
        ->school_curriculum_capability_id->toBe($capability->id)
        ->curriculum_kind->toBe('grade_pathway')
        ->curriculum_stage->toBe('Grade 1')
        ->curriculum_framework->toBe(CurriculumFramework::DepedMatatag->value);
});

it('stores an institutional TESDA diploma with bundled qualifications and internship metadata', function (): void {
    $school = School::factory()->create([
        'school_level' => SchoolLevel::TechnicalVocational,
        'curriculum_framework' => CurriculumFramework::TesdaTr,
    ]);
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $school->id,
    ]);
    $capability = SchoolCurriculumCapability::factory()->create([
        'school_id' => $school->id,
        'school_level' => SchoolLevel::TechnicalVocational,
        'curriculum_framework' => CurriculumFramework::TesdaTr,
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.curriculum.programs.store'), [
            'code' => 'DCA-DIP',
            'title' => 'Diploma in Culinary Arts',
            'capability_id' => (string) $capability->id,
            'curriculum_kind' => 'tesda_qualification',
            'tesda_program_type' => 'diploma',
            'qualification_level' => 'Diploma',
            'duration_hours' => 1200,
            'duration_years' => 1,
            'internship_hours' => 600,
            'bundled_qualifications' => ['Cookery NC II', 'Bread & Pastry Production NC II'],
            'advanced_topics' => 'Advanced culinary techniques and kitchen operations.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Program created successfully.');

    $diploma = Course::query()->where('code', 'DCA-DIP')->sole();

    expect($diploma)
        ->school_id->toBe($school->id)
        ->school_curriculum_capability_id->toBe($capability->id)
        ->curriculum_kind->toBe('tesda_qualification')
        ->tesda_program_type->toBe('diploma')
        ->duration_hours->toBe(1200)
        ->duration_years->toBe('1.0')
        ->internship_hours->toBe(600)
        ->bundled_qualifications->toBe(['Cookery NC II', 'Bread & Pastry Production NC II'])
        ->advanced_topics->toContain('Advanced culinary techniques');

    $this->actingAs($admin)
        ->get(route('administrators.curriculum.programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('programs', fn ($programs): bool => collect($programs)->contains(fn (array $program): bool => $program['id'] === $diploma->id
                && $program['tesda_program_type'] === 'diploma'
                && $program['internship_hours'] === 600))
        );
});

it('rejects a pathway type that does not match the selected school capability', function (): void {
    $school = School::factory()->create([
        'school_level' => SchoolLevel::TechnicalVocational,
        'curriculum_framework' => CurriculumFramework::TesdaTr,
    ]);
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $school->id,
    ]);
    $capability = SchoolCurriculumCapability::factory()->create([
        'school_id' => $school->id,
        'school_level' => SchoolLevel::TechnicalVocational,
        'curriculum_framework' => CurriculumFramework::TesdaTr,
    ]);

    $this->actingAs($admin)
        ->from(route('administrators.curriculum.programs.index'))
        ->post(route('administrators.curriculum.programs.store'), [
            'code' => 'INVALID',
            'title' => 'Invalid pathway',
            'capability_id' => (string) $capability->id,
            'curriculum_kind' => 'grade_pathway',
            'curriculum_stage' => 'Grade 1',
        ])
        ->assertRedirect(route('administrators.curriculum.programs.index'))
        ->assertSessionHasErrors('curriculum_kind');
});
