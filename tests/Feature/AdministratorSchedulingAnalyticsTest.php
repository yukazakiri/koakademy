<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('only includes active courses in scheduling class creation options', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $activeCourse = Course::factory()->create([
        'code' => 'ACTIVE',
        'is_active' => true,
    ]);

    $inactiveCourse = Course::factory()->create([
        'code' => 'INACTIVE',
        'is_active' => false,
    ]);

    $this->get(route('administrators.scheduling-analytics.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/scheduling-analytics', false)
            ->where('creation_options.courses', fn ($courses): bool => collect($courses)->contains('id', $activeCourse->id)
                && ! collect($courses)->contains('id', $inactiveCourse->id))
        );
});

it('exposes course-specific curriculum years for shared scheduling classes', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-22',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
    $hospitality = Course::factory()->create(['code' => 'BSHM', 'is_active' => true]);
    $business = Course::factory()->create(['code' => 'BSBA', 'is_active' => true]);
    $hospitalitySubject = Subject::factory()->for($hospitality)->create(['code' => 'FS-HM', 'academic_year' => 4]);
    $businessSubject = Subject::factory()->for($business)->create(['code' => 'FS-BA', 'academic_year' => 3]);
    $class = Classes::factory()->create([
        'subject_id' => $hospitalitySubject->id,
        'subject_ids' => [$hospitalitySubject->id, $businessSubject->id],
        'subject_code' => 'FS',
        'academic_year' => 1,
        'course_codes' => [$hospitality->id, (string) $business->id],
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'classification' => 'college',
    ]);
    Schedule::factory()->create(['class_id' => $class->id]);

    $this->get(route('administrators.scheduling-analytics.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('schedule_data.0.id', $class->id)
            ->where('schedule_data.0.year_levels', ['3rd Year', '4th Year'])
            ->where("schedule_data.0.course_year_levels.{$hospitality->id}", ['4th Year'])
            ->where("schedule_data.0.course_year_levels.{$business->id}", ['3rd Year'])
            ->where('filters.available_year_levels', ['3rd Year', '4th Year'])
        );
});
