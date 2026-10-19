<?php

declare(strict_types=1);

use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentCurriculumSelection;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;

it('selects incomplete back subjects only after verified prerequisites are completed', function (): void {
    $school = School::factory()->create();
    $course = Course::factory()->create(['school_id' => $school->id, 'lec_per_unit' => 100]);
    $student = Student::factory()->create(['course_id' => $course->id, 'school_id' => $school->id]);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id, 'school_id' => $school->id, 'school_year' => '2035 - 2036', 'semester' => 1, 'academic_year' => 2]);
    $base = ['course_id' => $course->id, 'semester' => 1, 'academic_year' => 1, 'pre_riquisite' => [], 'lecture' => 3, 'laboratory' => 0];
    $passed = Subject::factory()->create([...$base, 'code' => 'DONE']);
    $back = Subject::factory()->create([...$base, 'code' => 'BACK']);
    $eligible = Subject::factory()->create([...$base, 'academic_year' => 2, 'code' => 'NEXT', 'pre_riquisite' => [$passed->id]]);
    $blocked = Subject::factory()->create([...$base, 'academic_year' => 2, 'code' => 'BLOCKED', 'pre_riquisite' => [$back->id]]);
    Subject::factory()->create([...$base, 'academic_year' => 3, 'code' => 'FUTURE']);
    Subject::factory()->create([...$base, 'semester' => 2, 'code' => 'OTHERSEM']);
    $prior = StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_id' => $school->id, 'school_year' => '2034 - 2035', 'semester' => 1, 'terminal_outcome' => 'completed']);
    $prior->subjectsEnrolled()->create(['student_id' => $student->id, 'subject_id' => $passed->id, 'school_year' => $prior->school_year, 'semester' => 1, 'academic_year' => 1, 'grade_outcome' => 'pass']);
    $selection = app(EnrollmentCurriculumSelection::class);

    $selected = $selection->eligibleBackSubjects($enrollment);
    $strategy = app(EnrollmentPolicyRegistry::class)->assignmentStrategy('assignment.curriculum_automatic');
    $first = $strategy->execute(EnrollmentContext::fromEnrollment($enrollment), ['curriculum_selection' => 'eligible_back_subjects'], 'back-selection');
    $second = $strategy->execute(EnrollmentContext::fromEnrollment($enrollment), ['curriculum_selection' => 'eligible_back_subjects'], 'back-selection-retry');

    expect($selected->modelKeys())->toBe([$back->id, $eligible->id]);
    expect($first->successful)->toBeTrue();
    expect($second->successful)->toBeTrue();
    expect($enrollment->subjectsEnrolled()->pluck('subject_id')->all())->toBe([$back->id, $eligible->id]);
    expect($enrollment->subjectsEnrolled()->where('subject_id', $blocked->id)->exists())->toBeFalse();
});

it('preserves default current year assignment without enabling back subjects', function (): void {
    $course = Course::factory()->create();
    $enrollment = StudentEnrollment::factory()->create(['course_id' => $course->id, 'academic_year' => 2, 'semester' => 1]);
    $back = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $current = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 2, 'semester' => 1]);

    $result = app(EnrollmentPolicyRegistry::class)->assignmentStrategy('assignment.curriculum_automatic')->execute(EnrollmentContext::fromEnrollment($enrollment), [], 'regular-curriculum');

    expect($result->successful)->toBeTrue();
    expect($enrollment->subjectsEnrolled()->pluck('subject_id')->all())->toBe([$current->id]);
    expect($enrollment->subjectsEnrolled()->where('subject_id', $back->id)->exists())->toBeFalse();
});

it('rejects invalid selection mode before assigning subjects', function (): void {
    $enrollment = StudentEnrollment::factory()->create();

    $result = app(EnrollmentPolicyRegistry::class)->assignmentStrategy('assignment.curriculum_automatic')->execute(
        EnrollmentContext::fromEnrollment($enrollment), ['curriculum_selection' => 'unchecked_all'], 'invalid-selection',
    );

    expect($result->successful)->toBeFalse();
    expect($enrollment->subjectsEnrolled()->count())->toBe(0);
});

it('exposes read-only saved-history selections in recommendations', function (): void {
    $school = School::factory()->create();
    $course = Course::factory()->create(['school_id' => $school->id]);
    $enrollment = StudentEnrollment::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 2, 'semester' => 1, 'school_year' => '2035 - 2036']);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1, 'pre_riquisite' => []]);

    $result = app(EnrollmentPolicyRegistry::class)->assignmentStrategy('assignment.curriculum_automatic')->recommend(
        EnrollmentContext::fromEnrollment($enrollment), ['curriculum_selection' => 'eligible_back_subjects'],
    );

    expect($result['subject_ids'])->toBe([$subject->id]);
    expect($result['writes_performed'])->toBeFalse();
    expect($enrollment->subjectsEnrolled()->count())->toBe(0);
});

it('skips malformed and foreign prerequisite definitions', function (): void {
    $school = School::factory()->create();
    $course = Course::factory()->create(['school_id' => $school->id]);
    $enrollment = StudentEnrollment::factory()->create(['school_id' => $school->id, 'course_id' => $course->id, 'academic_year' => 2, 'semester' => 1, 'school_year' => '2035 - 2036']);
    $foreign = Subject::factory()->create(['course_id' => Course::factory()->create(['school_id' => School::factory()->create()->id])->id]);
    foreach ([['BAD-CODE'], [$foreign->id]] as $ids) {
        Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1, 'pre_riquisite' => $ids]);
    }

    $result = app(EnrollmentCurriculumSelection::class)->eligibleBackSubjects($enrollment);

    expect($result)->toHaveCount(0);
});
