<?php

declare(strict_types=1);

use App\Enums\SubjectEnrolledEnum;
use App\Enums\UserRole;
use App\Http\Requests\StoreCurriculumProgramRequest;
use App\Http\Requests\StoreCurriculumSubjectRequest;
use App\Http\Requests\UpdateCurriculumProgramRequest;
use App\Http\Requests\UpdateCurriculumSubjectRequest;
use App\Mcp\Servers\KoAkademyServer;
use App\Mcp\Tools\ManageStudentTool;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Department;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

it('allows two courses with the same code in different schools', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    $courseA = Course::factory()->create([
        'school_id' => $schoolA->id,
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology A',
    ]);

    $courseB = Course::factory()->create([
        'school_id' => $schoolB->id,
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology B',
    ]);

    expect($courseA->code)->toBe('BSIT')
        ->and($courseB->code)->toBe('BSIT')
        ->and($courseA->school_id)->toBe($schoolA->id)
        ->and($courseB->school_id)->toBe($schoolB->id);
});

it('rejects duplicate course code within the same school at database level', function (): void {
    $school = School::factory()->create();

    Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSCS',
        'title' => 'Bachelor of Science in Computer Science',
    ]);

    expect(fn () => Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSCS',
        'title' => 'Duplicate BSCS',
    ]))->toThrow(QueryException::class);
});

it('allows two subjects with the same code in different schools', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    $courseA = Course::factory()->create(['school_id' => $schoolA->id]);
    $courseB = Course::factory()->create(['school_id' => $schoolB->id]);

    $subjectA = Subject::factory()->create([
        'school_id' => $schoolA->id,
        'course_id' => $courseA->id,
        'code' => 'MATH101',
        'title' => 'College Algebra A',
    ]);

    $subjectB = Subject::factory()->create([
        'school_id' => $schoolB->id,
        'course_id' => $courseB->id,
        'code' => 'MATH101',
        'title' => 'College Algebra B',
    ]);

    expect($subjectA->code)->toBe('MATH101')
        ->and($subjectB->code)->toBe('MATH101')
        ->and($subjectA->school_id)->toBe($schoolA->id)
        ->and($subjectB->school_id)->toBe($schoolB->id);
});

it('allows two subjects with the same code in different programs of the same school', function (): void {
    $school = School::factory()->create();
    $programA = Course::factory()->create(['school_id' => $school->id]);
    $programB = Course::factory()->create(['school_id' => $school->id]);

    $subjectA = Subject::factory()->create([
        'school_id' => $school->id,
        'course_id' => $programA->id,
        'code' => 'NSTP 1',
        'title' => 'National Service Training Program I',
    ]);

    $subjectB = Subject::factory()->create([
        'school_id' => $school->id,
        'course_id' => $programB->id,
        'code' => 'NSTP 1',
        'title' => 'National Service Training Program I',
    ]);

    expect($subjectA->id)->not->toBe($subjectB->id)
        ->and($subjectA->course_id)->toBe($programA->id)
        ->and($subjectB->course_id)->toBe($programB->id);
});

it('rejects duplicate subject code within the same program at database level', function (): void {
    $school = School::factory()->create();
    $course = Course::factory()->create(['school_id' => $school->id]);

    Subject::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'code' => 'ENG101',
        'title' => 'Purposive Communication',
    ]);

    expect(fn () => Subject::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'code' => 'ENG101',
        'title' => 'Duplicate English',
    ]))->toThrow(QueryException::class);
});

it('automatically infers school_id on Subject from course relationship', function (): void {
    $school = School::factory()->create();
    $course = Course::factory()->create(['school_id' => $school->id]);

    $subject = Subject::query()->create([
        'code' => 'AUTO-SCH-01',
        'title' => 'Auto School Infer Subject',
        'units' => 3,
        'lecture' => 3,
        'laboratory' => 0,
        'academic_year' => 1,
        'semester' => 1,
        'course_id' => $course->id,
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'is_credited' => true,
    ]);

    expect($subject->school_id)->toBe($school->id);
});

