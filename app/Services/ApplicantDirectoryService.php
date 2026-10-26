<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Http\Controllers\Api\V1\ApiPagination;
use App\Models\ApplicantCancellation;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentRequirement;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read model powering the administrator admissions inbox.
 *
 * Applicants are students whose `students.status` is still `applicant`. Every
 * applicant that came through the public enrolment platform also owns a
 * `student_enrollment` row whose `enrollment_requirements` carry the evidence
 * (uploaded documents) they submitted with their application. That workflow
 * state is what this service surfaces, because it is the only signal that tells
 * a registrar whether an application is actually actionable.
 */
final class ApplicantDirectoryService
{
    /** The applicant never submitted an online application (no tracked requirements). */
    public const string STAGE_MANUAL = 'manual';

    /** Required documents are still missing. */
    public const string STAGE_AWAITING_DOCUMENTS = 'awaiting_documents';

    /** Every required document was submitted but none of them were reviewed yet. */
    public const string STAGE_READY_FOR_REVIEW = 'ready_for_review';

    /** Every required document was submitted and the whole checklist was reviewed. */
    public const string STAGE_REVIEWED = 'reviewed';

    /** The application was closed by the registrar. */
    public const string STAGE_CANCELLED = 'cancelled';

    /**
     * Columns selected for the applicant listing.
     *
     * @var list<string>
     */
    private const array LIST_COLUMNS = [
        'students.id',
        'students.status',
        'students.student_id',
        'students.lrn',
        'students.first_name',
        'students.middle_name',
        'students.last_name',
        'students.suffix',
        'students.email',
        'students.phone',
        'students.student_type',
        'students.academic_year',
        'students.scholarship_type',
        'students.created_at',
        'students.deleted_at',
        'c.code as course_code',
        'c.title as course_title',
        'd.code as department_code',
        'd.name as department_name',
        'le.id as enrollment_id',
        'le.status as pipeline_status',
        'le.current_step_key as pipeline_step_key',
        'le.school_year as enrollment_school_year',
        'le.semester as enrollment_semester',
        'le.submission_channel as submission_channel',
        'le.created_at as enrollment_created_at',
        'agg.req_total as requirements_total',
        'agg.req_submitted as requirements_submitted',
        'agg.req_reviewed as requirements_reviewed',
    ];

