<?php

declare(strict_types=1);

use App\Ai\Tools\QueryCampusAnalyticsTool;
use App\Ai\Tools\SearchStudentsTool;
use App\Enums\StudentStatus;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\StudentDirectoryQuery;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    $this->withoutVite();

    GeneralSetting::factory()->create([
        'semester' => 1,
    ]);

    Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'View:Student', 'guard_name' => 'web']);
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ViewAny:Student', 'guard_name' => 'web']);

    $this->term = app(GeneralSettingsService::class)->getCurrentSchoolYearString();
    $this->semester = 1;
});

function makeProgram(string $code, string $title, string $departmentCode = 'CCS'): Course
{
    return Course::factory()->create([
        'code' => $code,
        'title' => $title,
        'department_id' => Department::factory()->create(['code' => $departmentCode])->id,
    ]);
}

it('returns emails of all enrolled students in a program for the current term', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');

    $enrolled = Student::factory()->count(3)->create(['course_id' => $bsit->id]);
    // A profile with no term enrollment record at all.
    $applicant = Student::factory()->create(['course_id' => $bsit->id, 'status' => StudentStatus::Applicant->value]);

    foreach ($enrolled as $student) {
        StudentEnrollment::factory()->create([
            'student_id' => (string) $student->id,
            'course_id' => $bsit->id,
            'school_year' => $this->term,
            'semester' => $this->semester,
            'terminal_outcome' => null,
        ]);
    }

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'fields' => 'emails',
        'limit' => 500,
    ]);

    expect($result['total_matched'])->toBe(3)
        ->and($result['emails'])->toHaveCount(3)
        ->and($result['emails'])->toContain($enrolled->first()->email)
        ->and($result['emails'])->not->toContain($applicant->email)
        ->and($result['term']['school_year'])->toBe($this->term)
        ->and($result['term']['enrollment_basis'])->toBe('enrollment');
});

it('excludes term enrollments that ended in an abandoned outcome', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');

    $kept = Student::factory()->create(['course_id' => $bsit->id]);
    $cancelled = Student::factory()->create(['course_id' => $bsit->id]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $kept->id,
        'course_id' => $bsit->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'terminal_outcome' => null,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $cancelled->id,
        'course_id' => $bsit->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'terminal_outcome' => 'cancelled',
    ]);

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'fields' => 'emails',
    ]);

    expect($result['total_matched'])->toBe(1)
        ->and($result['emails'])->toBe([$kept->email]);
});

it('keeps completed term enrollments in the population', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $student = Student::factory()->create(['course_id' => $bsit->id]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'course_id' => $bsit->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'terminal_outcome' => 'completed',
    ]);

    $result = app(StudentDirectoryQuery::class)->execute(['program' => ['BSIT']]);

    expect($result['total_matched'])->toBe(1);
});

it('resolves a program by title fragment as well as by code', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $bscs = makeProgram('BSCS', 'Bachelor of Science in Computer Science');

    $target = Student::factory()->create(['course_id' => $bscs->id]);
    Student::factory()->create(['course_id' => $bsit->id]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $target->id,
        'course_id' => $bscs->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
    ]);

    $result = app(StudentDirectoryQuery::class)->execute(['program' => ['Computer Science']]);

    expect($result['total_matched'])->toBe(1)
        ->and($result['resolved_programs'][0]['code'])->toBe('BSCS');
});

it('returns actionable guidance when no program matches', function (): void {
    makeProgram('BSIT', 'Bachelor of Science in Information Technology');

    $result = app(StudentDirectoryQuery::class)->execute(['program' => ['BS-NOT-A-THING']]);

    expect($result['error'])->toBe('unknown_program')
        ->and($result['available_programs'])->not->toBeEmpty()
        ->and(collect($result['available_programs'])->pluck('code'))->toContain('BSIT');
});

it('pages through a cohort larger than the page size and reports the true total', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $students = Student::factory()->count(7)->create(['course_id' => $bsit->id]);

    foreach ($students as $student) {
        StudentEnrollment::factory()->create([
            'student_id' => (string) $student->id,
            'course_id' => $bsit->id,
            'school_year' => $this->term,
            'semester' => $this->semester,
        ]);
    }

    $first = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'fields' => 'emails',
        'limit' => 5,
    ]);

    expect($first['total_matched'])->toBe(7)
        ->and($first['returned'])->toBe(5)
        ->and($first['has_more'])->toBeTrue()
        ->and($first['next_offset'])->toBe(5);

    $second = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'fields' => 'emails',
        'limit' => 5,
        'offset' => $first['next_offset'],
    ]);

    expect($second['returned'])->toBe(2)
        ->and($second['has_more'])->toBeFalse();

    $combined = array_merge($first['emails'], $second['emails']);
    sort($combined);

    expect($combined)->toHaveCount(7)
        ->and(array_unique($combined))->toHaveCount(7);
});

