<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentClearance;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Manage student records in the selected school: create new student profiles, update status or program information, archive records, or fetch details.')]
final class ManageStudentTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action'));

        if ($action === 'get') {
            $user = $this->requireRead($request);
            $this->requirePermission($user, 'View:Student', 'You are not permitted to view student records.');

            $studentId = $request->get('student_id');
            $student = $this->resolveStudent((string) $studentId);

            if (! $student instanceof Student) {
                return Response::structured(['found' => false, 'message' => "Student '{$studentId}' not found."]);
            }

            return Response::structured([
                'found' => true,
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
                'year_level' => $student->academic_year,
                'program' => $student->Course?->code,
            ]);
        }

        $user = $this->requireWrite($request);
        if ($action === 'create') {
            $this->requirePermission($user, 'Create:Student', 'You are not permitted to create student records.');
        } elseif ($action === 'batch_upsert') {
            $this->requirePermission($user, 'Create:Student', 'You are not permitted to create student records.');
            $this->requirePermission($user, 'Update:Student', 'You are not permitted to modify student records.');
        } elseif ($action === 'archive' || $action === 'delete') {
            $this->requirePermission($user, 'Update:Student', 'You are not permitted to archive student records.');
        } else {
            $this->requirePermission($user, 'Update:Student', 'You are not permitted to modify student records.');
        }

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'batch_upsert' => $this->handleBatchUpsert($request),
            'archive', 'delete' => $this->handleArchive($request),
            default => Response::structured(['error' => true, 'message' => "Unsupported action '{$action}'. Valid: create, update, batch_upsert, archive, get."]),
        };
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'batch_upsert', 'archive', 'get'])->required()->description('Operation: create, update, batch_upsert, archive, get.'),
            'students' => $schema->array()->description('List of students for batch_upsert.')->items(
                $schema->object(fn ($s) => [
                    'student_id' => $s->string()->description('Student database ID or student number.'),
                    'lrn' => $s->string()->description('12-digit Learner Reference Number.'),
                    'first_name' => $s->string()->description('Student first name.'),
                    'last_name' => $s->string()->description('Student last name.'),
                    'email' => $s->string()->description('Student email address.'),
                    'course_code' => $s->string()->description('Program code (e.g. BSCS, BSHM).'),
                    'academic_year' => $s->integer()->description('Year level 1-5.'),
                    'status' => $s->string()->description('Status: enrolled, applicant, etc.'),
                    'gender' => $s->string()->description('Male, Female, Other.'),
                ])
            ),
            'student_id' => $schema->string()->description('Student database ID or student number.'),
            'first_name' => $schema->string()->description('Student first name.'),
            'last_name' => $schema->string()->description('Student last name.'),
            'email' => $schema->string()->description('Student email address.'),
            'course_code' => $schema->string()->description('Degree program code (e.g. BSCS).'),
            'course_id' => $schema->integer()->description('Degree program database ID.'),
            'academic_year' => $schema->integer()->description('Year level (1-5).'),
            'status' => $schema->string()->description('Status: enrolled, applicant, graduated, on_leave, dropped.'),
            'idempotency_key' => $schema->string()->description('Unique idempotency key for safe retries on create.'),
        ];
    }

    private function handleCreate(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'course_id' => ['nullable', 'integer'],
            'course_code' => ['nullable', 'string', 'max:50'],
            'academic_year' => ['nullable', 'integer', 'between:1,5'],
            'student_type' => ['nullable', 'string', 'in:college,shs'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:96'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $school = $this->school();
            $cacheKey = "mcp:create-student:{$school->id}:{$idempotencyKey}";
            $cachedStudentId = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if ($cachedStudentId) {
                $existingStudent = Student::query()->find($cachedStudentId);
                if ($existingStudent instanceof Student) {
                    return Response::structured([
                        'success' => true,
                        'action' => 'create',
                        'replayed' => true,
                        'idempotency_key' => $idempotencyKey,
                        'student' => [
                            'id' => $existingStudent->id,
                            'student_number' => (string) $existingStudent->student_id,
                            'name' => $existingStudent->full_name,
                            'email' => $existingStudent->email,
                            'status' => $existingStudent->status,
                        ],
                    ]);
                }
            }
        }

        $courseId = $validated['course_id'] ?? null;
        if (! $courseId && filled($validated['course_code'] ?? null)) {
            $course = Course::query()->where('code', $validated['course_code'])->first();
            $courseId = $course?->id;
        }

        if (! $courseId) {
            $defaultCourse = Course::query()->first();
            $courseId = $defaultCourse?->id ?? 1;
        }

        $studentType = isset($validated['student_type']) && $validated['student_type'] === 'shs'
            ? StudentType::Shs
            : StudentType::College;

        $school = $this->school();
        $student = DB::transaction(function () use ($validated, $courseId, $studentType, $school, $idempotencyKey) {
            $newStudentId = Student::generateNextId($studentType);
            $birthDate = filled($validated['birth_date'] ?? null)
                ? Carbon::parse($validated['birth_date'])
                : now()->subYears(18);

            $newStudent = Student::query()->create([
                'student_id' => $newStudentId,
                'institution_id' => $school->id,
                'school_id' => $school->id,
                'student_type' => $studentType->value,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'email' => $validated['email'],
                'course_id' => $courseId,
                'academic_year' => $validated['academic_year'] ?? 1,
                'gender' => $validated['gender'] ?? 'Other',
                'birth_date' => $birthDate->format('Y-m-d'),
                'age' => $birthDate->age,
                'status' => $validated['status'] ?? StudentStatus::Applicant->value,
            ]);

            $generalSetting = \App\Models\GeneralSetting::query()->first();
            if ($generalSetting instanceof \App\Models\GeneralSetting) {
                StudentClearance::createForCurrentSemester($newStudent, $generalSetting);
            }

            if ($idempotencyKey) {
                \Illuminate\Support\Facades\Cache::put("mcp:create-student:{$school->id}:{$idempotencyKey}", $newStudent->id, now()->addDays(7));
            }

            return $newStudent;
        });

        return Response::structured([
            'success' => true,
            'action' => 'create',
            'replayed' => false,
            'idempotency_key' => $idempotencyKey,
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
            ],
        ]);
    }

    private function handleUpdate(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'student_id' => ['required'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'academic_year' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['nullable', 'string'],
        ]);

        $student = $this->resolveStudent((string) $validated['student_id']);
        if (! $student instanceof Student) {
            return Response::structured(['error' => true, 'message' => "Student '{$validated['student_id']}' not found."]);
        }

        $updates = array_filter([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'academic_year' => $validated['academic_year'] ?? null,
            'status' => $validated['status'] ?? null,
        ], fn ($val) => $val !== null);

        $student->update($updates);

        return Response::structured([
            'success' => true,
            'action' => 'update',
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'status' => $student->status,
            ],
        ]);
    }

    private function handleBatchUpsert(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'students' => ['required', 'array', 'min:1'],
            'students.*.first_name' => ['required', 'string', 'max:100'],
            'students.*.last_name' => ['required', 'string', 'max:100'],
            'students.*.email' => ['required', 'email', 'max:150'],
            'students.*.student_id' => ['nullable'],
            'students.*.lrn' => ['nullable', 'string', 'max:20'],
            'students.*.course_code' => ['nullable', 'string', 'max:50'],
            'students.*.course_id' => ['nullable', 'integer'],
            'students.*.academic_year' => ['nullable', 'integer', 'between:1,5'],
            'students.*.gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'students.*.status' => ['nullable', 'string'],
        ]);

        $created = [];
        $updated = [];
        $errors = [];

        $generalSetting = \App\Models\GeneralSetting::query()->first();
        $defaultCourse = Course::query()->first();

        DB::transaction(function () use ($validated, &$created, &$updated, &$errors, $generalSetting, $defaultCourse) {
            foreach ($validated['students'] as $idx => $sData) {
                try {
                    $existing = null;
                    if (filled($sData['student_id'] ?? null)) {
                        $existing = $this->resolveStudent((string) $sData['student_id']);
                    }
                    if (! $existing && filled($sData['email'] ?? null)) {
                        $existing = $this->resolveStudent((string) $sData['email']);
                    }
                    if (! $existing && filled($sData['lrn'] ?? null)) {
                        $existing = Student::query()->where('lrn', mb_trim((string) $sData['lrn']))->first();
                    }

                    $courseId = $sData['course_id'] ?? null;
                    if (! $courseId && filled($sData['course_code'] ?? null)) {
                        $c = Course::query()->where('code', $sData['course_code'])->first();
                        $courseId = $c?->id;
                    }
                    if (! $courseId && ! $existing) {
                        $courseId = $defaultCourse?->id ?? 1;
                    }

                    if ($existing instanceof Student) {
                        $updates = array_filter([
                            'first_name' => $sData['first_name'] ?? null,
                            'last_name' => $sData['last_name'] ?? null,
                            'email' => $sData['email'] ?? null,
                            'course_id' => $courseId,
                            'academic_year' => $sData['academic_year'] ?? null,
                            'status' => $sData['status'] ?? null,
                            'gender' => $sData['gender'] ?? null,
                            'lrn' => $sData['lrn'] ?? null,
                        ], fn ($val) => $val !== null);

                        if (! empty($updates)) {
                            $existing->update($updates);
                        }

                        $updated[] = [
                            'id' => $existing->id,
                            'student_number' => (string) $existing->student_id,
                            'name' => $existing->full_name,
                            'email' => $existing->email,
                            'status' => $existing->status,
                        ];
                    } else {
                        $newStudentId = Student::generateNextId(StudentType::College);
                        $newStudent = Student::query()->create([
                            'student_id' => $newStudentId,
                            'institution_id' => 1,
                            'student_type' => StudentType::College->value,
                            'first_name' => $sData['first_name'],
                            'last_name' => $sData['last_name'],
                            'email' => $sData['email'],
                            'course_id' => $courseId,
                            'academic_year' => $sData['academic_year'] ?? 1,
                            'gender' => $sData['gender'] ?? 'Other',
                            'birth_date' => now()->subYears(18)->format('Y-m-d'),
                            'age' => 18,
                            'status' => $sData['status'] ?? StudentStatus::Applicant->value,
                            'lrn' => $sData['lrn'] ?? null,
                        ]);

                        if ($generalSetting instanceof \App\Models\GeneralSetting) {
                            StudentClearance::createForCurrentSemester($newStudent, $generalSetting);
                        }

                        $created[] = [
                            'id' => $newStudent->id,
                            'student_number' => (string) $newStudent->student_id,
                            'name' => $newStudent->full_name,
                            'email' => $newStudent->email,
                            'status' => $newStudent->status,
                        ];
                    }
                } catch (Throwable $rowEx) {
                    $errors[] = 'Row '.($idx + 1)." ({$sData['first_name']} {$sData['last_name']}): ".$rowEx->getMessage();
                }
            }
        });

        return Response::structured([
            'success' => true,
            'action' => 'batch_upsert',
            'created_count' => count($created),
            'updated_count' => count($updated),
            'total_processed' => count($created) + count($updated),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    private function handleArchive(Request $request): ResponseFactory
    {
        $student = $this->resolveStudent((string) $request->get('student_id'));
        if (! $student instanceof Student) {
            return Response::structured(['error' => true, 'message' => 'Student not found.']);
        }

        $student->update(['status' => StudentStatus::Dropped->value]);

        return Response::structured([
            'success' => true,
            'action' => 'archive',
            'message' => "Student {$student->full_name} marked as dropped.",
        ]);
    }

    private function resolveStudent(string $identifier): ?Student
    {
        if (is_numeric($identifier)) {
            $found = Student::query()->find((int) $identifier);
            if ($found) {
                return $found;
            }
        }

        return Student::query()
            ->where('student_id', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
}