    /**
     * Normalise the raw request query into a safe, whitelisted filter set.
     *
     * @param  array<string, mixed>  $input
     * @return array{search: string, department: string, course: string, student_type: string, scholarship: string, stage: string, applied_from: string, applied_to: string, include_deleted: bool, per_page: int, sort: string, direction: string, page: int}
     */
    public function normalizeFilters(array $input): array
    {
        $perPage = ApiPagination::perPage($input['per_page'] ?? 10);

        $direction = mb_strtolower((string) ($input['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $sort = (string) ($input['sort'] ?? 'created_at');
        if (! in_array($sort, ['name', 'student_id', 'course', 'department', 'created_at', 'scholarship_type'], true)) {
            $sort = 'created_at';
        }

        $stage = (string) ($input['stage'] ?? '');
        if (! in_array($stage, [...$this->stageKeys(), 'deleted'], true)) {
            $stage = '';
        }
        $studentType = (string) ($input['student_type'] ?? '');
        if ($studentType !== '' && ! in_array($studentType, array_column(StudentType::cases(), 'value'), true)) {
            $studentType = '';
        }

        $scholarship = (string) ($input['scholarship'] ?? '');
        if ($scholarship !== '' && $scholarship !== 'none' && ! in_array($scholarship, array_column(ScholarshipType::cases(), 'value'), true)) {
            $scholarship = '';
        }

        return [
            'search' => mb_substr(mb_trim((string) ($input['search'] ?? '')), 0, 120),
            'department' => mb_substr(mb_trim((string) ($input['department'] ?? '')), 0, 32),
            'course' => mb_substr(mb_trim((string) ($input['course'] ?? '')), 0, 32),
            'student_type' => $studentType,
            'scholarship' => $scholarship,
            'stage' => $stage,
            'applied_from' => $this->normaliseDate((string) ($input['applied_from'] ?? '')),
            'applied_to' => $this->normaliseDate((string) ($input['applied_to'] ?? '')),
            'include_deleted' => filter_var($input['include_deleted'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'per_page' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'page' => max(1, (int) ($input['page'] ?? 1)),
        ];
    }

    /**
     * @return list<string>
     */
    public function stageKeys(): array
    {
        return [
            self::STAGE_MANUAL,
            self::STAGE_AWAITING_DOCUMENTS,
            self::STAGE_READY_FOR_REVIEW,
            self::STAGE_REVIEWED,
            self::STAGE_CANCELLED,
        ];
    }

    /**
     * The stages that describe an application still waiting for a decision.
     *
     * @return list<string>
     */
    public function liveStageKeys(): array
    {
        return [
            self::STAGE_MANUAL,
            self::STAGE_AWAITING_DOCUMENTS,
            self::STAGE_READY_FOR_REVIEW,
            self::STAGE_REVIEWED,
        ];
    }

    /**
     * Stage metadata used by both the tab strip and the stat cards.
     *
     * @return array<string, array{label: string, description: string, tone: string}>
     */
    public function stageMeta(): array
    {
        return [
            self::STAGE_MANUAL => [
                'label' => 'Added manually',
                'description' => 'No online submission on record',
                'tone' => 'text-slate-600 dark:text-slate-300',
            ],
            self::STAGE_AWAITING_DOCUMENTS => [
                'label' => 'Awaiting documents',
                'description' => 'Required uploads are still missing',
                'tone' => 'text-amber-600 dark:text-amber-400',
            ],
            self::STAGE_READY_FOR_REVIEW => [
                'label' => 'Ready for review',
                'description' => 'All documents submitted, none reviewed',
                'tone' => 'text-blue-600 dark:text-blue-400',
            ],
            self::STAGE_REVIEWED => [
                'label' => 'Reviewed',
                'description' => 'Document checklist is complete',
                'tone' => 'text-emerald-600 dark:text-emerald-400',
            ],
            self::STAGE_CANCELLED => [
                'label' => 'Cancelled',
                'description' => 'Application closed by the registrar',
                'tone' => 'text-rose-600 dark:text-rose-400',
            ],
        ];
    }

    /**
     * Paginated applicant listing.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters);

        $this->applySorting($query, (string) $filters['sort'], (string) $filters['direction']);

        $paginator = $query->paginate((int) $filters['per_page'], self::LIST_COLUMNS, 'page', (int) $filters['page']);

        $paginator->setCollection($paginator->getCollection()->map(fn (object $row): array => $this->mapRow($row)));

        return $paginator;
    }

    /**
     * Facet counts for the stage tabs. Every other active filter is applied so
     * the tabs behave like a proper faceted search, but the stage filter itself
     * is intentionally excluded so a tab can report its own size.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function stageCounts(array $filters): array
    {
        $scoped = $filters;
        $scoped['stage'] = '';
        // The stage buckets always describe live applicants so the tab strip
        // never double counts the deleted queue.
        $scoped['include_deleted'] = false;
        // Cancelled applications are counted in their own bucket, so the query
        // has to span both queues.
        $scoped['include_cancelled'] = true;

        $counts = array_fill_keys($this->stageKeys(), 0);

        $rows = $this->baseQuery($scoped)
            ->selectRaw($this->stageExpression().' as applicant_stage')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('applicant_stage')
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->applicant_stage;
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $row->aggregate;
            }
        }

        // "All" counts the applications still waiting for a decision.
        $counts['all'] = (int) array_sum(array_map(
            fn (string $key): int => $counts[$key],
            $this->liveStageKeys(),
        ));
        $counts['deleted'] = $this->deletedCount($filters);

        return $counts;
    }

    /**
     * Aggregate headline numbers for the stat cards.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function stats(array $filters): array
    {
        $scoped = $filters;
        $scoped['stage'] = '';
        $scoped['include_deleted'] = false;

        $row = $this->baseQuery($scoped)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN students.created_at >= ? THEN 1 ELSE 0 END) as recent', [Carbon::now()->subDays(7)])
            ->selectRaw('SUM(CASE WHEN COALESCE(agg.req_total, 0) = 0 THEN 1 ELSE 0 END) as manual')
            ->selectRaw('SUM(CASE WHEN COALESCE(agg.req_total, 0) > 0 AND COALESCE(agg.req_submitted, 0) < COALESCE(agg.req_total, 0) THEN 1 ELSE 0 END) as awaiting_documents')
            ->selectRaw('SUM(CASE WHEN COALESCE(agg.req_total, 0) > 0 AND COALESCE(agg.req_submitted, 0) >= COALESCE(agg.req_total, 0) AND COALESCE(agg.req_reviewed, 0) < COALESCE(agg.req_total, 0) THEN 1 ELSE 0 END) as ready_for_review')
            ->selectRaw('SUM(CASE WHEN COALESCE(agg.req_total, 0) > 0 AND COALESCE(agg.req_reviewed, 0) >= COALESCE(agg.req_total, 0) THEN 1 ELSE 0 END) as reviewed')
            ->selectRaw("SUM(CASE WHEN students.scholarship_type IS NOT NULL AND students.scholarship_type <> '' AND students.scholarship_type <> 'none' THEN 1 ELSE 0 END) as with_scholarship")
            ->selectRaw('SUM(CASE WHEN students.deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted')
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'recent' => (int) ($row?->recent ?? 0),
            'manual' => (int) ($row?->manual ?? 0),
            'awaiting_documents' => (int) ($row?->awaiting_documents ?? 0),
            'ready_for_review' => (int) ($row?->ready_for_review ?? 0),
            'reviewed' => (int) ($row?->reviewed ?? 0),
            'with_scholarship' => (int) ($row?->with_scholarship ?? 0),
            'cancelled' => $this->cancelledCount($filters),
            'deleted' => $this->deletedCount($filters),
        ];
    }

    /**
     * Option lists that feed the filter popover.
     *
     * @return array{departments: list<array{value: string, label: string}>, courses: list<array{value: string, label: string}>, student_types: list<array{value: string, label: string}>, scholarships: list<array{value: string, label: string}>}
     */
    public function filterOptions(): array
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Department $department): array => [
                'value' => (string) $department->code,
                'label' => (string) ($department->name ?: $department->code),
            ])
            ->values()
            ->all();

        $courses = Course::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'title'])
            ->map(fn (Course $course): array => [
                'value' => (string) $course->code,
                'label' => $course->title ? sprintf('%s — %s', $course->code, $course->title) : (string) $course->code,
            ])
            ->values()
            ->all();

        $studentTypes = collect(StudentType::cases())
            ->map(fn (StudentType $type): array => [
                'value' => $type->value,
                'label' => (string) $type->getLabel(),
            ])
            ->values()
            ->all();

        $scholarships = collect(ScholarshipType::cases())
            ->reject(fn (ScholarshipType $type): bool => $type === ScholarshipType::None)
            ->map(fn (ScholarshipType $type): array => [
                'value' => $type->value,
                'label' => (string) $type->getLabel(),
            ])
            ->values()
            ->all();
        $scholarships[] = ['value' => 'none', 'label' => (string) ScholarshipType::None->getLabel()];

        return [
            'departments' => $departments,
            'courses' => $courses,
            'student_types' => $studentTypes,
            'scholarships' => $scholarships,
        ];
    }