it('filters by year level, status, and student type together', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');

    $match = Student::factory()->create([
        'course_id' => $bsit->id,
        'academic_year' => 2,
        'status' => StudentStatus::Enrolled->value,
        'student_type' => 'college',
    ]);
    $wrongYear = Student::factory()->create([
        'course_id' => $bsit->id,
        'academic_year' => 3,
        'status' => StudentStatus::Enrolled->value,
        'student_type' => 'college',
    ]);
    $shs = Student::factory()->create([
        'course_id' => $bsit->id,
        'academic_year' => 2,
        'status' => StudentStatus::Enrolled->value,
        'student_type' => 'shs',
    ]);

    foreach ([$match, $wrongYear, $shs] as $student) {
        StudentEnrollment::factory()->create([
            'student_id' => (string) $student->id,
            'course_id' => $bsit->id,
            'school_year' => $this->term,
            'semester' => $this->semester,
        ]);
    }

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'year_level' => [2],
        'status' => ['enrolled'],
        'student_type' => ['college'],
    ]);

    expect($result['total_matched'])->toBe(1)
        ->and($result['students'][0]['student_number'])->toBe((string) $match->student_id)
        ->and($result['students'][0]['enrolled_this_term'])->toBeTrue()
        // The program and department are resolved from the eager-loaded
        // relation, not left null.
        ->and($result['students'][0]['program_code'])->toBe('BSIT')
        ->and($result['students'][0]['program_title'])->toBe('Bachelor of Science in Information Technology')
        ->and($result['students'][0]['department'])->toBe('CCS');
});

it('omits the term constraint entirely when the basis is any', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $neverEnrolled = Student::factory()->create(['course_id' => $bsit->id]);

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'basis' => 'any',
    ]);

    expect($result['total_matched'])->toBe(1)
        ->and($result['students'][0]['student_number'])->toBe((string) $neverEnrolled->student_id)
        ->and($result['students'][0]['enrolled_this_term'])->toBeNull();
});

it('scopes to the current term rather than a historical one', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $thisTerm = Student::factory()->create(['course_id' => $bsit->id]);
    $priorTerm = Student::factory()->create(['course_id' => $bsit->id]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $thisTerm->id,
        'course_id' => $bsit->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
    ]);
    StudentEnrollment::factory()->create([
        'student_id' => (string) $priorTerm->id,
        'course_id' => $bsit->id,
        'school_year' => '2020 - 2021',
        'semester' => 2,
    ]);

    $result = app(StudentDirectoryQuery::class)->execute(['program' => ['BSIT']]);

    expect($result['total_matched'])->toBe(1)
        ->and($result['students'][0]['student_number'])->toBe((string) $thisTerm->student_id);
});

it('reports missing email addresses rather than silently dropping rows', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $withEmail = Student::factory()->create(['course_id' => $bsit->id]);
    $withoutEmail = Student::factory()->create(['course_id' => $bsit->id, 'email' => null]);

    foreach ([$withEmail, $withoutEmail] as $student) {
        StudentEnrollment::factory()->create([
            'student_id' => (string) $student->id,
            'course_id' => $bsit->id,
            'school_year' => $this->term,
            'semester' => $this->semester,
        ]);
    }

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'fields' => 'emails',
    ]);

    expect($result['total_matched'])->toBe(2)
        ->and($result['emails'])->toHaveCount(1)
        ->and($result['students_missing_email'])->toBe(1);
});

it('serves the copilot tool the population answer the tool previously could not give', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('ViewAny:Student');
    Auth::login($user);

    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $student = Student::factory()->create(['course_id' => $bsit->id]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'course_id' => $bsit->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
    ]);

    $tool = new SearchStudentsTool;
    $data = json_decode((string) $tool->handle(new Request([
        'program' => ['BSIT'],
        'fields' => 'emails',
    ])), true);

    expect($data['total_matched'])->toBe(1)
        ->and($data['emails'])->toBe([$student->email])
        ->and($data)->not->toHaveKey('error');
});

