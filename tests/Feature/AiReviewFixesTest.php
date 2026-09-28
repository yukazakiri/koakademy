<?php

declare(strict_types=1);

use App\Ai\Tools\QueryCampusAnalyticsTool;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Services\GeneralSettingsService;
use App\Services\StudentDirectoryQuery;
use App\Services\TenantContext;
use Database\Factories\StudentTuitionFactory;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;

/**
 * Regressions for the review findings on the directory query and the campus
 * analytics tool. Each test names the bug it pins.
 */
beforeEach(function (): void {
    GeneralSetting::factory()->create(['semester' => 1]);

    $this->term = app(GeneralSettingsService::class)->getCurrentSchoolYearString();
    $this->semester = 1;
});

function makeCourse(string $code, string $title, string $departmentCode = 'CCS'): Course
{
    return Course::factory()->create([
        'code' => $code,
        'title' => $title,
        'department_id' => Department::factory()->create(['code' => $departmentCode])->id,
    ]);
}

function enrollForTerm(Student $student, Course $course, string $term, int $semester): StudentEnrollment
{
    return StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'course_id' => $course->id,
        'school_year' => $term,
        'semester' => $semester,
        'terminal_outcome' => null,
    ]);
}

/**
 * `StudentTuition` does not use HasFactory, so reach for the factory class.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeTuition(array $attributes = []): StudentTuition
{
    return StudentTuitionFactory::new()->create($attributes);
}

/**
 * A soft-deleted enrollment record must not keep a student in the cohort.
 *
 * The EXISTS subquery is raw SQL, so it never sees the SoftDeletes scope the
 * model applies. Before the fix, deleting the record still counted the student
 * as enrolled and their email still came out in an export.
 */
it('excludes a student whose only term enrollment was soft deleted', function (): void {
    $course = makeCourse('BSIT', 'Information Technology');
    $student = Student::factory()->create(['course_id' => $course->id]);

    $enrollment = enrollForTerm($student, $course, $this->term, $this->semester);

    $before = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'fields' => 'emails',
    ]);

    expect($before['total_matched'])->toBe(1);

    $enrollment->delete();

    $after = app(StudentDirectoryQuery::class)->execute([
        'program' => ['BSIT'],
        'fields' => 'emails',
    ]);

    expect($after['total_matched'])->toBe(0)
        ->and($after['emails'])->toBe([]);
});

/**
 * A department code filter must cover every program in that department.
 *
 * Resolution used to keep only the alphabetically first match, so asking for
 * "CCS" silently omitted every program after the first.
 */
it('resolves a department code to every program in that department', function (): void {
    $department = Department::factory()->create(['code' => 'CCS']);

    $bsit = makeCourse('BSIT', 'Information Technology');
    $bscs = makeCourse('BSCS', 'Computer Science');
    $bsba = makeCourse('BSBA', 'Business Administration');

    // One shared department, so all three match the code.
    foreach ([$bsit, $bscs, $bsba] as $course) {
        $course->forceFill(['department_id' => $department->id])->save();
    }

    $bsitStudent = Student::factory()->create(['course_id' => $bsit->id]);
    $bscsStudent = Student::factory()->create(['course_id' => $bscs->id]);
    $bsbaStudent = Student::factory()->create(['course_id' => $bsba->id]);

    foreach ([$bsitStudent, $bscsStudent, $bsbaStudent] as $student) {
        enrollForTerm($student, $student->course, $this->term, $this->semester);
    }

    $result = app(StudentDirectoryQuery::class)->execute([
        'program' => ['CCS'],
        'fields' => 'emails',
    ]);

    expect($result['total_matched'])->toBe(3)
        ->and(collect($result['emails'])->sort()->values()->all())
        ->toBe(collect([$bsitStudent->email, $bscsStudent->email, $bsbaStudent->email])->sort()->values()->all());
});

/**
 * A term broad enough to match most of the catalog is a mistyped filter, not a
 * cohort, and must be reported rather than answered with an arbitrary slice.
 */
it('refuses a program term too broad to be a filter', function (): void {
    // One more than a department could plausibly hold, so the term is broad
    // rather than a real cohort filter.
    foreach (range(1, StudentDirectoryQuery::MAX_PROGRAMS_PER_TERM + 1) as $index) {
        makeCourse(sprintf('BS%02d', $index), sprintf('Program %d', $index));
    }

    $result = app(StudentDirectoryQuery::class)->execute(['program' => ['BS']]);

    expect($result['error'])->toBe('ambiguous_program')
        ->and($result['message'])->toContain('too many programs')
        ->and($result['total_matched'])->toBe(0)
        ->and($result['available_programs'])->not->toBeEmpty();
});

/**
 * The department code in a row must come from the eager-loaded relation.
 *
 * The course select omitted department_id, so the nested relation could not be
 * eager loaded and reading the department per row issued one query per student.
 */