    /**
     * Full applicant dossier used by the review drawer.
     *
     * @return array<string, mixed>
     */
    public function detail(Student $student): array
    {
        $student->loadMissing('Course.department');

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        $requirements = $enrollment instanceof StudentEnrollment
            ? $enrollment->requirements()->orderBy('id')->get()
            : collect();

        $requirementRows = $requirements->map(fn (EnrollmentRequirement $requirement): array => [
            'id' => $requirement->id,
            'key' => (string) $requirement->requirement_key,
            'label' => (string) ($requirement->label ?: $requirement->requirement_key),
            'description' => $requirement->description,
            'required' => (bool) $requirement->is_required,
            'status' => (string) $requirement->status,
            'evidence_path' => $requirement->evidence_path,
            'uploaded_at' => $this->evidenceUploadedAt($requirement),
            'verified_at' => $requirement->verified_at?->toDateTimeString(),
            'waived_at' => $requirement->waived_at?->toDateTimeString(),
            'waiver_reason' => $requirement->waiver_reason,
        ])->values()->all();

        return [
            'applicant' => [
                'id' => $student->id,
                'status' => $this->studentStatusValue($student->status),
                'student_id' => $student->student_id,
                'lrn' => $student->lrn,
                'name' => $student->full_name,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'birth_date' => $student->birth_date?->toDateString(),
                'gender' => $student->gender,
                'civil_status' => $student->civil_status,
                'nationality' => $student->nationality,
                'religion' => $student->religion,
                'address' => $student->address,
                'emergency_contact' => $student->emergency_contact,
                'student_type' => $this->studentTypeValue($student->student_type),
                'student_type_label' => $this->studentTypeLabel($student->student_type),
                'academic_year' => $student->academic_year,
                'scholarship_type' => $this->scholarshipValue($student->scholarship_type),
                'scholarship_label' => $this->scholarshipLabel($student->scholarship_type),
                'course_code' => $student->Course?->code,
                'course_title' => $student->Course?->title,
                'department_code' => $student->Course?->department?->code,
                'department_name' => $student->Course?->department?->name,
                'remarks' => $student->remarks,
                'created_at' => $student->created_at?->toDateTimeString(),
                'deleted_at' => $student->deleted_at?->toDateTimeString(),
                'is_trashed' => $student->trashed(),
            ],
            'enrollment' => $enrollment instanceof StudentEnrollment
                ? [
                    'id' => $enrollment->id,
                    'status' => (string) $enrollment->status,
                    'current_step_key' => $enrollment->current_step_key,
                    'school_year' => $enrollment->school_year,
                    'semester' => $enrollment->semester,
                    'submission_channel' => $enrollment->submission_channel,
                    'created_at' => $enrollment->created_at?->toDateTimeString(),
                ]
                : null,
            'requirements' => $requirementRows,
            'documents' => collect($requirementRows)
                ->filter(fn (array $requirement): bool => $requirement['evidence_path'] !== null)
                ->map(fn (array $requirement): array => [
                    'requirement_id' => $requirement['id'],
                    'key' => $requirement['key'],
                    'label' => $requirement['label'],
                    'path' => $requirement['evidence_path'],
                    'uploaded_at' => $requirement['uploaded_at'],
                ])
                ->values()
                ->all(),
            'cancellation' => $this->cancellationSummary($student),
            'cancellation_history' => $this->cancellationHistory($student),
        ];
    }