it('includes students who have an active class this term under the class basis', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');

    $inClass = Student::factory()->create(['course_id' => $bsit->id]);
    $notInClass = Student::factory()->create(['course_id' => $bsit->id]);

    $class = Classes::factory()->create([
        'school_year' => $this->term,
        'semester' => $this->semester,
    ]);

    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $inClass->id,
        'status' => true,
    ]);

    // A dropped class enrollment must not count.
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $notInClass->id,
        'status' => false,
    ]);

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'basis' => 'class',
        'fields' => 'detailed',
    ]);

    expect($result['total_matched'])->toBe(1)
        ->and($result['term']['enrollment_basis'])->toBe('class')
        ->and($result['students'][0]['student_number'])->toBe((string) $inClass->student_id)
        ->and($result['students'][0]['class_count_this_term'])->toBe(1);
});

it('scopes the class basis to classes running the requested term', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $thisTerm = Student::factory()->create(['course_id' => $bsit->id]);
    $priorTerm = Student::factory()->create(['course_id' => $bsit->id]);

    $currentClass = Classes::factory()->create(['school_year' => $this->term, 'semester' => $this->semester]);
    $priorClass = Classes::factory()->create(['school_year' => '2020 - 2021', 'semester' => 2]);

    ClassEnrollment::factory()->create(['class_id' => $currentClass->id, 'student_id' => $thisTerm->id, 'status' => true]);
    ClassEnrollment::factory()->create(['class_id' => $priorClass->id, 'student_id' => $priorTerm->id, 'status' => true]);

    $result = app(StudentDirectoryQuery::class)->execute(['program' => ['BSIT'], 'basis' => 'class']);

    expect($result['total_matched'])->toBe(1)
        ->and($result['students'][0]['student_number'])->toBe((string) $thisTerm->student_id);
});

it('treats an explicit term as a population request even without other filters', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $withEnrollment = Student::factory()->create(['course_id' => $bsit->id]);
    $withoutEnrollment = Student::factory()->create(['course_id' => $bsit->id]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $withEnrollment->id,
        'course_id' => $bsit->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
    ]);

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'semester' => $this->semester,
    ]);

    expect($result['total_matched'])->toBe(1)
        ->and($result['students'][0]['student_number'])->toBe((string) $withEnrollment->student_id)
        ->and($result['students'][0]['student_number'])->not->toBe((string) $withoutEnrollment->student_id);
});

it('ignores an unrecognised filter value instead of returning a silently empty roster', function (): void {
    $bsit = makeProgram('BSIT', 'Bachelor of Science in Information Technology');
    $student = Student::factory()->create(['course_id' => $bsit->id, 'status' => StudentStatus::Enrolled->value]);

    StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'course_id' => $bsit->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
    ]);

    // Enum values are matched case-insensitively, so an agent passing the
    // human label still filters correctly rather than matching nothing.
    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'status' => ['Enrolled'],
    ]);

    expect($result['criteria']['status'])->toBe(['enrolled'])
        ->and($result['total_matched'])->toBe(1);

    // A genuinely unknown value degrades to "no filter" instead of an empty
    // roster, so the agent can retry rather than report "zero students".
    $unknown = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'status' => ['not_a_real_status'],
    ]);

    expect($unknown['criteria']['status'])->toBe([])
        ->and($unknown['total_matched'])->toBe(1);
});

it('refuses the directory to a caller without student read permission', function (): void {
    Auth::login(User::factory()->create());

    $data = json_decode((string) (new SearchStudentsTool)->handle(new Request([
        'program' => ['BSIT'],
    ])), true);

    expect($data['error'])->toBeTrue()
        ->and($data['message'])->toContain('not permitted');
});

