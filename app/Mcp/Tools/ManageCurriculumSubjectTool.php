<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Manage curriculum subjects: create new subjects under degree programs, update credits/prerequisites, delete unassigned subjects, or inspect details.')]
final class ManageCurriculumSubjectTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action'));

        if ($action === 'get') {
            $user = $this->requireRead($request);
            $this->requirePermission($user, 'View:Subject', 'You are not permitted to view curriculum subjects.');

            $subject = $this->resolveSubject($request);
            if (! $subject instanceof Subject) {
                return Response::structured(['found' => false, 'message' => 'Subject not found.']);
            }

            return Response::structured([
                'found' => true,
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
                'year_level' => $subject->academic_year,
                'semester' => $subject->semester,
                'program' => $subject->course?->code,
            ]);
        }

        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:Subject', 'You are not permitted to modify curriculum subjects.');

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'delete' => $this->handleDelete($request),
            default => Response::structured(['error' => true, 'message' => "Unsupported action '{$action}'."]),
        };
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'delete', 'get'])->required()->description('Operation: create, update, delete, get.'),
            'subject_id' => $schema->integer()->description('Subject database ID.'),
            'code' => $schema->string()->description('Subject code (e.g. CS101).'),
            'title' => $schema->string()->description('Subject title.'),
            'units' => $schema->integer()->description('Credit units.'),
            'academic_year' => $schema->integer()->description('Year level (1-5).'),
            'semester' => $schema->integer()->enum([1, 2])->description('Semester (1 or 2).'),
            'course_code' => $schema->string()->description('Program code (e.g. BSCS).'),
        ];
    }

    private function handleCreate(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:150'],
            'units' => ['required', 'integer', 'between:1,12'],
            'lecture' => ['nullable', 'integer', 'between:0,12'],
            'laboratory' => ['nullable', 'integer', 'between:0,12'],
            'academic_year' => ['required', 'integer', 'between:1,5'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'course_id' => ['nullable', 'integer'],
            'course_code' => ['nullable', 'string', 'max:50'],
        ]);

        $courseId = $validated['course_id'] ?? null;
        if (! $courseId && filled($validated['course_code'] ?? null)) {
            $course = Course::query()->where('code', $validated['course_code'])->first();
            $courseId = $course?->id;
        }

        if (! $courseId) {
            $courseId = Course::query()->value('id') ?? 1;
        }

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
        ]);

        return Response::structured([
            'success' => true,
            'action' => 'create',
            'subject' => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
            ],
        ]);
    }

    private function handleUpdate(Request $request): ResponseFactory
    {
        $subject = $this->resolveSubject($request);
        if (! $subject instanceof Subject) {
            return Response::structured(['error' => true, 'message' => 'Subject not found.']);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'units' => ['nullable', 'integer', 'between:1,12'],
            'academic_year' => ['nullable', 'integer', 'between:1,5'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
        ]);

        $updates = array_filter([
            'title' => $validated['title'] ?? null,
            'units' => $validated['units'] ?? null,
            'academic_year' => $validated['academic_year'] ?? null,
            'semester' => $validated['semester'] ?? null,
        ], fn ($val) => $val !== null);

        $subject->update($updates);

        return Response::structured([
            'success' => true,
            'action' => 'update',
            'subject' => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
            ],
        ]);
    }

    private function handleDelete(Request $request): ResponseFactory
    {
        $subject = $this->resolveSubject($request);
        if (! $subject instanceof Subject) {
            return Response::structured(['error' => true, 'message' => 'Subject not found.']);
        }

        $count = $subject->classes()->count();
        if ($count > 0) {
            return Response::structured(['error' => true, 'message' => "Cannot delete {$subject->code}; {$count} active class section(s) depend on it."]);
        }

        $code = $subject->code;
        $subject->delete();

        return Response::structured([
            'success' => true,
            'action' => 'delete',
            'message' => "Subject {$code} deleted.",
        ]);
    }

    private function resolveSubject(Request $request): ?Subject
    {
        $id = $request->get('subject_id');
        if ($id && is_numeric($id)) {
            $found = Subject::query()->find((int) $id);
            if ($found) {
                return $found;
            }
        }

        $code = $request->get('code');
        if ($code) {
            return Subject::query()->where('code', mb_trim((string) $code))->first();
        }

        return null;
    }
}
