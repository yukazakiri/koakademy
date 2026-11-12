<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Exceptions\AmbiguousSubjectException;
use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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
        try {
            return $this->dispatch($request);
        } catch (AmbiguousSubjectException $exception) {
            return Response::structured(['error' => true, 'message' => $exception->getMessage()]);
        }
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'batch_upsert', 'delete', 'get'])->required()->description('Operation: create, update, batch_upsert, delete, get.'),
            'subjects' => $schema->array()->description('List of subjects for batch_upsert.')->items(
                $schema->object(fn ($s) => [
                    'code' => $s->string()->required()->description('Subject code (e.g. CS101).'),
                    'title' => $s->string()->required()->description('Subject title.'),
                    'units' => $s->integer()->required()->description('Credit units.'),
                    'lecture' => $s->integer()->description('Lecture hours.'),
                    'laboratory' => $s->integer()->description('Lab hours.'),
                    'academic_year' => $s->integer()->description('Year level 1-5.'),
                    'semester' => $s->integer()->description('Semester 1 or 2.'),
                    'course_code' => $s->string()->description('Program code.'),
                ])
            ),
            'subject_id' => $schema->integer()->description('Subject database ID.'),
            'code' => $schema->string()->description('Subject code (e.g. CS101).'),
            'title' => $schema->string()->description('Subject title.'),
            'units' => $schema->integer()->description('Credit units.'),
            'academic_year' => $schema->integer()->description('Year level (1-5).'),
            'semester' => $schema->integer()->enum([1, 2])->description('Semester (1 or 2).'),
            'course_code' => $schema->string()->description('Program code (e.g. BSCS).'),
        ];
    }

    private function dispatch(Request $request): ResponseFactory
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
            'batch_upsert' => $this->handleBatchUpsert($request),
            'delete' => $this->handleDelete($request),
            default => Response::structured(['error' => true, 'message' => "Unsupported action '{$action}'."]),
        };
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

        $school = $this->school();
        $courseId = $validated['course_id'] ?? null;
        if (! $courseId && filled($validated['course_code'] ?? null)) {
            $course = Course::query()->where('school_id', $school->id)->where('code', $validated['course_code'])->first();
            $courseId = $course?->id;
        }

        if (! $courseId) {
            $courseId = Course::query()->where('school_id', $school->id)->value('id') ?? Course::query()->value('id') ?? 1;
        }

        // A subject may only be attached to a program of the current school.
        if (! Course::query()->where('school_id', $school->id)->whereKey($courseId)->exists()) {
            return Response::structured([
                'error' => true,
                'message' => "Program {$courseId} does not belong to this school.",
            ]);
        }

        $code = mb_strtoupper(mb_trim($validated['code']));

        // A subject code is unique inside the program it belongs to.
        if (Subject::query()->where('course_id', $courseId)->where('code', $code)->exists()) {
            return Response::structured([
                'error' => true,
                'message' => "Subject code {$code} already exists in this program.",
            ]);
        }

        $subject = Subject::query()->create([
            'school_id' => $school->id,
            'code' => $code,
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

    private function handleBatchUpsert(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.code' => ['required', 'string', 'max:50'],
            'subjects.*.title' => ['required', 'string', 'max:150'],
            'subjects.*.units' => ['required', 'integer', 'between:0,12'],
            'subjects.*.lecture' => ['nullable', 'integer', 'between:0,40'],
            'subjects.*.laboratory' => ['nullable', 'integer', 'between:0,40'],
            'subjects.*.academic_year' => ['nullable', 'integer', 'between:1,5'],
            'subjects.*.semester' => ['nullable', 'integer', 'in:1,2'],
            'subjects.*.course_code' => ['nullable', 'string', 'max:50'],
        ]);

        $school = $this->school();
        $defaultCourse = Course::query()->where('school_id', $school->id)->first() ?? Course::query()->first();
        $created = [];
        $updated = [];

        DB::transaction(function () use ($validated, $defaultCourse, $school, &$created, &$updated) {
            foreach ($validated['subjects'] as $sData) {
                $code = mb_strtoupper(mb_trim((string) $sData['code']));
                $title = mb_trim((string) $sData['title']);
                $units = (int) $sData['units'];
                $lecture = (int) ($sData['lecture'] ?? $units);
                $lab = (int) ($sData['laboratory'] ?? 0);
                $year = (int) ($sData['academic_year'] ?? 1);
                $sem = (int) ($sData['semester'] ?? 1);

                $courseId = null;
                if (filled($sData['course_code'] ?? null)) {
                    $c = Course::query()->where('school_id', $school->id)->where('code', $sData['course_code'])->first();
                    $courseId = $c?->id;
                }
                if (! $courseId) {
                    $courseId = $defaultCourse?->id ?? 1;
                }

                // A subject may only be attached to a program of the current
                // school.
                if (! Course::query()->where('school_id', $school->id)->whereKey($courseId)->exists()) {
                    throw new InvalidArgumentException("Program {$courseId} does not belong to this school.");
                }

                $existing = Subject::query()->where('course_id', $courseId)->where('code', $code)->first();
                if ($existing instanceof Subject) {
                    $existing->update([
                        'title' => $title,
                        'units' => $units,
                        'lecture' => $lecture,
                        'laboratory' => $lab,
                        'academic_year' => $year,
                        'semester' => $sem,
                        'course_id' => $courseId,
                    ]);
                    $updated[] = [
                        'id' => $existing->id,
                        'code' => $existing->code,
                        'title' => $existing->title,
                    ];
                } else {
                    $newSub = Subject::query()->create([
                        'school_id' => $school->id,
                        'code' => $code,
                        'title' => $title,
                        'units' => $units,
                        'lecture' => $lecture,
                        'laboratory' => $lab,
                        'academic_year' => $year,
                        'semester' => $sem,
                        'course_id' => $courseId,
                        'classification' => \App\Enums\SubjectEnrolledEnum::INTERNAL->value,
                        'is_credited' => true,
                    ]);
                    $created[] = [
                        'id' => $newSub->id,
                        'code' => $newSub->code,
                        'title' => $newSub->title,
                    ];
                }
            }
        });

        return Response::structured([
            'success' => true,
            'action' => 'batch_upsert',
            'created_count' => count($created),
            'updated_count' => count($updated),
            'subjects' => array_merge($created, $updated),
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
        $school = $this->school();
        $id = $request->get('subject_id');
        if ($id && is_numeric($id)) {
            $found = Subject::query()->where('school_id', $school->id)->find((int) $id);
            if ($found) {
                return $found;
            }
        }

        $code = $request->get('code');
        if ($code) {
            $query = Subject::query()->where('school_id', $school->id);

            // A subject code is unique inside the program it belongs to, so
            // a lookup by code is scoped to the program when the request
            // names one.
            $courseId = $request->get('course_id');
            if ($courseId === null && filled($request->get('course_code'))) {
                $courseId = Course::query()
                    ->where('school_id', $school->id)
                    ->where('code', (string) $request->get('course_code'))
                    ->value('id');

                // A program that does not resolve cannot narrow the lookup.
                // Falling back to a school wide search would let the request
                // reach a subject of a different program.
                if ($courseId === null) {
                    return null;
                }
            }

            if ($courseId !== null) {
                $query->where('course_id', (int) $courseId);
            }

            $matches = $query->where('code', mb_trim((string) $code))->limit(2)->get();

            if ($matches->count() > 1) {
                throw AmbiguousSubjectException::forCode(mb_trim((string) $code));
            }

            return $matches->first();
        }

        return null;
    }
}