it('reports the department code without querying per returned row', function (): void {
    $countQueries = function (): callable {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        return function () use (&$queries): int {
            return $queries;
        };
    };

    $course = makeCourse('BSIT', 'Information Technology', 'CCS');

    $one = Student::factory()->create(['course_id' => $course->id]);
    enrollForTerm($one, $course, $this->term, $this->semester);

    $forOne = $countQueries();
    $result = app(StudentDirectoryQuery::class)->execute(['program' => ['BSIT']]);
    $oneStudentQueries = $forOne();

    expect($result['students'][0]['department'])->toBe('CCS');

    foreach (range(1, 4) as $ignored) {
        $extra = Student::factory()->create(['course_id' => $course->id]);
        enrollForTerm($extra, $course, $this->term, $this->semester);
    }

    $forFive = $countQueries();
    app(StudentDirectoryQuery::class)->execute(['program' => ['BSIT']]);
    $fiveStudentQueries = $forFive();

    // Reading the relation per row would make the count grow with the cohort.
    expect($fiveStudentQueries)->toBe($oneStudentQueries);
});

/**
 * Finance totals must not reach another school's tuition.
 *
 * student_tuition has no tenant column and no global school scope, so matching
 * on the academic period alone aggregated every school sharing that term.
 */
it('scopes finance aggregates to the selected school', function (): void {
    $ownSchool = App\Models\School::factory()->create();
    $otherSchool = App\Models\School::factory()->create();

    app(TenantContext::class)->setCurrentSchool($ownSchool);

    $ownStudent = Student::factory()->create([
        'school_id' => $ownSchool->id,
        'institution_id' => $ownSchool->id,
    ]);
    $otherStudent = Student::factory()->create([
        'school_id' => $otherSchool->id,
        'institution_id' => $otherSchool->id,
    ]);

    makeTuition([
        'student_id' => $ownStudent->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'overall_tuition' => 10_000,
        'paid' => 0,
    ]);
    makeTuition([
        'student_id' => $otherStudent->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'overall_tuition' => 999_999,
        'paid' => 0,
    ]);

    $data = json_decode((string) (new QueryCampusAnalyticsTool)->handle(new Request([
        'category' => 'finance',
    ])), true);

    expect($data['records_in_scope'])->toBe(1)
        ->and($data['assessed_tuition'])->toEqual(10_000.0);
});

/**
 * Collections must come from verified payment allocations, not the stale column.
 *
 * syncTuitionBalance() writes only total_balance and status, so `paid` stays at
 * its last legacy value after a payment goes through the allocation flow.
 */
it('counts verified payment allocations as collections', function (): void {
    $school = App\Models\School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'institution_id' => $school->id,
    ]);

    $tuition = makeTuition([
        'student_id' => $student->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'overall_tuition' => 20_000,
        // The column still says nothing was paid, because the allocation flow
        // never writes it.
        'paid' => 0,
        'total_balance' => 20_000,
    ]);

    Transaction::create([
        'description' => 'Tuition payment',
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'transaction_date' => now(),
    ]);

    $transaction = Transaction::query()->latest('id')->first();

    PaymentAllocation::create([
        'transaction_id' => $transaction->id,
        'student_tuition_id' => $tuition->id,
        'student_id' => $student->id,
        'target_type' => 'assessment',
        'amount' => 8_000,
    ]);

    $data = json_decode((string) (new QueryCampusAnalyticsTool)->handle(new Request([
        'category' => 'finance',
    ])), true);

    expect($data['collected_payments'])->toEqual(8_000.0)
        ->and($data['outstanding_balance'])->toEqual(12_000.0)
        ->and($data['collection_efficiency_percent'])->toEqual(40.0);
});

/**
 * A pending transaction is not money received, so it must not be counted.
 *
 * A verified allocation on a second record is included alongside it, so this
 * pins the filter rather than merely observing a total of zero.
 */
it('ignores unverified payment allocations', function (): void {
    $school = App\Models\School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'institution_id' => $school->id,
    ]);

    $unverified = makeTuition([
        'student_id' => $student->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'overall_tuition' => 20_000,
        'paid' => 0,
    ]);
    $verified = makeTuition([
        'student_id' => $student->id,
        'school_year' => $this->term,
        'semester' => $this->semester,
        'overall_tuition' => 20_000,
        'paid' => 0,
    ]);

    Transaction::create([
        'description' => 'Unverified payment',
        'status' => 'Pending',
        'payment_method' => 'Cash',
        'transaction_date' => now(),
    ]);
    $pending = Transaction::query()->latest('id')->first();

    Transaction::create([
        'description' => 'Verified payment',
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'transaction_date' => now(),
    ]);
    $paid = Transaction::query()->latest('id')->first();

    PaymentAllocation::create([
        'transaction_id' => $pending->id,
        'student_tuition_id' => $unverified->id,
        'student_id' => $student->id,
        'target_type' => 'assessment',
        'amount' => 8_000,
    ]);
    PaymentAllocation::create([
        'transaction_id' => $paid->id,
        'student_tuition_id' => $verified->id,
        'student_id' => $student->id,
        'target_type' => 'assessment',
        'amount' => 5_000,
    ]);

    $data = json_decode((string) (new QueryCampusAnalyticsTool)->handle(new Request([
        'category' => 'finance',
    ])), true);

    // Only the verified 5,000 is collected; the pending 8,000 is ignored.
    expect($data['collected_payments'])->toEqual(5_000.0)
        ->and($data['outstanding_balance'])->toEqual(35_000.0);
});