it('validates course code uniqueness scoped to school in curriculum program requests', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $deptA = Department::factory()->create(['school_id' => $schoolA->id]);
    $type = CourseType::factory()->create();

    // Create course in school B
    Course::factory()->create([
        'school_id' => $schoolB->id,
        'code' => 'BSN',
        'title' => 'Nursing B',
    ]);

    app(TenantContext::class)->setCurrentSchoolId($schoolA->id);

    // Storing BSN in school A should pass validation
    $storeRequest = new StoreCurriculumProgramRequest;
    $validator = Validator::make([
        'code' => 'BSN',
        'title' => 'Nursing A',
        'department_id' => $deptA->id,
        'course_type_id' => $type->id,
        'curriculum_kind' => 'program',
    ], $storeRequest->rules());

    expect($validator->passes())->toBeTrue();

    // Create course in school A
    $courseA = Course::factory()->create([
        'school_id' => $schoolA->id,
        'code' => 'BSN',
        'title' => 'Nursing A',
        'department_id' => $deptA->id,
        'course_type_id' => $type->id,
    ]);

    // Storing BSN in school A again should fail validation
    $validatorDuplicate = Validator::make([
        'code' => 'BSN',
        'title' => 'Another Nursing A',
        'department_id' => $deptA->id,
        'course_type_id' => $type->id,
        'curriculum_kind' => 'program',
    ], $storeRequest->rules());

    expect($validatorDuplicate->fails())->toBeTrue()
        ->and($validatorDuplicate->errors()->has('code'))->toBeTrue();

    // Updating course A with its own code should pass
    $updateRequest = new UpdateCurriculumProgramRequest;
    $updateRequest->setRouteResolver(fn () => new class($courseA)
    {
        public function __construct(public $course) {}

        public function parameter($name)
        {
            return $this->course;
        }
    });
    $validatorUpdate = Validator::make([
        'code' => 'BSN',
        'title' => 'Updated Nursing A Title',
        'department_id' => $deptA->id,
        'course_type_id' => $type->id,
        'curriculum_kind' => 'program',
    ], $updateRequest->rules());

    expect($validatorUpdate->passes())->toBeTrue();
});

it('validates subject code uniqueness scoped to program in curriculum subject requests', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $courseA = Course::factory()->create(['school_id' => $schoolA->id]);
    $courseB = Course::factory()->create(['school_id' => $schoolB->id]);
    $programA2 = Course::factory()->create(['school_id' => $schoolA->id]);

    // Subject exists in School B
    Subject::factory()->create([
        'school_id' => $schoolB->id,
        'course_id' => $courseB->id,
        'code' => 'BIO101',
    ]);

    app(TenantContext::class)->setCurrentSchoolId($schoolA->id);

    // Storing BIO101 in School A should pass validation
    $storeRequest = new StoreCurriculumSubjectRequest;
    $storeRequest->setRouteResolver(fn () => new class($courseA)
    {
        public function __construct(public $course) {}

        public function parameter($name)
        {
            return $this->course;
        }
    });

    $validator = Validator::make([
        'code' => 'BIO101',
        'title' => 'General Biology',
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'units' => 3,
    ], $storeRequest->rules());

    expect($validator->passes())->toBeTrue();

    // Create BIO101 in School A
    $subjectA = Subject::factory()->create([
        'school_id' => $schoolA->id,
        'course_id' => $courseA->id,
        'code' => 'BIO101',
    ]);

    // Storing BIO101 again in School A should fail
    $validatorDuplicate = Validator::make([
        'code' => 'BIO101',
        'title' => 'General Biology Duplicate',
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'units' => 3,
    ], $storeRequest->rules());

    expect($validatorDuplicate->fails())->toBeTrue()
        ->and($validatorDuplicate->errors()->has('code'))->toBeTrue();

    // The same code in another program of the same school is a different
    // subject and is allowed.
    $otherProgramRequest = new StoreCurriculumSubjectRequest;
    $otherProgramRequest->setRouteResolver(fn () => new class($programA2)
    {
        public function __construct(public $course) {}

        public function parameter($name)
        {
            return $this->course;
        }
    });

    $validatorOtherProgram = Validator::make([
        'code' => 'BIO101',
        'title' => 'General Biology For Another Program',
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'units' => 3,
    ], $otherProgramRequest->rules());

    expect($validatorOtherProgram->passes())->toBeTrue();

    // Updating subject A with its own code should pass
    $updateRequest = new UpdateCurriculumSubjectRequest;
    $updateRequest->setRouteResolver(fn () => new class($courseA, $subjectA)
    {
        public function __construct(public $course, public $subject) {}

        public function parameter($name)
        {
            return $name === 'course' ? $this->course : $this->subject;
        }
    });

    $validatorUpdate = Validator::make([
        'code' => 'BIO101',
        'title' => 'Updated Biology Title',
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'units' => 3,
    ], $updateRequest->rules());

    expect($validatorUpdate->passes())->toBeTrue();
});

