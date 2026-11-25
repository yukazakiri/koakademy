<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentClassWaitlist;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-30',
        'semester' => 1,
    ]);
});

it('auto-assigns open class seats and waitlists overflow students when sections hit capacity', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSIT']);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT101']);

    $class = Classes::factory()->create([
        'subject_code' => 'IT101',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSIT-1A',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 1,
    ]);

    $s1 = Student::factory()->create(['course_id' => $course->id]);
    $s2 = Student::factory()->create(['course_id' => $course->id]);

    $e1 = StudentEnrollment::factory()->create(['student_id' => $s1->id, 'course_id' => $course->id, 'school_year' => '2025 - 2026', 'semester' => 1]);
    $e2 = StudentEnrollment::factory()->create(['student_id' => $s2->id, 'course_id' => $course->id, 'school_year' => '2025 - 2026', 'semester' => 1]);

    SubjectEnrollment::query()->create([
        'enrollment_id' => $e1->id,
        'student_id' => $s1->id,
        'subject_id' => $subject->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    SubjectEnrollment::query()->create([
        'enrollment_id' => $e2->id,
        'student_id' => $s2->id,
        'subject_id' => $subject->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/waitlists'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/enrollments/waitlists', false)
            ->where('stats.unassigned_count', 2)
            ->where('stats.auto_assignable_count', 2),
        );

    // Run one-click auto-assign: 1st student gets the 1 open seat, 2nd is waitlisted!
    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/enrollments/waitlists/auto-assign'), [
            'waitlist_when_full' => true,
        ])
        ->assertRedirect();

    expect(ClassEnrollment::query()->where('class_id', $class->id)->count())->toBe(1);
    expect(EnrollmentClassWaitlist::query()->waiting()->count())->toBe(1);

    // Now open a 2nd section and promote the waitlisted student!
    $overflowClass = Classes::factory()->create([
        'subject_code' => 'IT101',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSIT-1B',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 30,
    ]);
    $waitlistEntry = EnrollmentClassWaitlist::query()->waiting()->firstOrFail();

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/waitlists/{$waitlistEntry->id}/promote"), [
            'class_id' => $overflowClass->id,
        ])
        ->assertRedirect();

    $waitlistEntry->refresh();
    expect($waitlistEntry->status)->toBe(EnrollmentClassWaitlist::StatusPromoted);
    expect($waitlistEntry->promoted_class_id)->toBe($overflowClass->id);
    expect(ClassEnrollment::query()->where('student_id', $s2->id)->where('class_id', $overflowClass->id)->exists())->toBeTrue();
});
