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
        $this->requirePermission($user, 'Update:Student', 'You are not permitted to modify student records.');

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'archive', 'delete' => $this->handleArchive($request),
            default => Response::structured(['error' => true, 'message' => "Unsupported action '{$action}'. Valid: create, update, archive, get."]),
        };
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'archive', 'get'])->required()->description('Operation: create, update, archive, get.'),
            'student_id' => $schema->string()->description('Student database ID or student number.'),
            'first_name' => $schema->string()->description('Student first name.'),
            'last_name' => $schema->string()->description('Student last name.'),
            'email' => $schema->string()->description('Student email address.'),
            'course_code' => $schema->string()->description('Degree program code (e.g. BSCS).'),
            'course_id' => $schema->integer()->description('Degree program database ID.'),
            'academic_year' => $schema->integer()->description('Year level (1-5).'),
            'status' => $schema->string()->description('Status: enrolled, applicant, graduated, on_leave, dropped.'),
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

        $student = DB::transaction(function () use ($validated, $courseId, $studentType) {
            $newStudentId = Student::generateNextId($studentType);
            $birthDate = filled($validated['birth_date'] ?? null)
                ? Carbon::parse($validated['birth_date'])
                : now()->subYears(18);

            $newStudent = Student::query()->create([
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
                StudentClearance::createForCurrentSemester($newStudent, $generalSetting);
            }

            return $newStudent;
        });

        return Response::structured([
            'success' => true,
            'action' => 'create',
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