it('backfills missing school_id on courses and subjects', function (): void {
    $school = School::factory()->create();
    $dept = Department::factory()->create(['school_id' => $school->id]);

    $courseId = DB::table('courses')->insertGetId([
        'code' => 'BACKFILL-CRS',
        'title' => 'Backfill Course Test',
        'school_id' => null,
        'department_id' => $dept->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $subjectId = DB::table('subject')->insertGetId([
        'code' => 'BACKFILL-SUB',
        'title' => 'Backfill Subject Test',
        'classification' => 'internal',
        'units' => 3,
        'lecture' => 3,
        'laboratory' => 0,
        'semester' => 1,
        'course_id' => $courseId,
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require base_path('database/migrations/2026_11_09_000002_scope_course_and_subject_codes_to_school.php');
    $migration->up();

    $course = DB::table('courses')->where('id', $courseId)->first();
    $subject = DB::table('subject')->where('id', $subjectId)->first();

    expect($course->school_id)->toBe($school->id)
        ->and($subject->school_id)->toBe($school->id);
});

it('reports the duplicated program codes instead of failing with a raw database error', function (): void {
    $school = School::factory()->create();

    // The school scoped key cannot hold the duplicated rows. Production
    // reached that state because the global key was never applied there.
    DB::statement('DROP INDEX IF EXISTS courses_school_id_code_unique');

    foreach (['First', 'Second'] as $title) {
        DB::table('courses')->insert([
            'code' => 'DUP-CRS',
            'title' => $title,
            'school_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $migration = require base_path('database/migrations/2026_11_09_000002_scope_course_and_subject_codes_to_school.php');

    try {
        $migration->up();
        $this->fail('The migration should have refused the duplicated program code.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toContain('program code(s) are still used twice in the same school')
            ->toContain('DUP-CRS')
            ->toContain('2026_11_08_000001_merge_duplicate_courses');
    }
});

it('reports the duplicated subject codes instead of failing with a raw database error', function (): void {
    $school = School::factory()->create();
    $course = Course::factory()->create(['school_id' => $school->id]);

    DB::statement('DROP INDEX IF EXISTS subject_school_id_course_id_code_unique');

    foreach (['First', 'Second'] as $title) {
        DB::table('subject')->insert([
            'classification' => 'internal',
            'code' => 'DUP-SUB',
            'title' => $title,
            'units' => 3,
            'lecture' => 3,
            'laboratory' => 0,
            'semester' => 1,
            'course_id' => $course->id,
            'school_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $migration = require base_path('database/migrations/2026_11_09_000002_scope_course_and_subject_codes_to_school.php');

    try {
        $migration->up();
        $this->fail('The migration should have refused the duplicated subject code.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toContain('subject code(s) are still used twice in the same program')
            ->toContain('DUP-SUB');
    }
});

it('scopes student lookup to authenticated school in ManageStudentTool', function (): void {
    config(['api.mcp.enabled' => true]);

    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    $userA = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $schoolA->id,
    ]);
    Permission::firstOrCreate(['name' => 'View:Student', 'guard_name' => 'web']);
    $userA->givePermissionTo('View:Student');
    $userA->createToken('Admin Agent', ['mcp:read']);

    $studentA = Student::factory()->create([
        'school_id' => $schoolA->id,
        'student_id' => 'STU-SAME-01',
        'first_name' => 'Alice',
        'last_name' => 'Alpha',
        'email' => 'alice@alpha.edu',
    ]);

    $studentB = Student::factory()->create([
        'school_id' => $schoolB->id,
        'student_id' => 'STU-SAME-01',
        'first_name' => 'Bob',
        'last_name' => 'Beta',
        'email' => 'bob@beta.edu',
    ]);

    app(TenantContext::class)->setCurrentSchool($schoolA);

    $response = KoAkademyServer::actingAs($userA)
        ->tool(ManageStudentTool::class, [
            'action' => 'get',
            'student_id' => 'STU-SAME-01',
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($studentA): void {
            $json->where('found', true)
                ->where('id', $studentA->id)
                ->where('email', 'alice@alpha.edu')
                ->etc();
        });
});
