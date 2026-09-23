<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Course;
use App\Models\Subject;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

final class ManageCurriculumSubjectTool implements Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Create, update, delete, or retrieve academic curriculum subjects within degree programs. Modifying or deleting subjects requires administrator approval.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = mb_strtolower((string) $request['action']);

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'delete' => $this->handleDelete($request),
            'get' => $this->handleGet($request),
            default => json_encode(['error' => true, 'message' => "Unknown action '{$action}'. Supported: create, update, delete, get."]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create', 'update', 'delete', 'get'])
                ->required()
                ->description('Operation to execute on curriculum subjects.'),
            'subject_id' => $schema->integer()->description('Subject database ID (for update/delete/get).'),
            'code' => $schema->string()->description('Subject code (e.g. "CS101", "MATH101").'),
            'title' => $schema->string()->description('Descriptive subject title (e.g. "Introduction to Computer Science").'),
            'units' => $schema->integer()->description('Credit units (e.g. 3).'),
            'lecture' => $schema->integer()->description('Weekly lecture hours (e.g. 3).'),
            'laboratory' => $schema->integer()->description('Weekly laboratory hours (e.g. 0).'),
            'academic_year' => $schema->integer()->description('Target curriculum year level (1, 2, 3, 4, 5).'),
            'semester' => $schema->integer()->enum([1, 2])->description('Curriculum semester (1 or 2).'),
            'course_code' => $schema->string()->description('Degree program code (e.g. "BSCS", "BSIT").'),
            'course_id' => $schema->integer()->description('Degree program course ID.'),
            'prerequisites' => $schema->array()->items($schema->string())->description('List of prerequisite subject codes.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $action = mb_strtolower((string) ($request['action'] ?? ''));

        if ($action === 'get') {
            return false;
        }

        if ($action === 'create') {
            $code = $request['code'] ?? 'New Subject';
            $title = $request['title'] ?? '';
            $program = $request['course_code'] ?? ($request['course_id'] ?? 'Program');

            return Approval::required("Create curriculum subject '{$code}' - {$title} under program {$program}?");
        }

        if ($action === 'update') {
            $id = $request['subject_id'] ?? ($request['code'] ?? 'Subject');

            return Approval::required("Save updates to curriculum subject #{$id}?");
        }

        if ($action === 'delete') {
            $id = $request['subject_id'] ?? ($request['code'] ?? 'Subject');

            return Approval::required("Permanently delete curriculum subject #{$id} from course catalog?");
        }

        return false;
    }

    private function handleCreate(Request $request): string
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'title' => 'required|string|max:150',
            'units' => 'required|integer|between:1,12',
            'lecture' => 'nullable|integer|between:0,12',
            'laboratory' => 'nullable|integer|between:0,12',
            'academic_year' => 'required|integer|between:1,5',
            'semester' => 'required|integer|in:1,2',
            'course_id' => 'nullable|integer',
            'course_code' => 'nullable|string|max:50',
            'prerequisites' => 'nullable|array',
        ]);

        $courseId = $validated['course_id'] ?? null;
        if (! $courseId && filled($validated['course_code'] ?? null)) {
            $course = Course::query()->where('code', $validated['course_code'])->first();
            $courseId = $course?->id;
        }

        if (! $courseId) {
            $courseId = Course::query()->value('id') ?? 1;
        }

        try {
            $subject = Subject::query()->create([
                'code' => mb_strtoupper(mb_trim($validated['code'])),
                'title' => mb_trim($validated['title']),
                'units' => (int) $validated['units'],
                'lecture' => (int) ($validated['lecture'] ?? $validated['units']),
                'laboratory' => (int) ($validated['laboratory'] ?? 0),
                'academic_year' => (int) $validated['academic_year'],
                'semester' => (int) $validated['semester'],
                'course_id' => $courseId,
                'classification' => \App\Enums\SubjectEnrolledEnum::INTERNAL->value,
                'is_credited' => true,
                'pre_riquisite' => $validated['prerequisites'] ?? [],
            ]);

            return json_encode([
                'success' => true,
                'action' => 'create',
                'message' => "Successfully created subject {$subject->code} - {$subject->title}.",
                'subject' => [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'title' => $subject->title,
                    'units' => $subject->units,
                    'year_level' => $subject->academic_year,
                    'semester' => $subject->semester,
                    'course_id' => $subject->course_id,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            return json_encode(['error' => true, 'message' => "Failed to create subject: {$e->getMessage()}"]);
        }
    }

    private function handleUpdate(Request $request): string
    {
        $subject = $this->resolveSubject($request);
        if (! $subject instanceof Subject) {
            return json_encode(['error' => true, 'message' => 'Subject not found.']);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:150',
            'units' => 'nullable|integer|between:1,12',
            'lecture' => 'nullable|integer|between:0,12',
            'laboratory' => 'nullable|integer|between:0,12',
            'academic_year' => 'nullable|integer|between:1,5',
            'semester' => 'nullable|integer|in:1,2',
            'prerequisites' => 'nullable|array',
        ]);

        $updates = array_filter([
            'title' => $validated['title'] ?? null,
            'units' => $validated['units'] ?? null,
            'lecture' => $validated['lecture'] ?? null,
            'laboratory' => $validated['laboratory'] ?? null,
            'academic_year' => $validated['academic_year'] ?? null,
            'semester' => $validated['semester'] ?? null,
            'pre_riquisite' => $validated['prerequisites'] ?? null,
        ], fn ($val) => $val !== null);

        $subject->update($updates);

        return json_encode([
            'success' => true,
            'action' => 'update',
            'message' => "Successfully updated subject {$subject->code}.",
            'subject' => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
                'academic_year' => $subject->academic_year,
                'semester' => $subject->semester,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleDelete(Request $request): string
    {
        $subject = $this->resolveSubject($request);
        if (! $subject instanceof Subject) {
            return json_encode(['error' => true, 'message' => 'Subject not found.']);
        }

        $activeClassesCount = $subject->classes()->count();
        if ($activeClassesCount > 0) {
            return json_encode([
                'error' => true,
                'message' => "Cannot delete {$subject->code} because it is assigned to {$activeClassesCount} active class section(s).",
            ]);
        }

        $code = $subject->code;
        $title = $subject->title;
        $subject->delete();

        return json_encode([
            'success' => true,
            'action' => 'delete',
            'message' => "Successfully deleted subject {$code} ({$title}).",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleGet(Request $request): string
    {
        $subject = $this->resolveSubject($request);
        if (! $subject instanceof Subject) {
            return json_encode(['error' => true, 'message' => 'Subject not found.']);
        }

        return json_encode([
            'found' => true,
            'id' => $subject->id,
            'code' => $subject->code,
            'title' => $subject->title,
            'units' => $subject->units,
            'lecture_hours' => $subject->lecture,
            'lab_hours' => $subject->laboratory,
            'academic_year' => $subject->academic_year,
            'semester' => $subject->semester,
            'course' => $subject->course?->code ?? 'N/A',
            'prerequisites' => $subject->pre_riquisite ?? [],
            'active_sections_count' => $subject->classes()->count(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function resolveSubject(Request $request): ?Subject
    {
        $id = $request['subject_id'] ?? null;
        if ($id && is_numeric($id)) {
            $found = Subject::query()->find((int) $id);
            if ($found) {
                return $found;
            }
        }

        $code = $request['code'] ?? null;
        if ($code) {
            return Subject::query()->where('code', mb_trim((string) $code))->first();
        }

        return null;
    }
}
