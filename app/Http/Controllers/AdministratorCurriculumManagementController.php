<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SchoolLevel;
use App\Enums\SubjectEnrolledEnum;
use App\Http\Requests\StoreCurriculumProgramRequest;
use App\Http\Requests\StoreCurriculumSubjectRequest;
use App\Http\Requests\UpdateCurriculumProgramRequest;
use App\Http\Requests\UpdateCurriculumSubjectRequest;
use App\Models\Course;
use App\Models\Department;
use App\Models\School;
use App\Models\ShsTrack;
use App\Models\Subject;
use App\Models\User;
use App\Services\CurriculumCapabilityResolver;
use App\Services\TenantContext;
use App\Support\ChedProgramRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorCurriculumManagementController extends Controller
{
    public function index(CurriculumCapabilityResolver $capabilityResolver, TenantContext $tenantContext): Response
    {
        return $this->programs($capabilityResolver, $tenantContext);
    }

    public function programs(CurriculumCapabilityResolver $capabilityResolver, TenantContext $tenantContext): Response
    {
        $school = $this->currentSchool($tenantContext);
        $programs = Course::query()
            ->with(['department:id,name,code', 'courseType:id,name'])
            ->withCount([
                'subjects',
                'subjects as prerequisites_count' => fn ($query) => $query->whereNotNull('pre_riquisite'),
            ])
            ->withSum('subjects', 'units')
            ->orderBy('code')
            ->get();

        $departments = Department::query()
            ->select(['id', 'name', 'code'])
            ->orderBy('name')
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
                'department_id' => $course->department_id,
                'department_name' => $course->department?->name,
                'course_type_id' => $course->course_type_id,
                'course_type_name' => $course->courseType?->name,
                'updated_at' => $course->updated_at?->toDateString(),
            ]),
            'departments' => $departments->map(fn (Department $dept): array => [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
            ]),
            'versions' => $versions,
            'course_types' => \App\Models\CourseType::query()->select(['id', 'name'])->orderBy('name')->get(),
            'ched_options' => ChedProgramRules::options(),
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
                'school_level' => $school->school_level?->value,
            ] : null,
            'capabilities' => $school ? $capabilityResolver->forSchool($school)->values() : [],
            'catalog_templates' => $this->catalogTemplates(),
            'shs_pathways' => $this->shsPathways(),
        ]);
    }

    public function showProgram(Course $course): Response
    {
        $course->load([
            'subjects' => fn ($query) => $query
                ->orderBy('academic_year')
                ->orderBy('semester')
                ->orderBy('code'),
            'department:id,name,code',
            'courseType:id,name',
        ]);

        $subjects = $course->subjects;
        $subjectsWithRequisites = $subjects
            ->filter(fn (Subject $subject): bool => $this->normalizeRequisites($subject->pre_riquisite) !== [])
            ->count();

        $departments = Department::query()
            ->select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get();

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
            'departments' => $departments->map(fn (Department $dept): array => [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
            ]),
            'course_types' => \App\Models\CourseType::query()->select(['id', 'name'])->orderBy('name')->get(),
            'ched_options' => ChedProgramRules::options(),
        ]);
    }

    public function storeProgram(
        StoreCurriculumProgramRequest $request,
        CurriculumCapabilityResolver $capabilityResolver,
        TenantContext $tenantContext,
    ): RedirectResponse {
        $validated = $request->validated();
        $school = $this->currentSchool($tenantContext);
        $capability = $school ? $capabilityResolver->find($school, $validated['capability_id'] ?? null) : null;

        if (($validated['capability_id'] ?? null) !== null && $capability === null) {
            throw ValidationException::withMessages([
                'capability_id' => 'Choose a curriculum capability that is enabled for the active school.',
            ]);
        }

        if ($capability !== null && $validated['curriculum_kind'] !== $this->kindForSchoolLevel($capability['school_level'])) {
            throw ValidationException::withMessages([
                'curriculum_kind' => 'The selected pathway type does not match the selected school capability.',
            ]);
        }

        unset($validated['capability_id']);
        $validated['is_active'] = true;
        $validated['description'] ??= '';

        if ($school !== null) {
            $validated['school_id'] = $school->id;
        }

        if ($capability !== null) {
            $validated['school_curriculum_capability_id'] = $capability['persisted_id'];
            $validated['curriculum_framework'] = $capability['curriculum_framework'];
            $validated['catalog_reference'] ??= $capability['reference'];
        }

        Course::create($validated);

        return Redirect::back()->with('success', 'Program created successfully.');
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
            'curriculum_kind' => $course->curriculum_kind ?? 'legacy',
            'curriculum_stage' => $course->curriculum_stage,
            'curriculum_framework' => $course->curriculum_framework,
            'qualification_level' => $course->qualification_level,
            'duration_hours' => $course->duration_hours,
            'tesda_program_type' => $course->tesda_program_type,
            'duration_years' => $course->duration_years,
            'internship_hours' => $course->internship_hours,
            'bundled_qualifications' => $course->bundled_qualifications,
            'advanced_topics' => $course->advanced_topics,
        ];
    }

    private function programFormPayload(Course $course): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
            'description' => $course->description,
            'department_id' => $course->department_id,
            'department_name' => $course->department?->name,
            'department_code' => $course->department?->code,
            'course_type_id' => $course->course_type_id,
            'course_type_name' => $course->courseType?->name,
            'lec_per_unit' => $course->lec_per_unit,
            'remarks' => $course->remarks,
            'curriculum_year' => $course->curriculum_year,
            'miscelaneous' => $course->miscelaneous,
            'ched_major' => $course->ched_major,
            'ched_has_thesis' => $course->ched_has_thesis,
            'ched_program_status' => $course->ched_program_status,
            'ched_authority_category' => $course->ched_authority_category,
            'ched_authority_serial' => $course->ched_authority_serial,
            'ched_authority_year' => $course->ched_authority_year,
            'ched_authority_other_program' => $course->ched_authority_other_program,
            'ched_delivery_mode' => $course->ched_delivery_mode,
            'ched_normal_length_years' => $course->ched_normal_length_years,
            'ched_program_credit_units' => $course->ched_program_credit_units,
            'ched_tuition_per_unit' => $course->ched_tuition_per_unit,
            'ched_program_fee' => $course->ched_program_fee,
            'curriculum_kind' => $course->curriculum_kind ?? 'legacy',
            'curriculum_stage' => $course->curriculum_stage,
            'curriculum_framework' => $course->curriculum_framework,
            'catalog_reference' => $course->catalog_reference,
            'duration_hours' => $course->duration_hours,
            'qualification_level' => $course->qualification_level,
            'tesda_program_type' => $course->tesda_program_type,
            'duration_years' => $course->duration_years,
            'internship_hours' => $course->internship_hours,
            'bundled_qualifications' => $course->bundled_qualifications,
            'advanced_topics' => $course->advanced_topics,
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

    private function currentSchool(TenantContext $tenantContext): ?School
    {
        return $tenantContext->getCurrentSchool()
            ?? Auth::user()?->school
            ?? School::query()->orderBy('id')->first();
    }

    private function kindForSchoolLevel(string $schoolLevel): string
    {
        return match (SchoolLevel::from($schoolLevel)) {
            SchoolLevel::HigherEducation => 'program',
            SchoolLevel::TechnicalVocational => 'tesda_qualification',
            SchoolLevel::Elementary, SchoolLevel::JuniorHigh => 'grade_pathway',
            SchoolLevel::SeniorHigh => 'senior_high_pathway',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function catalogTemplates(): array
    {
        return [
            ['framework' => 'ched_psg', 'label' => 'Bachelor of Science in Information Technology', 'kind' => 'program', 'code' => 'BSIT', 'title' => 'Bachelor of Science in Information Technology', 'stage' => null, 'qualification_level' => null, 'duration_hours' => null, 'tesda_program_type' => null, 'duration_years' => null, 'internship_hours' => null, 'bundled_qualifications' => [], 'advanced_topics' => null, 'reference' => 'CHED PSG reference'],
            ['framework' => 'tesda_tr', 'label' => 'Computer Systems Servicing NC II', 'kind' => 'tesda_qualification', 'code' => 'CSS-NC2', 'title' => 'Computer Systems Servicing NC II', 'stage' => null, 'qualification_level' => 'NC II', 'duration_hours' => 280, 'tesda_program_type' => 'national_certificate', 'duration_years' => null, 'internship_hours' => null, 'bundled_qualifications' => [], 'advanced_topics' => null, 'reference' => 'TESDA Training Regulations'],
            ['framework' => 'tesda_tr', 'label' => 'Diploma in Culinary Arts (institutional program)', 'kind' => 'tesda_qualification', 'code' => 'DCA-DIP', 'title' => 'Diploma in Culinary Arts', 'stage' => null, 'qualification_level' => 'Diploma', 'duration_hours' => 1200, 'tesda_program_type' => 'diploma', 'duration_years' => 1.0, 'internship_hours' => 600, 'bundled_qualifications' => ['Cookery NC II', 'Bread & Pastry Production NC II'], 'advanced_topics' => 'Advanced culinary techniques, kitchen operations, menu planning, food safety, and hospitality operations.', 'reference' => 'TESDA Training Regulations / institutional program'],
            ['framework' => 'deped_matatag', 'label' => 'Grade 1 Learning Pathway', 'kind' => 'grade_pathway', 'code' => 'G1', 'title' => 'Grade 1 Learning Pathway', 'stage' => 'Grade 1', 'qualification_level' => null, 'duration_hours' => null, 'tesda_program_type' => null, 'duration_years' => null, 'internship_hours' => null, 'bundled_qualifications' => [], 'advanced_topics' => null, 'reference' => 'DepEd Order No. 010, s. 2024'],
            ['framework' => 'deped_matatag', 'label' => 'Grade 7 Learning Pathway', 'kind' => 'grade_pathway', 'code' => 'G7', 'title' => 'Grade 7 Learning Pathway', 'stage' => 'Grade 7', 'qualification_level' => null, 'duration_hours' => null, 'tesda_program_type' => null, 'duration_years' => null, 'internship_hours' => null, 'bundled_qualifications' => [], 'advanced_topics' => null, 'reference' => 'DepEd Order No. 010, s. 2024'],
            ['framework' => 'deped_shs_k12', 'label' => 'Senior High Pathway', 'kind' => 'senior_high_pathway', 'code' => 'SHS', 'title' => 'Senior High Pathway', 'stage' => 'Grade 11', 'qualification_level' => null, 'duration_hours' => null, 'tesda_program_type' => null, 'duration_years' => null, 'internship_hours' => null, 'bundled_qualifications' => [], 'advanced_topics' => null, 'reference' => 'K to 12 SHS Curriculum'],
        ];
    }

    /** @return array<int, array{id: int, title: string, strands_count: int, subjects_count: int}> */
    private function shsPathways(): array
    {
        return ShsTrack::query()
            ->withCount('strands')
            ->with(['strands' => fn ($query) => $query->withCount('subjects')])
            ->orderBy('track_name')
            ->get()
            ->map(fn (ShsTrack $track): array => [
                'id' => $track->id,
                'title' => $track->track_name,
                'strands_count' => $track->strands_count,
                'subjects_count' => $track->strands->sum('subjects_count'),
            ])
            ->all();
    }
}
