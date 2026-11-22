<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $school = School::factory()->create(['is_active' => true]);

    GeneralSetting::factory()->create([
        'school_id' => $school->id,
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

it('recomputes continuing enrollment tuition fees server-side and ignores client-supplied zero fees', function (): void {
    $course = Course::factory()->create([
        'lec_per_unit' => 500,
        'lab_per_unit' => 1000,
        'miscelaneous' => 3500,
    ]);

    $student = Student::factory()->create([
        'course_id' => $course->id,
        'email' => 'tamper.student@example.com',
        'student_id' => 209901,
        'academic_year' => 1,
    ]);

    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'CS201',
        'lecture' => 3,
        'laboratory' => 1,
        'academic_year' => 2,
        'semester' => 1,
    ]);

    $response = $this->post(route('enrollment.continuing.store'), [
        'email' => 'tamper.student@example.com',
        'student_id' => '209901',
        'academic_year' => 2,
        'subjects' => [
            [
                'subject_id' => $subject->id,
                'is_modular' => false,
                'lecture_fee' => 0,
                'laboratory_fee' => 0,
                'enrolled_lecture_units' => 0,
                'enrolled_laboratory_units' => 0,
            ],
        ],
        'consent' => true,
    ]);

    $response->assertRedirect(route('enrollment.create'));
    $response->assertSessionHas('flash.success');

    $enrollment = StudentEnrollment::query()
        ->withoutSchoolScope()
        ->where('student_id', $student->id)
        ->latest('id')
        ->firstOrFail();

    $enrolledSubject = $enrollment->subjectsEnrolled()->firstOrFail();
    // 3 lecture + 1 lab = 4 total units * 500/unit = 2000 lecture fee; 1 * 1000 = 1000 lab fee
    expect((float) $enrolledSubject->lecture_fee)->toBe(2000.0)
        ->and((float) $enrolledSubject->laboratory_fee)->toBe(1000.0)
        ->and((int) $enrolledSubject->enrolled_lecture_units)->toBe(3)
        ->and((int) $enrolledSubject->enrolled_laboratory_units)->toBe(1);

    $tuition = $enrollment->studentTuition;
    expect($tuition)->not->toBeNull()
        ->and((float) $tuition->total_lectures)->toBe(2000.0)
        ->and((float) $tuition->total_laboratory)->toBe(1000.0);
});

it('rejects subjects belonging to a different course on continuing enrollment', function (): void {
    $courseA = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 1000]);
    $courseB = Course::factory()->create(['lec_per_unit' => 100, 'lab_per_unit' => 100]);

    $student = Student::factory()->create([
        'course_id' => $courseA->id,
        'email' => 'cross.course@example.com',
        'student_id' => 209902,
        'academic_year' => 1,
    ]);

    $foreignSubject = Subject::factory()->create([
        'course_id' => $courseB->id,
        'lecture' => 3,
        'laboratory' => 0,
    ]);

    $this->post(route('enrollment.continuing.store'), [
        'email' => 'cross.course@example.com',
        'student_id' => '209902',
        'academic_year' => 2,
        'subjects' => [
            [
                'subject_id' => $foreignSubject->id,
                'is_modular' => false,
            ],
        ],
        'consent' => true,
    ])->assertSessionHasErrors('subjects');

    expect(StudentEnrollment::query()->withoutSchoolScope()->where('student_id', $student->id)->exists())->toBeFalse();
});

it('rate limits public enrollment lookup endpoint', function (): void {
    RateLimiter::clear('public-enrollment-lookup');

    for ($i = 0; $i < 10; $i++) {
        $this->postJson(route('enrollment.lookup'), [
            'email' => 'enum@example.com',
            'student_id' => '999999',
        ])->assertSuccessful();
    }

    $this->postJson(route('enrollment.lookup'), [
        'email' => 'enum@example.com',
        'student_id' => '999999',
    ])->assertTooManyRequests();
});