    /**
     * Every cancellation recorded for an applicant, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function cancellationHistory(Student $student): array
    {
        return ApplicantCancellation::query()
            ->where('student_id', $student->id)
            ->orderByDesc('cancelled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApplicantCancellation $cancellation): array => [
                'id' => $cancellation->id,
                'reason_label' => (string) $cancellation->reason_label,
                'note' => $cancellation->note,
                'cancelled_at' => $cancellation->cancelled_at?->toDateTimeString(),
                'cancelled_by' => $cancellation->cancelledBy?->name,
                'reverted_at' => $cancellation->reverted_at?->toDateTimeString(),
                'revert_reason' => $cancellation->revert_reason,
                'email_sent_at' => $cancellation->email_sent_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    /**
     * The active cancellation for an applicant, if the application was closed.
     *
     * @return array<string, mixed>|null
     */
    private function cancellationSummary(Student $student): ?array
    {
        $cancellation = ApplicantCancellation::query()
            ->where('student_id', $student->id)
            ->active()
            ->orderByDesc('cancelled_at')
            ->orderByDesc('id')
            ->first();

        if (! $cancellation instanceof ApplicantCancellation) {
            return null;
        }

        return [
            'id' => $cancellation->id,
            'reason_code' => (string) $cancellation->reason_code,
            'reason_label' => (string) $cancellation->reason_label,
            'note' => $cancellation->note,
            'cancelled_at' => $cancellation->cancelled_at?->toDateTimeString(),
            'cancelled_by' => $cancellation->cancelledBy?->name,
            'email_subject' => $cancellation->email_subject,
            'email_body' => $cancellation->email_body,
            'email_sent_at' => $cancellation->email_sent_at?->toDateTimeString(),
        ];
    }

