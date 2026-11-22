<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\FacultyClassShareService;
use App\Services\StudentClassShareService;
use Illuminate\Support\Facades\DB;

it('loads student sidebar classes in constant query count without N+1 subject lookups', function (): void {
    School::factory()->create();

    $user = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create(['email' => $user->email, 'user_id' => $user->id]);

    $currentYear = (int) date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    for ($i = 1; $i <= 6; $i++) {
        $subject = Subject::factory()->create([
            'code' => "PERF579-{$i}",
            'title' => "Performance Subject {$i}",
        ]);
        $class = Classes::factory()->create([
            'school_year' => $schoolYear,
            'semester' => 1,
            'subject_id' => $subject->id,
            'subject_code' => $subject->code,
            'section' => "SEC-{$i}",
        ]);
        ClassEnrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'status' => true,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $classes = app(StudentClassShareService::class)->getStudentClasses($user);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($classes)->toHaveCount(6)
        ->and($classes[0]['subject_title'])->toContain('Performance Subject')
        ->and($queryCount)->toBeLessThanOrEqual(8);
});

it('resolves faculty subjects by subject_id when subject_code differs', function (): void {
    School::factory()->create();

    $user = User::factory()->create(['role' => UserRole::Instructor]);
    $faculty = Faculty::factory()->create(['email' => $user->email]);
    $subject = Subject::factory()->create(['code' => 'IDMATCH-101', 'title' => 'ID Matched Course']);

    $currentYear = (int) date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    Classes::factory()->create([
        'faculty_id' => $faculty->id,
        'subject_id' => $subject->id,
        'subject_code' => 'LEGACY-ALIAS',
        'school_year' => $schoolYear,
        'semester' => 1,
        'section' => 'A',
    ]);

    $classes = app(FacultyClassShareService::class)->getFacultyClasses($user);

    expect($classes)->toHaveCount(1)
        ->and($classes[0]['subject_code'])->toBe('IDMATCH-101')
        ->and($classes[0]['subject_title'])->toBe('ID Matched Course');
});