it('separates a single-record lookup from a cohort export', function (): void {
    // Security guard is seeded with View:Student but not ViewAny:Student,
    // because it verifies one person at a time. It must not be able to turn the
    // chat box into a directory export.
    $guard = User::factory()->create();
    $guard->givePermissionTo('View:Student');
    Auth::login($guard);

    $bsit = makeProgram('BSIT', 'Information Technology');
    $student = Student::factory()->create([
        'course_id' => $bsit->id,
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);

    $tool = new SearchStudentsTool;

    // A bare name is how a guard finds the person they are allowed to open.
    $byName = json_decode((string) $tool->handle(new Request([
        'query' => 'Dela Cruz',
    ])), true);

    expect($byName['count'])->toBe(1)
        ->and($byName['students'][0]['student_number'])->toBe((string) $student->student_id);

    foreach ([
        ['program' => ['BSIT']],
        ['fields' => 'emails'],
        ['status' => ['enrolled']],
        ['school_year' => $this->term],
        ['offset' => 0],
    ] as $cohortFilter) {
        $denied = json_decode((string) $tool->handle(new Request($cohortFilter)), true);

        expect($denied['error'])->toBeTrue()
            ->and($denied['required_permission'])->toBe('ViewAny:Student');
    }

    // A pasted list is a bulk read even when each entry is one person.
    $batch = json_decode((string) $tool->handle(new Request([
        'queries' => ['Dela Cruz, Juan'],
    ])), true);

    expect($batch['error'])->toBeTrue()
        ->and($batch['required_permission'])->toBe('ViewAny:Student');
});

it('resolves a pasted name list without falling back to a surname-only match', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('ViewAny:Student');
    Auth::login($admin);

    $renelyn = Student::factory()->create([
        'first_name' => 'Renelyn',
        'last_name' => 'Bunalan',
        'email' => 'renelyn.bunalan@example.com',
    ]);

    $tool = new SearchStudentsTool;

    $batch = json_decode((string) $tool->handle(new Request([
        'queries' => [
            '1 BUNALAN, RENELYN O.',
            '2 BUNALAN, UNKNOWNGIVEN X.',
        ],
    ])), true);

    expect($batch['count'])->toBe(2)
        ->and($batch['found_count'])->toBe(1)
        ->and($batch['students'][0]['found'])->toBeTrue()
        ->and($batch['students'][0]['email'])->toBe($renelyn->email)
        // A formatted name with the wrong given name must report a miss, not
        // resolve to the one student who shares the surname.
        ->and($batch['students'][1]['found'])->toBeFalse()
        ->and($batch['students'][1]['email'])->toBeNull();

    // A multiline query is read as a list, and the "1." row numbers registrar
    // pastes carry are stripped rather than searched for.
    $multiline = json_decode((string) $tool->handle(new Request([
        'query' => "1\tBUNALAN, RENELYN O.\n2\tBUNALAN, UNKNOWNGIVEN X.",
    ])), true);

    expect($multiline['count'])->toBe(2)
        ->and($multiline['found_count'])->toBe(1)
        ->and($multiline['students'][0]['email'])->toBe($renelyn->email);

    // Batch size is bounded so one request cannot ask for an unbounded lookup.
    $oversized = json_decode((string) $tool->handle(new Request([
        'queries' => array_fill(0, StudentDirectoryQuery::MAX_BATCH_SIZE + 1, 'BUNALAN, RENELYN O.'),
    ])), true);

    expect($oversized['error'])->toBeTrue()
        ->and($oversized['limit'])->toBe(StudentDirectoryQuery::MAX_BATCH_SIZE);
});

it('matches a formatted surname and given name through the filtered query too', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('View:Student');
    Auth::login($admin);

    $student = Student::factory()->create([
        'first_name' => 'Audrey Irish',
        'last_name' => 'Fermante',
    ]);

    $data = json_decode((string) (new SearchStudentsTool)->handle(new Request([
        'query' => 'FERMANTE, AUDREY IRISH G.',
    ])), true);

    expect($data['count'])->toBe(1)
        ->and($data['students'][0]['student_number'])->toBe((string) $student->student_id)
        // A bare name search is not term-scoped, so a student without a current
        // enrollment record is still found.
        ->and($data['term']['enrollment_basis'])->toBe(StudentDirectoryQuery::BASIS_ANY);
});

it('computes enrollment analytics from the registrar service instead of fixed numbers', function (): void {
    // Constructed with `new`, exactly as the agent registries build it.
    $tool = new QueryCampusAnalyticsTool;

    $enrollment = json_decode((string) $tool->handle(new Request(['category' => 'enrollment'])), true);
    $overview = json_decode((string) $tool->handle(new Request(['category' => 'overview'])), true);

    expect($enrollment)->toHaveKey('current_term_enrollments')
        ->and($enrollment)->toHaveKey('by_program')
        ->and($overview['headline_metrics'])->not->toHaveKey('retention_rate_percent')
        ->and($overview['unavailable'])->toHaveKey('retention_rate_percent');
});