    /**
     * Build the shared base query. Every derived table is keyed by student so
     * the join never fans out and counts stay one row per applicant.
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $query = DB::table('students')
            ->leftJoin('courses as c', 'c.id', '=', 'students.course_id')
            ->leftJoin('departments as d', 'd.id', '=', 'c.department_id')
            ->leftJoinSub($this->latestEnrollmentQuery(), 'le', 'le.student_id', '=', 'students.id')
            ->leftJoinSub($this->requirementAggregateQuery(), 'agg', 'agg.student_id', '=', 'students.id')
            ->whereIn('students.status', [StudentStatus::Applicant->value, StudentStatus::Rejected->value]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * The most recent non-deleted enrollment for every student.
     */
    private function latestEnrollmentQuery(): Builder
    {
        return DB::table('student_enrollment as se')
            ->whereNull('se.deleted_at')
            ->whereRaw(
                'se.id = (SELECT MAX(se2.id) FROM student_enrollment se2 WHERE se2.student_id = se.student_id AND se2.deleted_at IS NULL)'
            )
            ->select([
                'se.id as id',
                'se.student_id as student_id',
                'se.status as status',
                'se.current_step_key as current_step_key',
                'se.school_year as school_year',
                'se.semester as semester',
                'se.submission_channel as submission_channel',
                'se.created_at as created_at',
            ]);
    }

