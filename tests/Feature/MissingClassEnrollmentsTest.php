<?php

declare(strict_types=1);

use App\Enums\EnrollStat;
use App\Filament\Widgets\MissingClassEnrollmentsTable;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\GeneralSettingsService;
use Illuminate\Support\Facades\DB;

/**
 * @return array{
 *     enrollment: StudentEnrollment,
 *     student: Student,
 *     course: Course,
 *     subjects: array<int, Subject>,
 *     classes: array<int, array<int, Classes>>,
 *     school_year: string,
 *     semester: int
 * }
 */
function createDcc33MissingClassScenario(int $subjectCount = 3, int $classesPerSubject = 2): array
{
    GeneralSetting::factory()->createOne([
        'school_starting_date' => '2026-08-01',
        'school_ending_date' => '2027-05-31',
        'semester' => 1,
    ]);

    $settings = app(GeneralSettingsService::class);
    $schoolYear = $settings->getCurrentSchoolYearString();
    $semester = $settings->getCurrentSemester();

    $course = Course::factory()->createOne();
    $student = Student::factory()->createOne(['course_id' => $course->id]);

    $enrollment = StudentEnrollment::factory()->createOne([
        'student_id' => (string) $student->id,
        'course_id' => $course->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'status' => EnrollStat::Pending->value,
    ]);

    $subjects = [];
    $classes = [];

    for ($subjectIndex = 0; $subjectIndex < $subjectCount; $subjectIndex++) {
        $subject = Subject::factory()->createOne([
            'course_id' => $course->id,
            'code' => sprintf('TST%03d', $subjectIndex + 1),
        ]);
        $subjects[] = $subject;

        SubjectEnrollment::factory()->createOne([
            'student_id' => (int) $student->id,
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'school_year' => $schoolYear,
            'semester' => $semester,
        ]);

        for ($classIndex = 0; $classIndex < $classesPerSubject; $classIndex++) {
            // Alternate the JSON scalar type: legacy rows store string IDs,
            // newer rows store integers. Both have to match the subject.
            $courseCodes = $classIndex % 2 === 0 ? [$course->id] : [(string) $course->id];
            $subjectIds = $classIndex % 2 === 0 ? [$subject->id] : [(string) $subject->id];

            $classes[$subjectIndex][] = Classes::factory()->createOne([
                'subject_id' => $subject->id,
                'subject_ids' => $subjectIds,
                'subject_code' => $subject->code,
                'faculty_id' => Faculty::factory(),
                'school_year' => $schoolYear,
                'semester' => $semester,
                'course_codes' => $courseCodes,
                'maximum_slots' => 2,
            ]);
        }
    }

    return [
        'enrollment' => $enrollment,
        'student' => $student,
        'course' => $course,
        'subjects' => $subjects,
        'classes' => $classes,
        'school_year' => $schoolYear,
        'semester' => $semester,
    ];
}

it('batches candidate class and slot lookups for the missing classes accessor', function (): void {
    $scenario = createDcc33MissingClassScenario(subjectCount: 4, classesPerSubject: 2);
    $enrollment = $scenario['enrollment'];
    $firstClass = $scenario['classes'][0][0];
    $fullClass = $scenario['classes'][0][1];

    ClassEnrollment::factory()->createOne([
        'class_id' => $firstClass->id,
        'student_id' => 900001,
        'status' => true,
    ]);

    ClassEnrollment::factory()->createOne([
        'class_id' => $fullClass->id,
        'student_id' => 900002,
        'status' => true,
    ]);

    ClassEnrollment::factory()->createOne([
        'class_id' => $fullClass->id,
        'student_id' => 900003,
        'status' => true,
    ]);

    $enrollment = StudentEnrollment::query()
        ->with(['subjectsEnrolled.subject', 'student.classEnrollments.class'])
        ->findOrFail($enrollment->id);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $missingClasses = $enrollment->missing_classes_for_infolist;

    $firstClassRow = $missingClasses->firstWhere('class_id', $firstClass->id);
    $fullClassRow = $missingClasses->firstWhere('class_id', $fullClass->id);

    expect($missingClasses)->toHaveCount(8)
        ->and($firstClassRow->available_slots)->toBe(1)
        ->and($firstClassRow->is_full)->toBeFalse()
        ->and($fullClassRow->available_slots)->toBe(0)
        ->and($fullClassRow->is_full)->toBeTrue()
        // Four subjects must not turn into a query per subject, class and
        // enrolled-slot count. The eager-loaded relations keep this bounded.
        ->and($queries)->toBeLessThanOrEqual(12);
});

