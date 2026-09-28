<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required.']);
        }

        $action = mb_strtolower((string) $request['action']);

        if ($action === 'get') {
            if (! $user->hasRole('super_admin') && ! $user->can('View:Subject')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to view curriculum subjects.']);
            }
        } elseif ($action === 'create') {
            if (! $user->hasRole('super_admin') && ! $user->can('Create:Subject')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to create curriculum subjects.']);
            }
        } elseif ($action === 'batch_upsert') {
            if (! $user->hasRole('super_admin') && ! $user->can('Create:Subject') && ! $user->can('Update:Subject')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to create or update curriculum subjects.']);
            }
        } elseif ($action === 'delete') {
            if (! $user->hasRole('super_admin') && ! $user->can('Delete:Subject')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to delete curriculum subjects.']);
            }
        } elseif ($action === 'update') {
            if (! $user->hasRole('super_admin') && ! $user->can('Update:Subject')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to update curriculum subjects.']);
            }
        }

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'batch_upsert' => $this->handleBatchUpsert($request),
            'delete' => $this->handleDelete($request),
            'get' => $this->handleGet($request),
            default => json_encode(['error' => true, 'message' => "Unknown action '{$action}'. Supported: create, update, batch_upsert, delete, get."]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create', 'update', 'batch_upsert', 'delete', 'get'])
                ->required()
                ->description('Operation to execute on curriculum subjects.'),
            'subjects' => $schema->array()
                ->description('List of curriculum subjects for batch_upsert.')
                ->items(
                    $schema->object(fn ($s) => [
                        'code' => $s->string()->required()->description('Subject code (e.g. CS101, MATH101).'),
                        'title' => $s->string()->required()->description('Descriptive title.'),
                        'units' => $s->integer()->required()->description('Credit units (e.g. 3).'),
                        'lecture' => $s->integer()->description('Weekly lecture hours.'),
                        'laboratory' => $s->integer()->description('Weekly lab hours.'),
                        'academic_year' => $s->integer()->description('Year level (1-5).'),
                        'semester' => $s->integer()->description('Semester (1 or 2).'),
                        'course_code' => $s->string()->description('Program code (e.g. BSCS).'),
                        'prerequisites' => $s->array()->items($s->string())->description('Prerequisite subject codes.'),
                    ])
                ),
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

        if ($action === 'batch_upsert') {
            $count = is_array($request['subjects'] ?? null) ? count($request['subjects']) : 0;

            return Approval::required("Batch create or update {$count} curriculum subjects?");
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

    private function handleBatchUpsert(Request $request): string
    {
        $validated = $request->validate([
            'subjects' => 'required|array|min:1',
            'subjects.*.code' => 'required|string|max:50',
            'subjects.*.title' => 'required|string|max:150',
            'subjects.*.units' => 'required|integer|between:0,12',
            'subjects.*.lecture' => 'nullable|integer|between:0,40',
            'subjects.*.laboratory' => 'nullable|integer|between:0,40',
            'subjects.*.academic_year' => 'nullable|integer|between:1,5',
            'subjects.*.semester' => 'nullable|integer|in:1,2',
            'subjects.*.course_id' => 'nullable|integer',
            'subjects.*.course_code' => 'nullable|string|max:50',
            'subjects.*.prerequisites' => 'nullable|array',
        ]);

        $created = [];
        $updated = [];
        $errors = [];

        $defaultCourse = Course::query()->first();

        DB::transaction(function () use ($validated, $defaultCourse, &$created, &$updated, &$errors) {
            foreach ($validated['subjects'] as $idx => $sData) {
                try {
                    $code = mb_strtoupper(mb_trim((string) $sData['code']));
                    $title = mb_trim((string) $sData['title']);
                    $units = (int) $sData['units'];
                    $lecture = (int) ($sData['lecture'] ?? $units);
                    $lab = (int) ($sData['laboratory'] ?? 0);
                    $year = (int) ($sData['academic_year'] ?? 1);
                    $sem = (int) ($sData['semester'] ?? 1);

                    $courseId = $sData['course_id'] ?? null;
                    if (! $courseId && filled($sData['course_code'] ?? null)) {
                        $c = Course::query()->where('code', $sData['course_code'])->first();
                        $courseId = $c?->id;
                    }
                    if (! $courseId) {
                        $courseId = $defaultCourse?->id ?? 1;
                    }

                    $existing = Subject::query()->where('code', $code)->first();
                    if ($existing instanceof Subject) {
                        $existing->update([
                            'title' => $title,
                            'units' => $units,
                            'lecture' => $lecture,
                            'laboratory' => $lab,
                            'academic_year' => $year,
                            'semester' => $sem,
                            'course_id' => $courseId,
                            'pre_riquisite' => $sData['prerequisites'] ?? $existing->pre_riquisite,
                        ]);
                        $updated[] = [
                            'id' => $existing->id,
                            'code' => $existing->code,
                            'title' => $existing->title,
                        ];
                    } else {
                        $newSub = Subject::query()->create([
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
                            'pre_riquisite' => $sData['prerequisites'] ?? [],
                        ]);
                        $created[] = [
                            'id' => $newSub->id,
                            'code' => $newSub->code,
                            'title' => $newSub->title,
                        ];
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Row '.($idx + 1)." ({$sData['code']}): ".$e->getMessage();
                }
            }
        });

        return json_encode([
            'success' => true,
            'action' => 'batch_upsert',
            'created_count' => count($created),
            'updated_count' => count($updated),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