    /**
     * Requirement readiness rollup for the latest enrollment of every student.
     */
    private function requirementAggregateQuery(): Builder
    {
        return DB::table('enrollment_requirements as er')
            ->joinSub($this->latestEnrollmentQuery(), 'le', 'le.id', '=', 'er.student_enrollment_id')
            ->where('er.is_required', 1)
            ->groupBy('le.student_id')
            ->select('le.student_id')
            ->selectRaw('COUNT(*) as req_total')
            ->selectRaw("SUM(CASE WHEN er.evidence_path IS NOT NULL AND er.evidence_path <> '' THEN 1 ELSE 0 END) as req_submitted")
            ->selectRaw("SUM(CASE WHEN er.status IN ('verified', 'waived') THEN 1 ELSE 0 END) as req_reviewed");
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $stage = (string) ($filters['stage'] ?? '');

        if ($stage === 'deleted') {
            $query->whereNotNull('students.deleted_at')
                ->where('students.status', StudentStatus::Applicant->value);
        } elseif ($stage === self::STAGE_CANCELLED) {
            $query->where('students.status', StudentStatus::Rejected->value);
        } elseif ($filters['include_cancelled'] ?? false) {
            // The facet counts need both queues so the tab strip can report them together.
            $query->whereIn('students.status', [StudentStatus::Applicant->value, StudentStatus::Rejected->value]);
        } else {
            $query->where('students.status', StudentStatus::Applicant->value);
        }

        if (! ($filters['include_deleted'] ?? false) && $stage !== 'deleted') {
            $query->whereNull('students.deleted_at');
        }

        $search = mb_trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('students.first_name', 'like', $like)
                    ->orWhere('students.middle_name', 'like', $like)
                    ->orWhere('students.last_name', 'like', $like)
                    ->orWhere('students.email', 'like', $like)
                    ->orWhere('students.phone', 'like', $like)
                    ->orWhere('students.student_id', 'like', $like)
                    ->orWhere('students.lrn', 'like', $like)
                    ->orWhereRaw("CONCAT(students.first_name, ' ', students.last_name) LIKE ?", [$like])
                    ->orWhere('c.code', 'like', $like)
                    ->orWhere('c.title', 'like', $like)
                    ->orWhere('d.code', 'like', $like)
                    ->orWhere('d.name', 'like', $like);
            });
        }

        if (($filters['department'] ?? '') !== '') {
            $query->where('d.code', (string) $filters['department']);
        }

        if (($filters['course'] ?? '') !== '') {
            $query->where('c.code', (string) $filters['course']);
        }

        if (($filters['student_type'] ?? '') !== '') {
            $query->where('students.student_type', (string) $filters['student_type']);
        }

        $scholarship = (string) ($filters['scholarship'] ?? '');
        if ($scholarship === 'none') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('students.scholarship_type')
                    ->orWhere('students.scholarship_type', '')
                    ->orWhere('students.scholarship_type', ScholarshipType::None->value);
            });
        } elseif ($scholarship !== '') {
            $query->where('students.scholarship_type', $scholarship);
        }

        if (($filters['applied_from'] ?? '') !== '') {
            $query->whereDate('students.created_at', '>=', (string) $filters['applied_from']);
        }

        if (($filters['applied_to'] ?? '') !== '') {
            $query->whereDate('students.created_at', '<=', (string) $filters['applied_to']);
        }

        $stage = (string) ($filters['stage'] ?? '');

        if ($stage === '' || $stage === 'deleted' || $stage === self::STAGE_CANCELLED) {
            return;
        }

        match ($stage) {
            self::STAGE_MANUAL => $query->whereRaw('COALESCE(agg.req_total, 0) = 0'),
            self::STAGE_AWAITING_DOCUMENTS => $query->whereRaw(
                'COALESCE(agg.req_total, 0) > 0 AND COALESCE(agg.req_submitted, 0) < COALESCE(agg.req_total, 0)'
            ),
            self::STAGE_READY_FOR_REVIEW => $query->whereRaw(
                'COALESCE(agg.req_total, 0) > 0 AND COALESCE(agg.req_submitted, 0) >= COALESCE(agg.req_total, 0) AND COALESCE(agg.req_reviewed, 0) < COALESCE(agg.req_total, 0)'
            ),
            self::STAGE_REVIEWED => $query->whereRaw(
                'COALESCE(agg.req_total, 0) > 0 AND COALESCE(agg.req_reviewed, 0) >= COALESCE(agg.req_total, 0)'
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySorting(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'name' => $query->orderBy('students.last_name', $direction)->orderBy('students.first_name', $direction),
            'student_id' => $query->orderBy('students.student_id', $direction),
            'course' => $query->orderBy('c.code', $direction),
            'department' => $query->orderBy('d.code', $direction),
            'scholarship_type' => $query->orderBy('students.scholarship_type', $direction),
            default => $query->orderBy('students.created_at', $direction)->orderBy('students.id', $direction),
        };
    }

    /**
     * Count the applications that were closed by the registrar.
     *
     * @param  array<string, mixed>  $filters
     */
    private function cancelledCount(array $filters): int
    {
        $scoped = $filters;
        $scoped['stage'] = self::STAGE_CANCELLED;
        $scoped['include_deleted'] = false;

        return (int) $this->baseQuery($scoped)->count();
    }

    /**
     * Count soft-deleted applicants that match the active filters.
     *
     * @param  array<string, mixed>  $filters
     */
    private function deletedCount(array $filters): int
    {
        $scoped = $filters;
        $scoped['include_deleted'] = true;
        $scoped['stage'] = '';

        $query = $this->baseQuery($scoped)->whereNotNull('students.deleted_at');

        return (int) $query->count();
    }

    /**
     * SQL expression that buckets an applicant into a pipeline stage.
     */
    private function stageExpression(): string
    {
        return 'CASE'
            ." WHEN students.status = '".StudentStatus::Rejected->value."' THEN '".self::STAGE_CANCELLED."'"
            ." WHEN COALESCE(agg.req_total, 0) = 0 THEN '".self::STAGE_MANUAL."'"
            ." WHEN COALESCE(agg.req_submitted, 0) < COALESCE(agg.req_total, 0) THEN '".self::STAGE_AWAITING_DOCUMENTS."'"
            ." WHEN COALESCE(agg.req_reviewed, 0) >= COALESCE(agg.req_total, 0) THEN '".self::STAGE_REVIEWED."'"
            ." ELSE '".self::STAGE_READY_FOR_REVIEW."'"
            .' END';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRow(object $row): array
    {
        $total = (int) ($row->requirements_total ?? 0);
        $submitted = (int) ($row->requirements_submitted ?? 0);
        $reviewed = (int) ($row->requirements_reviewed ?? 0);

        $middle = mb_trim((string) $row->middle_name);
        $name = $middle !== ''
            ? sprintf('%s, %s %s', (string) $row->last_name, (string) $row->first_name, $middle)
            : sprintf('%s, %s', (string) $row->last_name, (string) $row->first_name);

        $createdAt = $row->created_at !== null ? Carbon::parse((string) $row->created_at) : null;

        return [
            'id' => (int) $row->id,
            'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
            'lrn' => $row->lrn !== null ? (string) $row->lrn : null,
            'name' => $name,
            'first_name' => (string) $row->first_name,
            'last_name' => (string) $row->last_name,
            'email' => $row->email !== null ? (string) $row->email : null,
            'phone' => $row->phone !== null ? (string) $row->phone : null,
            'student_type' => $this->studentTypeValue($row->student_type),
            'student_type_label' => $this->studentTypeLabel($row->student_type),
            'academic_year' => $row->academic_year !== null ? (int) $row->academic_year : null,
            'scholarship_type' => $this->scholarshipValue($row->scholarship_type),
            'scholarship_label' => $this->scholarshipLabel($row->scholarship_type),
            'course' => $row->course_code !== null ? (string) $row->course_code : null,
            'course_title' => $row->course_title !== null ? (string) $row->course_title : null,
            'department' => $row->department_code !== null ? (string) $row->department_code : null,
            'department_name' => $row->department_name !== null ? (string) $row->department_name : null,
            'created_at' => $createdAt?->toDateTimeString(),
            'applied_ago' => $createdAt?->diffForHumans(['syntax' => 'short', 'parts' => 1, 'short' => true]),
            'deleted_at' => $row->deleted_at !== null ? (string) $row->deleted_at : null,
            'is_trashed' => $row->deleted_at !== null,
            'enrollment_id' => $row->enrollment_id !== null ? (int) $row->enrollment_id : null,
            'pipeline_status' => $row->pipeline_status !== null ? (string) $row->pipeline_status : null,
            'pipeline_step_key' => $row->pipeline_step_key !== null ? (string) $row->pipeline_step_key : null,
            'submission_channel' => $row->submission_channel !== null ? (string) $row->submission_channel : null,
            'requirements_total' => $total,
            'requirements_submitted' => $submitted,
            'requirements_reviewed' => $reviewed,
            'requirements_missing' => max(0, $total - $submitted),
            'stage' => (string) $row->status === StudentStatus::Rejected->value
                ? self::STAGE_CANCELLED
                : $this->resolveStage($total, $submitted, $reviewed),
        ];
    }

    /**
     * @param  mixed  $value
     */
    private function resolveStage(int $total, int $submitted, int $reviewed): string
    {
        if ($total <= 0) {
            return self::STAGE_MANUAL;
        }

        if ($submitted < $total) {
            return self::STAGE_AWAITING_DOCUMENTS;
        }

        if ($reviewed >= $total) {
            return self::STAGE_REVIEWED;
        }

        return self::STAGE_READY_FOR_REVIEW;
    }

    private function studentStatusValue(mixed $value): ?string
    {
        if ($value instanceof StudentStatus) {
            return $value->value;
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function studentTypeValue(mixed $value): ?string
    {
        if ($value instanceof StudentType) {
            return $value->value;
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function studentTypeLabel(mixed $value): ?string
    {
        $raw = $this->studentTypeValue($value);

        if ($raw === null) {
            return null;
        }

        return StudentType::tryFrom($raw)?->getLabel() ?? $raw;
    }

    private function scholarshipValue(mixed $value): ?string
    {
        if ($value instanceof ScholarshipType) {
            return $value->value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function scholarshipLabel(mixed $value): ?string
    {
        if ($value instanceof ScholarshipType) {
            return (string) $value->getLabel();
        }

        $raw = $this->scholarshipValue($value);

        if ($raw === null) {
            return null;
        }

        return ScholarshipType::tryFrom($raw)?->getLabel() ?? mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
    }

    private function evidenceUploadedAt(EnrollmentRequirement $requirement): ?string
    {
        $evidence = $requirement->evidence;

        if (is_array($evidence) && isset($evidence['uploaded_at']) && is_string($evidence['uploaded_at'])) {
            return $evidence['uploaded_at'];
        }

        return null;
    }

    private function normaliseDate(string $value): string
    {
        $value = mb_trim($value);

        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return '';
        }
    }
}