it('keeps a placeholder when a missing subject has no class offering', function (): void {
    $scenario = createDcc33MissingClassScenario(subjectCount: 1, classesPerSubject: 0);

    $enrollment = StudentEnrollment::query()
        ->with(['subjectsEnrolled.subject', 'student.classEnrollments.class'])
        ->findOrFail($scenario['enrollment']->id);

    $missingClasses = $enrollment->missing_classes_for_infolist;

    expect($missingClasses)->toHaveCount(1)
        ->and($missingClasses->first()->class_id)->toBeNull()
        ->and($missingClasses->first()->subject_code)->toBe('TST001')
        ->and($missingClasses->first()->available_slots)->toBeNull()
        ->and($missingClasses->first()->max_slots)->toBe(0)
        ->and($missingClasses->first()->is_full)->toBeFalse();
});

it('determines missing classes without querying when relations are eager loaded', function (): void {
    $scenario = createDcc33MissingClassScenario(subjectCount: 2, classesPerSubject: 1);
    $enrollment = $scenario['enrollment'];

    $loadedEnrollment = StudentEnrollment::query()
        ->with(['subjectsEnrolled.subject', 'student.classEnrollments.class'])
        ->findOrFail($enrollment->id);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($loadedEnrollment->hasMissingClasses())->toBeTrue()
        ->and($queries)->toBe(0);

    // Enrol the student in both subjects. A fresh instance proves the check
    // now reports the enrollment as complete without extra statements.
    foreach ($scenario['subjects'] as $subjectIndex => $subject) {
        $class = $scenario['classes'][$subjectIndex][0];

        ClassEnrollment::factory()->createOne([
            'class_id' => $class->id,
            'student_id' => (int) $scenario['student']->id,
            'status' => true,
        ]);
    }

    $completeEnrollment = StudentEnrollment::query()
        ->with(['subjectsEnrolled.subject', 'student.classEnrollments.class'])
        ->findOrFail($enrollment->id);

    $queries = 0;

    expect($completeEnrollment->hasMissingClasses())->toBeFalse()
        ->and($queries)->toBe(0);
});

it('filters the widget query in chunks instead of querying per enrollment', function (): void {
    $scenario = createDcc33MissingClassScenario(subjectCount: 2, classesPerSubject: 1);

    $completeEnrollment = StudentEnrollment::factory()->createOne([
        'student_id' => (string) Student::factory()->createOne(['course_id' => $scenario['course']->id])->id,
        'course_id' => $scenario['course']->id,
        'school_year' => $scenario['school_year'],
        'semester' => $scenario['semester'],
        'status' => EnrollStat::Pending->value,
    ]);

    // This enrollment has one subject that is already covered by a class.
    $coveredSubject = Subject::factory()->createOne([
        'course_id' => $scenario['course']->id,
        'code' => 'TST-COVERED',
    ]);

    SubjectEnrollment::factory()->createOne([
        'student_id' => (int) $completeEnrollment->student_id,
        'enrollment_id' => $completeEnrollment->id,
        'subject_id' => $coveredSubject->id,
        'school_year' => $scenario['school_year'],
        'semester' => $scenario['semester'],
    ]);

    $coveredClass = Classes::factory()->createOne([
        'subject_id' => $coveredSubject->id,
        'subject_ids' => [$coveredSubject->id],
        'subject_code' => $coveredSubject->code,
        'faculty_id' => Faculty::factory(),
        'school_year' => $scenario['school_year'],
        'semester' => $scenario['semester'],
        'course_codes' => [$scenario['course']->id],
        'maximum_slots' => 10,
    ]);

    ClassEnrollment::factory()->createOne([
        'class_id' => $coveredClass->id,
        'student_id' => (int) $completeEnrollment->student_id,
        'status' => true,
    ]);

    $widget = new MissingClassEnrollmentsTable;

    $method = new ReflectionMethod($widget, 'getEnrollmentIdsWithMissingClasses');
    $method->setAccessible(true);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $enrollmentIds = $method->invoke($widget);

    expect($enrollmentIds)->toContain($scenario['enrollment']->id)
        ->and($enrollmentIds)->not->toContain($completeEnrollment->id)
        // One chunk plus its eager loads, rather than one lookup per record.
        ->and($queries)->toBeLessThanOrEqual(12);

    $tableMethod = new ReflectionMethod($widget, 'getTableQuery');
    $tableMethod->setAccessible(true);

    expect($tableMethod->invoke($widget)->pluck('id')->all())
        ->toContain($scenario['enrollment']->id)
        ->not->toContain($completeEnrollment->id);
});
