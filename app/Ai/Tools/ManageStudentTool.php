<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

final class ManageStudentTool implements Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Create, update, archive, or inspect student profiles and status in the institution. Modifications require administrator confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required.']);
        }

        $action = mb_strtolower((string) $request['action']);

        if ($action === 'get') {
            if (! $user->hasRole('super_admin') && ! $user->can('View:Student')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to view student records.']);
            }
        } elseif ($action === 'batch_upsert') {
            if (! $user->hasRole('super_admin') && ! $user->can('Create:Student') && ! $user->can('Update:Student')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to insert or update student records.']);
            }
        } elseif ($action === 'create') {
            if (! $user->hasRole('super_admin') && ! $user->can('Create:Student')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to create student records.']);
            }
        } elseif ($action === 'archive' || $action === 'delete') {
            if (! $user->hasRole('super_admin') && ! $user->can('Delete:Student') && ! $user->can('Update:Student')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to archive or delete student records.']);
            }
        } elseif ($action === 'update') {
            if (! $user->hasRole('super_admin') && ! $user->can('Update:Student')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to update student records.']);
            }
        }

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'batch_upsert' => $this->handleBatchUpsert($request),
            'archive', 'delete' => $this->handleArchive($request),
            'get' => $this->handleGet($request),
            default => json_encode(['error' => true, 'message' => "Unknown action '{$action}'. Supported actions: create, update, batch_upsert, archive, get."]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create', 'update', 'batch_upsert', 'archive', 'get'])
                ->required()
                ->description('The operation to perform on student records.'),
            'students' => $schema->array()
                ->description('Array of student records for batch_upsert.')
                ->items(
                    $schema->object(fn ($s) => [
                        'student_id' => $s->string()->description('Student ID, student number, or LRN to update if existing.'),
                        'lrn' => $s->string()->description('12-digit Learner Reference Number.'),
                        'first_name' => $s->string()->description('Student first name.'),
                        'last_name' => $s->string()->description('Student last name.'),
                        'middle_name' => $s->string()->description('Middle name.'),
                        'email' => $s->string()->description('Student email address.'),
                        'course_code' => $s->string()->description('Program code (e.g. BSIT, BSHM).'),
                        'academic_year' => $s->integer()->description('Year level 1-5.'),
                        'status' => $s->string()->description('Student status (enrolled, applicant, etc.).'),
                        'gender' => $s->string()->description('Gender: Male, Female, Other.'),
                        'student_type' => $s->string()->description('college or shs.'),
                        'birth_date' => $s->string()->description('YYYY-MM-DD birth date.'),
                    ])
                ),
            'student_id' => $schema->string()
                ->description('Database ID or official student number (e.g. "2024-0012" or "45") for update/archive/get.'),
            'first_name' => $schema->string()->description('Student first name for create/update.'),
            'last_name' => $schema->string()->description('Student last name for create/update.'),
            'middle_name' => $schema->string()->description('Optional middle name.'),
            'email' => $schema->string()->description('Student email address.'),
            'course_code' => $schema->string()->description('Academic program code (e.g. "BSCS", "BSIT", "BSA").'),
            'course_id' => $schema->integer()->description('Course database ID.'),
            'academic_year' => $schema->integer()->description('Year level (1 to 5).'),
            'student_type' => $schema->string()->enum(['college', 'shs'])->description('Student level: college or shs.'),
            'status' => $schema->string()->enum(['enrolled', 'applicant', 'graduated', 'on_leave', 'dropped'])->description('Student status.'),
            'gender' => $schema->string()->enum(['Male', 'Female', 'Other'])->description('Student gender.'),
            'birth_date' => $schema->string()->description('Date of birth in YYYY-MM-DD format.'),
            'reason' => $schema->string()->description('Reason for status change or archiving.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $action = mb_strtolower((string) ($request['action'] ?? ''));

        if ($action === 'get') {
            return false;
        }

        if ($action === 'create') {
            $name = mb_trim(($request['first_name'] ?? '').' '.($request['last_name'] ?? ''));
            $course = $request['course_code'] ?? ($request['course_id'] ?? 'unspecified course');

            return Approval::required("Create new official student record for '{$name}' in program {$course}?");
        }

        if ($action === 'batch_upsert') {
            $count = is_array($request['students'] ?? null) ? count($request['students']) : 0;

            return Approval::required("Batch insert or update {$count} student records in the student directory?");
        }

        if ($action === 'update') {
            $id = $request['student_id'] ?? 'unknown';

            return Approval::required("Apply updates to student record #{$id}?");
        }

        if ($action === 'archive' || $action === 'delete') {
            $id = $request['student_id'] ?? 'unknown';
            $reason = $request['reason'] ? " Reason: {$request['reason']}" : '';

            return Approval::required("Archive/deactivate student #{$id}?{$reason}");
        }

        return false;
    }

    private function handleCreate(Request $request): string
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'email' => 'required|email|max:150',
            'course_id' => 'nullable|integer',
            'course_code' => 'nullable|string|max:50',
            'academic_year' => 'nullable|integer|between:1,5',
            'student_type' => 'nullable|string|in:college,shs',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'birth_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

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

        try {
            return DB::transaction(function () use ($validated, $courseId, $studentType) {
                $newStudentId = Student::generateNextId($studentType);
                $birthDate = filled($validated['birth_date'] ?? null)
                    ? Carbon::parse($validated['birth_date'])
                    : now()->subYears(18);

                $student = Student::query()->create([
                    'student_id' => $newStudentId,
                    'institution_id' => 1,
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
                    StudentClearance::createForCurrentSemester($student, $generalSetting);
                }

                return json_encode([
                    'success' => true,
                    'action' => 'create',
                    'message' => "Successfully created student {$student->full_name}.",
                    'student' => [
                        'id' => $student->id,
                        'student_number' => (string) $student->student_id,
                        'name' => $student->full_name,
                        'email' => $student->email,
                        'program_id' => $student->course_id,
                        'status' => $student->status,
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            });
        } catch (Throwable $e) {
            return json_encode(['error' => true, 'message' => "Failed to create student: {$e->getMessage()}"]);
        }
    }

    private function handleUpdate(Request $request): string
    {
        $validated = $request->validate([
            'student_id' => 'required',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'course_id' => 'nullable|integer',
            'course_code' => 'nullable|string|max:50',
            'academic_year' => 'nullable|integer|between:1,5',
            'status' => 'nullable|string',
            'gender' => 'nullable|string',
        ]);

        $student = $this->resolveStudent((string) $validated['student_id']);
        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => "Student '{$validated['student_id']}' not found."]);
        }

        $courseId = $validated['course_id'] ?? null;
        if (! $courseId && filled($validated['course_code'] ?? null)) {
            $course = Course::query()->where('code', $validated['course_code'])->first();
            if ($course) {
                $courseId = $course->id;
            }
        }

        $updates = array_filter([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'course_id' => $courseId,
            'academic_year' => $validated['academic_year'] ?? null,
            'status' => $validated['status'] ?? null,
            'gender' => $validated['gender'] ?? null,
        ], fn ($val) => $val !== null);

        if (empty($updates)) {
            return json_encode(['error' => true, 'message' => 'No valid fields provided to update.']);
        }

        $student->update($updates);

        return json_encode([
            'success' => true,
            'action' => 'update',
            'message' => "Successfully updated student {$student->full_name}.",
            'updated_fields' => array_keys($updates),
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
                'academic_year' => $student->academic_year,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleArchive(Request $request): string
    {
        $validated = $request->validate([
            'student_id' => 'required',
            'reason' => 'nullable|string|max:500',
        ]);

        $student = $this->resolveStudent((string) $validated['student_id']);
        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => "Student '{$validated['student_id']}' not found."]);
        }

        $student->update([
            'status' => StudentStatus::Dropped->value,
        ]);

        return json_encode([
            'success' => true,
            'action' => 'archive',
            'message' => "Student {$student->full_name} (ID: {$student->student_id}) marked as dropped/archived.",
            'reason' => $validated['reason'] ?? 'Archived via administrative copilot.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleBatchUpsert(Request $request): string
    {
        $validated = $request->validate([
            'students' => 'required|array|min:1',
            'students.*.first_name' => 'required|string|max:100',
            'students.*.last_name' => 'required|string|max:100',
            'students.*.middle_name' => 'nullable|string|max:100',
            'students.*.email' => 'required|email|max:150',
            'students.*.student_id' => 'nullable',
            'students.*.lrn' => 'nullable|string|max:20',
            'students.*.course_code' => 'nullable|string|max:50',
            'students.*.course_id' => 'nullable|integer',
            'students.*.academic_year' => 'nullable|integer|between:1,5',
            'students.*.student_type' => 'nullable|string|in:college,shs',
            'students.*.gender' => 'nullable|string|in:Male,Female,Other',
            'students.*.birth_date' => 'nullable|date',
            'students.*.status' => 'nullable|string',
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
                            'middle_name' => $sData['middle_name'] ?? null,
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
                        $studentType = isset($sData['student_type']) && $sData['student_type'] === 'shs'
                            ? StudentType::Shs
                            : StudentType::College;

                        $newStudentId = Student::generateNextId($studentType);
                        $birthDate = filled($sData['birth_date'] ?? null)
                            ? Carbon::parse($sData['birth_date'])
                            : now()->subYears(18);

                        $newStudent = Student::query()->create([
                            'student_id' => $newStudentId,
                            'institution_id' => 1,
                            'student_type' => $studentType->value,
                            'first_name' => $sData['first_name'],
                            'last_name' => $sData['last_name'],
                            'middle_name' => $sData['middle_name'] ?? null,
                            'email' => $sData['email'],
                            'course_id' => $courseId,
                            'academic_year' => $sData['academic_year'] ?? 1,
                            'gender' => $sData['gender'] ?? 'Other',
                            'birth_date' => $birthDate->format('Y-m-d'),
                            'age' => $birthDate->age,
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

        return json_encode([
            'success' => true,
            'action' => 'batch_upsert',
            'created_count' => count($created),
            'updated_count' => count($updated),
            'total_processed' => count($created) + count($updated),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleGet(Request $request): string
    {
        $id = $request['student_id'] ?? null;
        if (! $id) {
            return json_encode(['error' => true, 'message' => 'student_id is required to fetch details.']);
        }

        $student = $this->resolveStudent((string) $id);
        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => "Student '{$id}' not found."]);
        }

        return json_encode([
            'found' => true,
            'id' => $student->id,
            'student_number' => (string) $student->student_id,
            'name' => $student->full_name,
            'email' => $student->email,
            'status' => $student->status,
            'program' => $student->Course?->code ?? 'N/A',
            'year_level' => $student->academic_year,
            'gender' => $student->gender,
            'birth_date' => $student->birth_date?->format('Y-m-d'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
