<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SubjectEnrolledEnum;
use App\Http\Requests\StoreCurriculumSubjectRequest;
use App\Http\Requests\UpdateCurriculumProgramRequest;
use App\Http\Requests\UpdateCurriculumSubjectRequest;
use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorCurriculumManagementController extends Controller
{
    public function index(): Response
    {
        $latestPrograms = Course::query()
            ->withCount([
                'subjects',
                'subjects as prerequisites_count' => fn ($query) => $query->whereNotNull('pre_riquisite'),
            ])
            ->withSum('subjects', 'units')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        $versions = $this->buildVersions(
            Course::query()->withCount('subjects')->get()
        );

        return Inertia::render('administrators/curriculum/index', [
            ...$this->userProps(),
            'stats' => [
                'programs' => Course::count(),
                'active_programs' => Course::where('is_active', true)->count(),
                'subjects' => Subject::count(),
                'subjects_with_requisites' => Subject::query()
                    ->whereNotNull('pre_riquisite')
                    ->count(),
                'curriculum_versions' => count($versions),
            ],
            'latest_programs' => $latestPrograms->map(fn (Course $course): array => $this->programPayload($course)),
            'versions' => $versions,
            'filament' => [
                'courses' => [
                    'index_url' => route('filament.admin.resources.courses.index'),
                    'create_url' => route('filament.admin.resources.courses.create'),
                ],
            ],
        ]);
    }

    public function programs(): Response
    {
        $programs = Course::query()
            ->withCount([
                'subjects',
                'subjects as prerequisites_count' => fn ($query) => $query->whereNotNull('pre_riquisite'),
            ])
            ->withSum('subjects', 'units')
            ->orderBy('code')
            ->get();

        $versions = $this->buildVersions($programs);

        return Inertia::render('administrators/curriculum/programs', [
            ...$this->userProps(),
            'stats' => [
                'programs' => $programs->count(),
                'active_programs' => $programs->where('is_active', true)->count(),
                'subjects' => $programs->sum('subjects_count'),
                'subjects_with_requisites' => $programs->sum('prerequisites_count'),
                'curriculum_versions' => count($versions),
            ],
            'programs' => $programs->map(fn (Course $course): array => [
                ...$this->programPayload($course),
                'updated_at' => $course->updated_at?->toDateString(),
            ]),
            'versions' => $versions,
            'filament' => [
                'courses' => [
                    'index_url' => route('filament.admin.resources.courses.index'),
                    'create_url' => route('filament.admin.resources.courses.create'),
                ],
            ],
        ]);
    }

    public function showProgram(Course $course): Response
    {
        $course->load([
            'subjects' => fn ($query) => $query
                ->orderBy('academic_year')
                ->orderBy('semester')
                ->orderBy('code'),
        ]);

        $subjects = $course->subjects;
        $subjectsWithRequisites = $subjects
            ->filter(fn (Subject $subject): bool => $this->normalizeRequisites($subject->pre_riquisite) !== [])
            ->count();

        return Inertia::render('administrators/curriculum/programs/show', [
            ...$this->userProps(),
            'program' => $this->programFormPayload($course),
            'stats' => [
                'subjects' => $subjects->count(),
                'credited_subjects' => $subjects->where('is_credited', true)->count(),
                'academic_years' => $subjects->pluck('academic_year')->filter()->unique()->count(),
                'subjects_with_requisites' => $subjectsWithRequisites,
                'total_units' => $subjects->sum('units'),
            ],
            'subjects' => $subjects->map(fn (Subject $subject): array => $this->subjectFormPayload($subject)),
            'subject_options' => $subjects->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
            ])->values(),
            'classification_options' => collect(SubjectEnrolledEnum::cases())
                ->map(fn (SubjectEnrolledEnum $option): array => [
                    'value' => $option->value,
                    'label' => ucwords(str_replace('_', ' ', $option->value)),
                ])
                ->values(),
        ]);
    }

    public function updateProgram(UpdateCurriculumProgramRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());

        return Redirect::back()->with('success', 'Program updated successfully.');
    }

    public function toggleProgramStatus(Course $course): RedirectResponse
    {
        $course->update([
            'is_active' => ! $course->is_active,
        ]);

        $status = $course->is_active ? 'activated' : 'deactivated';

        return Redirect::back()->with('success', "Program {$status} successfully.");
    }

    public function storeSubject(StoreCurriculumSubjectRequest $request, Course $course): RedirectResponse
    {
        $validated = $this->normalizeSubjectPayload($request->validated());
        $validated['course_id'] = $course->id;

        Subject::create($validated);

        return Redirect::back()->with('success', 'Subject added to this program.');
    }

    public function updateSubject(UpdateCurriculumSubjectRequest $request, Course $course, Subject $subject): RedirectResponse
    {
        $this->ensureSubjectBelongsToCourse($course, $subject);

        $validated = $this->normalizeSubjectPayload($request->validated());

        $subject->update($validated);

        return Redirect::back()->with('success', 'Subject updated successfully.');
    }

    public function destroySubject(Course $course, Subject $subject): RedirectResponse
    {
        $this->ensureSubjectBelongsToCourse($course, $subject);

        $subject->delete();

        return Redirect::back()->with('success', 'Subject removed from this program.');
    }

    private function buildVersions(Collection $programs): array
    {
        return $programs
            ->groupBy(fn (Course $course): string => $course->curriculum_year ?: 'Unassigned')
            ->map(fn (Collection $group, string $curriculumYear): array => [
                'curriculum_year' => $curriculumYear,
                'program_count' => $group->count(),
                'active_program_count' => $group->where('is_active', true)->count(),
                'subject_count' => $group->sum('subjects_count'),
            ])
            ->sortByDesc('curriculum_year', SORT_NATURAL)
            ->values()
            ->all();
    }

    private function programPayload(Course $course): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
            'department' => $course->department?->code,
            'curriculum_year' => $course->curriculum_year,
            'subjects_count' => $course->subjects_count ?? $course->subjects()->count(),
            'total_units' => (int) $course->subjects_sum_units,
            'prerequisites_count' => $this->countPrerequisites($course),
            'is_active' => $course->is_active,
        ];
    }

    private function programFormPayload(Course $course): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
            'description' => $course->description,
            'department' => $course->department?->code,
            'lec_per_unit' => $course->lec_per_unit,
            'remarks' => $course->remarks,
            'curriculum_year' => $course->curriculum_year,
            'miscelaneous' => $course->miscelaneous,
        ];
    }

    private function subjectPayload(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'code' => $subject->code,
            'title' => $subject->title,
            'classification' => $subject->classification?->value,
            'units' => $subject->units,
            'lecture' => $subject->lecture,
            'laboratory' => $subject->laboratory,
            'academic_year' => $subject->academic_year,
            'semester' => $subject->semester,
            'is_credited' => $subject->is_credited,
            'course' => $subject->course ? [
                'id' => $subject->course->id,
                'code' => $subject->course->code,
                'title' => $subject->course->title,
                'curriculum_year' => $subject->course->curriculum_year,
            ] : null,
        ];
    }

    private function subjectFormPayload(Subject $subject): array
    {
        return [
            ...$this->subjectPayload($subject),
            'group' => $subject->group,
            'pre_riquisite' => $this->resolvePrerequisiteIds($subject),
        ];
    }

    private function normalizeRequisites(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $items = array_map(trim(...), explode(',', $value));
        } else {
            $items = [];
        }

        $items = array_filter($items, fn ($item): bool => is_string($item) ? $item !== '' : ! empty($item));
        $items = array_map(
            fn ($item): mixed => is_numeric($item) ? (int) $item : $item,
            $items
        );

        return array_values($items);
    }

    private function resolvePrerequisiteIds(Subject $subject): array
    {
        $items = $this->normalizeRequisites($subject->pre_riquisite);

        if ($items === []) {
            return [];
        }

        $ids = array_values(array_filter($items, is_int(...)));
        $codes = array_values(array_filter($items, is_string(...)));

        if ($codes === [] || $subject->course_id === null) {
            return array_values(array_unique($ids));
        }

        $resolvedIds = Subject::query()
            ->where('course_id', $subject->course_id)
            ->whereIn('code', $codes)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($ids, $resolvedIds)));
    }

    private function countPrerequisites(Course $course): int
    {
        $count = $course->getAttribute('prerequisites_count');

        if (is_numeric($count)) {
            return (int) $count;
        }

        return Subject::query()
            ->where('course_id', $course->id)
            ->whereNotNull('pre_riquisite')
            ->count();
    }

    private function normalizeSubjectPayload(array $validated): array
    {
        $validated['pre_riquisite'] = $this->normalizeRequisites($validated['pre_riquisite'] ?? []);

        return $validated;
    }

    private function ensureSubjectBelongsToCourse(Course $course, Subject $subject): void
    {
        abort_unless($subject->course_id === $course->id, 404);
    }

    private function userProps(): array
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
        ];
    }
}
