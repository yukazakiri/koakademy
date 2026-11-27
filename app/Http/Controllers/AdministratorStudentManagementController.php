<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttritionCategory;
use App\Enums\EmploymentStatus;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Enums\SubjectEnrolledEnum;
use App\Enums\UserRole;
use App\Http\Requests\Administrators\BulkDeleteStudentRequest;
use App\Http\Requests\Administrators\BulkEmailStudentsRequest;
use App\Http\Requests\Administrators\BulkForceDeleteStudentRequest;
use App\Http\Requests\Administrators\BulkUpdateStudentClearanceRequest;
use App\Http\Requests\Administrators\BulkUpdateStudentStatusRequest;
use App\Jobs\GenerateStudentSoaPdfJob;
use App\Mail\StudentBulkMessage;
use App\Models\Account;
use App\Models\ClassAttendanceRecord;
use App\Models\ClassEnrollment;
use App\Models\Course;
use App\Models\Department;
use App\Models\DocumentLocation;
use App\Models\Resource;
use App\Models\ShsStrand;
use App\Models\ShsStudent;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentDocumentRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentHold;
use App\Models\StudentStatusRecord;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Notifications\PortalAccountWelcomeNotification;
use App\Notifications\StatementOfAccountAdjustedNotification;
use App\Services\BulkActivityLogger;
use App\Services\CurriculumCapabilityResolver;
use App\Services\GeneralSettingsService;
use App\Services\GradeEvaluationService;
use App\Services\GradeReleaseService;
use App\Services\GradingSystemService;
use App\Services\IdentifierGenerator;
use App\Services\StudentConfigurationService;
use App\Services\StudentDocumentService;
use App\Services\StudentIdUpdateService;
use App\Services\StudentOrganizationAssignmentService;
use App\Services\StudentProfileCompletionService;
use App\Services\StudentSchoolOptionService;
use App\Services\TenantContext;
use App\Services\UserSystemRoleService;
use App\Settings\SiteSettings;
use App\Support\BulkChunk;
use Closure;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as RulesEnum;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

final class AdministratorStudentManagementController extends Controller
{
    /**
     * Memoized per-request column listings, keyed by table name.
     *
     * Schema::hasColumn() runs a live catalog query on every call — and internally
     * calls getColumnListing() twice. Checking every attribute of the four related
     * student tables that way cost ~120 information_schema/pg_catalog queries per
     * save, which dominated the response time of a single field edit.
     *
     * @var array<string, array<int, string>>
     */
    private array $columnListings = [];

    public function index(Request $request): Response
    {
        $this->authorizeStudentAccess(['ViewAny:Student', 'View:Student']);

        $generalSettingsService = app(GeneralSettingsService::class);
        $configService = app(StudentConfigurationService::class);
        $documentService = app(StudentDocumentService::class);
        $schoolId = app(TenantContext::class)->getCurrentSchoolId();
        $studentConfiguration = $configService->get($schoolId);
        $schoolProfile = $configService->schoolProfile($schoolId);
        $configuredYearLevels = $configService->filterYearLevels($schoolId);
        $validYearLevelValues = array_column($configuredYearLevels, 'numeric');

        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();
        $systemSettings = $generalSettingsService->getGlobalSettingsModel();
        $currentPeriod = [
            'academic_year' => $currentSchoolYear,
            'semester' => $currentSemester,
        ];
        $gradeClearancePeriod = [
            'school_year' => $systemSettings?->getSchoolYearString() ?? $currentSchoolYear,
            'semester' => (int) ($systemSettings?->semester ?? $currentSemester),
        ];

        $search = $request->input('search');
        $type = $request->input('type');
        $status = $request->input('status');
        $courseId = $request->integer('course_id');
        $courseId = $courseId > 0 ? $courseId : null;
        $departmentId = $request->integer('department_id');
        $departmentId = $departmentId > 0 ? $departmentId : null;
        $yearLevel = $request->integer('year_level');
        $yearLevel = in_array($yearLevel, $validYearLevelValues, true) ? $yearLevel : null;
        $currentEnrollment = $request->string('current_enrollment')->toString();
        $currentEnrollment = in_array($currentEnrollment, ['enrolled', 'not_enrolled'], true) ? $currentEnrollment : null;
        $scholarshipType = $request->input('scholarship_type');
        $employmentStatus = $request->input('employment_status');
        $isIndigenousPerson = $request->input('is_indigenous_person');
        $regionOfOrigin = $request->input('region_of_origin');
        $previousSemesterCleared = $request->input('previous_semester_cleared');
        $triage = $request->string('triage', 'all')->toString();
        if (! in_array($triage, ['all', 'needs_doc_review', 'missing_docs', 'active_holds', 'no_portal', 'incomplete_profile', 'trashed'], true)) {
            $triage = 'all';
        }
        $trashedFilter = $request->string('trashed', $triage === 'trashed' ? 'trashed' : 'active')->toString();
        if (! in_array($trashedFilter, ['active', 'trashed', 'all'], true)) {
            $trashedFilter = 'active';
        }
        if ($triage === 'trashed') {
            $trashedFilter = 'trashed';
        }

        $sort = $request->string('sort', 'created_at')->toString();
        $allowedSorts = ['name', 'status', 'student_id', 'type', 'course', 'academic_year', 'created_at', 'age', 'gender'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $direction = $request->string('direction', 'desc')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $perPage = $request->input('per_page', 20);
        $perPage = in_array((int) $perPage, [10, 20, 50, 100], true) ? (int) $perPage : 20;

        $hasDocRecordsTable = Schema::hasTable('student_document_records');
        $hasHoldsTable = Schema::hasTable('student_holds');

        $studentsQuery = Student::query()
            ->select([
                'students.id',
                'students.school_id',
                'students.student_id',
                'students.lrn',
                'students.student_type',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'students.email',
                'students.phone',
                'students.user_id',
                'students.course_id',
                'students.shs_strand_id',
                'students.academic_year',
                'students.document_location_id',
                'students.is_indigenous_person',
                'students.region_of_origin',
                'students.scholarship_type',
                'students.employment_status',
                'students.created_at',
                'students.deleted_at',
            ])
            ->with(array_filter([
                'Course:id,department_id,code,title',
                'shsStrand:id,strand_name',
                'DocumentLocation',
                'user:id,name,email,email_verified_at,suspended_at',
                $hasDocRecordsTable ? 'documentRecords' : null,
                'gradeReleaseClearances' => function ($query) use ($gradeClearancePeriod): void {
                    $query->select(['id', 'student_id', 'school_year', 'semester', 'is_cleared'])
                        ->where('school_year', $gradeClearancePeriod['school_year'])
                        ->where('semester', $gradeClearancePeriod['semester']);
                },
                'statusRecords' => function ($query) use ($currentPeriod): void {
                    $query->select(['student_id', 'academic_year', 'semester', 'status'])
                        ->where('academic_year', $currentPeriod['academic_year'])
                        ->where('semester', $currentPeriod['semester']);
                },
            ]));

        if ($hasHoldsTable) {
            $studentsQuery->withCount([
                'holds as active_holds_count' => fn (Builder $query) => $query->active(),
            ]);
        }

        if ($trashedFilter === 'trashed') {
            $studentsQuery->onlyTrashed();
        } elseif ($trashedFilter === 'all') {
            $studentsQuery->withTrashed();
        }

        if (is_string($search) && mb_trim($search) !== '') {
            $searchTerm = mb_trim($search);
            $textCast = match ($studentsQuery->getConnection()->getDriverName()) {
                'mysql', 'mariadb' => 'CHAR',
                default => 'TEXT',
            };

            $studentsQuery->where(function (Builder $query) use ($searchTerm, $currentPeriod, $textCast): void {
                $like = "%{$searchTerm}%";
                $query->whereRaw("LOWER(CAST(students.student_id AS {$textCast})) LIKE LOWER(?)", [$like])
                    ->orWhere('students.lrn', 'like', $like)
                    ->orWhere('students.email', 'like', $like)
                    ->orWhere('students.first_name', 'like', $like)
                    ->orWhere('students.middle_name', 'like', $like)
                    ->orWhere('students.last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(students.first_name, ' ', students.last_name) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT(students.last_name, ', ', students.first_name) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT(students.last_name, ' ', students.first_name) LIKE ?", [$like])
                    ->orWhere('students.scholarship_type', 'like', $like)
                    ->orWhere('students.employment_status', 'like', $like)
                    ->orWhere('students.region_of_origin', 'like', $like)
                    ->orWhereHas('Course', function (Builder $courseQuery) use ($like): void {
                        $courseQuery->where('code', 'like', $like)
                            ->orWhere('title', 'like', $like);
                    })
                    ->orWhereHas('statusRecords', function (Builder $statusQuery) use ($like, $currentPeriod): void {
                        $statusQuery->where('academic_year', $currentPeriod['academic_year'])
                            ->where('semester', $currentPeriod['semester'])
                            ->where('status', 'like', $like);
                    });
            });
        }

        if ($triage === 'needs_doc_review') {
            $this->applyNeedsDocReviewFilter($studentsQuery, $documentService, $studentConfiguration, $schoolId, $hasDocRecordsTable);
        } elseif ($triage === 'missing_docs') {
            $this->applyMissingRequiredDocsFilter($studentsQuery, $documentService, $studentConfiguration, $schoolId, $hasDocRecordsTable);
        } elseif ($triage === 'active_holds' && $hasHoldsTable) {
            $studentsQuery->whereHas('holds', fn (Builder $q) => $q->active());
        } elseif ($triage === 'no_portal') {
            $studentsQuery->whereNull('students.user_id');
        } elseif ($triage === 'incomplete_profile') {
            $studentsQuery->where(function (Builder $q): void {
                $q->whereNull('students.email')->orWhere('students.email', '')
                    ->orWhereNull('students.phone')->orWhere('students.phone', '')
                    ->orWhere(function (Builder $placement): void {
                        $placement->whereNull('students.course_id')->whereNull('students.shs_strand_id');
                    });
            });
        }

        if (is_string($type) && mb_trim($type) !== '') {
            $studentsQuery->where('students.student_type', $type);
        }

        if (is_string($status) && mb_trim($status) !== '') {
            $studentsQuery->whereHas('statusRecords', function (Builder $statusQuery) use ($status, $currentPeriod): void {
                $statusQuery->where('academic_year', $currentPeriod['academic_year'])
                    ->where('semester', $currentPeriod['semester'])
                    ->where('status', $status);
            });
        }

        if ($courseId !== null) {
            $studentsQuery->where('students.course_id', $courseId);
        }

        if ($departmentId !== null) {
            $studentsQuery->whereHas('Course', function (Builder $courseQuery) use ($departmentId): void {
                $courseQuery->where('department_id', $departmentId);
            });
        }

        if ($yearLevel !== null) {
            $studentsQuery->where('students.academic_year', $yearLevel);
        }

        if ($currentEnrollment === 'enrolled') {
            $studentsQuery->whereHas('statusRecords', function (Builder $statusQuery) use ($currentPeriod): void {
                $statusQuery->where('academic_year', $currentPeriod['academic_year'])
                    ->where('semester', $currentPeriod['semester'])
                    ->where('status', StudentStatus::Enrolled->value);
            });
        } elseif ($currentEnrollment === 'not_enrolled') {
            $studentsQuery->whereDoesntHave('statusRecords', function (Builder $statusQuery) use ($currentPeriod): void {
                $statusQuery->where('academic_year', $currentPeriod['academic_year'])
                    ->where('semester', $currentPeriod['semester'])
                    ->where('status', StudentStatus::Enrolled->value);
            });
        }

        if (is_string($scholarshipType) && mb_trim($scholarshipType) !== '') {
            $studentsQuery->where('students.scholarship_type', $scholarshipType);
        }

        if (is_string($employmentStatus) && mb_trim($employmentStatus) !== '') {
            $studentsQuery->where('students.employment_status', $employmentStatus);
        }

        if ($isIndigenousPerson === 'yes') {
            $studentsQuery->where('students.is_indigenous_person', true);
        } elseif ($isIndigenousPerson === 'no') {
            $studentsQuery->where('students.is_indigenous_person', false);
        }

        if (is_string($regionOfOrigin) && mb_trim($regionOfOrigin) !== '') {
            $studentsQuery->where('students.region_of_origin', $regionOfOrigin);
        }

        if (in_array($previousSemesterCleared, ['true', 'false'], true)) {
            $studentsQuery->whereHas('gradeReleaseClearances', function (Builder $clearanceQuery) use ($gradeClearancePeriod, $previousSemesterCleared): void {
                $clearanceQuery->where('school_year', $gradeClearancePeriod['school_year'])
                    ->where('semester', $gradeClearancePeriod['semester'])
                    ->where('is_cleared', $previousSemesterCleared === 'true');
            });
        }

        if ($sort === 'name') {
            $studentsQuery->orderBy('students.last_name', $direction)
                ->orderBy('students.first_name', $direction);
        } elseif ($sort === 'status') {
            $studentsQuery->orderBy(
                StudentStatusRecord::query()
                    ->select('status')
                    ->whereColumn('student_id', 'students.id')
                    ->where('academic_year', $currentPeriod['academic_year'])
                    ->where('semester', $currentPeriod['semester'])
                    ->limit(1),
                $direction
            );
        } elseif ($sort === 'student_id') {
            $studentsQuery->orderBy('students.student_id', $direction);
        } elseif ($sort === 'type') {
            $studentsQuery->orderBy('students.student_type', $direction);
        } elseif ($sort === 'course') {
            $studentsQuery->orderBy(
                Course::select('code')
                    ->whereColumn('courses.id', 'students.course_id')
                    ->limit(1),
                $direction
            );
        } elseif (in_array($sort, ['academic_year', 'created_at', 'age', 'gender'], true)) {
            $studentsQuery->orderBy("students.{$sort}", $direction);
        }

        $studentsQuery->orderBy('students.id', $direction);

        $studentTransform = function (Student $student) use ($documentService, $hasDocRecordsTable): array {
            $studentType = $student->student_type;
            $currentStatusRecord = $student->statusRecords->first();
            $currentStatus = $currentStatusRecord?->status;
            $scholarshipType = $student->scholarship_type;
            $employmentStatus = $student->employment_status;

            // Get current semester clearance status
            $currentClearanceStatus = 'no_record';
            $currentClearance = $student->gradeReleaseClearances->first();
            if ($currentClearance) {
                $currentClearanceStatus = $currentClearance->is_cleared ? 'cleared' : 'not_cleared';
            }

            $docsSummary = $documentService->rowSummaryFor(
                $student,
                $hasDocRecordsTable && $student->relationLoaded('documentRecords') ? $student->documentRecords : collect(),
            );

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'lrn' => $student->lrn,
                'name' => $student->full_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'user_id' => $student->user_id,
                'portal_account' => $student->user ? [
                    'id' => $student->user->id,
                    'email' => $student->user->email,
                    'email_verified' => $student->user->email_verified_at !== null,
                    'suspended' => $student->user->suspended_at !== null,
                ] : null,
                'course_id' => $student->course_id,
                'department_id' => $student->Course?->department_id,
                'course' => $student->Course?->code ?? $student->shsStrand?->strand_name,
                'course_title' => $student->Course?->title ?? ($student->shsStrand ? 'Senior High Strand' : null),
                'shs_strand_id' => $student->shs_strand_id,
                'year_level' => $student->academic_year,
                'academic_year' => $student->formatted_academic_year,
                'type' => $studentType instanceof StudentType ? $studentType->value : (is_string($studentType) ? $studentType : null),
                'status' => $currentStatus instanceof StudentStatus ? $currentStatus->value : (is_string($currentStatus) ? $currentStatus : null),
                'scholarship_type_value' => $scholarshipType instanceof ScholarshipType ? $scholarshipType->value : $scholarshipType,
                'scholarship_type' => $scholarshipType instanceof ScholarshipType ? $scholarshipType->getLabel() : ($scholarshipType ?? 'None'),
                'employment_status_value' => $employmentStatus instanceof EmploymentStatus ? $employmentStatus->value : $employmentStatus,
                'employment_status' => $employmentStatus instanceof EmploymentStatus ? $employmentStatus->getLabel() : ($employmentStatus ?? 'N/A'),
                'is_indigenous_person' => $student->is_indigenous_person,
                'region_of_origin' => $student->region_of_origin,
                'previous_sem_clearance' => $currentClearanceStatus,
                'active_holds_count' => (int) ($student->active_holds_count ?? 0),
                'docs_summary' => [
                    'required_total' => $docsSummary['required_total'],
                    'compliant_count' => $docsSummary['compliant_count'],
                    'pending_review_count' => $docsSummary['pending_review_count'],
                    'rejected_count' => $docsSummary['rejected_count'],
                    'missing_count' => $docsSummary['missing_count'],
                    'compliance_percent' => $docsSummary['compliance_percent'],
                    'is_complete' => $docsSummary['is_complete'],
                ],
                'avatar_url' => $student->picture1x1 !== '' ? $student->picture1x1 : null,
                'created_at' => format_timestamp($student->created_at),
                'deleted_at' => $student->deleted_at ? format_timestamp($student->deleted_at) : null,
                'filament' => [
                    'view_url' => route('filament.admin.resources.students.view', $student),
                    'edit_url' => route('filament.admin.resources.students.edit', $student),
                ],
            ];
        };

        $studentsPayloadResolver = function () use ($studentsQuery, $perPage, $studentTransform): LengthAwarePaginator {
            /** @var LengthAwarePaginator $paginated */
            $paginated = $studentsQuery->paginate($perPage)->withQueryString();

            return $paginated->through($studentTransform);
        };

        $hasActiveFilters = (is_string($search) && mb_trim($search) !== '')
            || (is_string($type) && mb_trim($type) !== '')
            || (is_string($status) && mb_trim($status) !== '')
            || $courseId !== null
            || $departmentId !== null
            || $yearLevel !== null
            || $currentEnrollment !== null
            || (is_string($scholarshipType) && mb_trim($scholarshipType) !== '')
            || (is_string($employmentStatus) && mb_trim($employmentStatus) !== '')
            || $isIndigenousPerson !== null
            || (is_string($regionOfOrigin) && mb_trim($regionOfOrigin) !== '')
            || $previousSemesterCleared !== null
            || $triage !== 'all'
            || $trashedFilter !== 'active';

        $isPartialReload = $request->header('X-Inertia-Partial-Data') !== null;
        $isFullPageFilterOrPagination = ($request->has('page') || $request->has('search') || $hasActiveFilters) && ! $isPartialReload;

        if ($request->header('X-Inertia') !== null && ! $isFullPageFilterOrPagination) {
            $students = Inertia::defer($studentsPayloadResolver, 'student-directory');
        } else {
            $students = $studentsPayloadResolver();
            $globalStudentTotal = $hasActiveFilters ? Student::query()->count() : $students->total();
            $request->attributes->set('admin_students_global_total', $globalStudentTotal);
        }

        $optionsResolver = function () use ($studentConfiguration, $schoolProfile, $configuredYearLevels, $schoolId): array {
            $enabledTypes = $studentConfiguration['enabled_types'] ?? array_column(StudentType::cases(), 'value');
            $types = collect(StudentType::cases())
                ->filter(fn (StudentType $studentType): bool => in_array($studentType->value, $enabledTypes, true))
                ->map(fn (StudentType $studentType): array => [
                    'value' => $studentType->value,
                    'label' => $studentConfiguration['type_labels'][$studentType->value] ?? $studentType->getLabel() ?? $studentType->value,
                ])
                ->values()
                ->all();

            $statuses = collect(StudentStatus::cases())
                ->map(fn (StudentStatus $studentStatus): array => [
                    'value' => $studentStatus->value,
                    'label' => $studentStatus->getLabel() ?? $studentStatus->value,
                ])
                ->values()
                ->all();

            $scholarshipTypes = collect(ScholarshipType::cases())
                ->map(fn (ScholarshipType $type): array => [
                    'value' => $type->value,
                    'label' => $type->getLabel(),
                ])
                ->values()
                ->all();

            $employmentStatuses = collect(EmploymentStatus::cases())
                ->map(fn (EmploymentStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->getLabel(),
                ])
                ->values()
                ->all();

            $courseModels = Course::query()
                ->orderBy('code')
                ->get(['id', 'code', 'title']);

            $courses = $courseModels
                ->map(fn (Course $course): array => [
                    'value' => (string) $course->id,
                    'label' => "{$course->code} - {$course->title}",
                ])
                ->values()
                ->all();

            $quickCreateCourses = $courseModels
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ])
                ->values()
                ->all();

            $shsStrands = ShsStrand::query()
                ->orderBy('strand_name')
                ->get(['id', 'strand_name'])
                ->map(fn (ShsStrand $strand): array => [
                    'id' => $strand->id,
                    'value' => (string) $strand->id,
                    'label' => $strand->strand_name,
                ])
                ->values()
                ->all();

            $departments = Department::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Department $department): array => [
                    'value' => (string) $department->id,
                    'label' => "{$department->code} - {$department->name}",
                ])
                ->values()
                ->all();

            $yearLevels = collect($configuredYearLevels)
                ->map(fn (array $item): array => [
                    'value' => $item['value'],
                    'label' => $item['label'],
                ])
                ->values()
                ->all();

            return [
                'types' => $types,
                'statuses' => $statuses,
                'courses' => $courses,
                'quick_create_courses' => $quickCreateCourses,
                'shs_strands' => $shsStrands,
                'departments' => $departments,
                'year_levels' => $yearLevels,
                'scholarship_types' => $scholarshipTypes,
                'employment_statuses' => $employmentStatuses,
                'student_configuration' => $studentConfiguration,
                'identifier_policy' => app(IdentifierGenerator::class)->studentPolicy(schoolId: $schoolId),
                'school_profile' => $schoolProfile,
            ];
        };

        $statsResolver = function () use ($request, $currentSchoolYear, $currentSemester): array {
            if (! $request->attributes->has('admin_students_global_total')) {
                $request->attributes->set('admin_students_global_total', Student::query()->count());
            }

            $statusCounts = StudentStatusRecord::query()
                ->where('academic_year', $currentSchoolYear)
                ->where('semester', $currentSemester)
                ->selectRaw('
                    COUNT(CASE WHEN status = ? THEN 1 END) as enrolled_count,
                    COUNT(CASE WHEN status = ? THEN 1 END) as applicant_count,
                    COUNT(CASE WHEN status = ? THEN 1 END) as graduated_count
                ', [
                    StudentStatus::Enrolled->value,
                    StudentStatus::Applicant->value,
                    StudentStatus::Graduated->value,
                ])
                ->first();

            return [
                'total_students' => (int) $request->attributes->get('admin_students_global_total'),
                'total_enrolled' => (int) ($statusCounts?->enrolled_count ?? 0),
                'total_applicants' => (int) ($statusCounts?->applicant_count ?? 0),
                'total_graduated' => (int) ($statusCounts?->graduated_count ?? 0),
            ];
        };

        $triageCountsResolver = function () use ($hasDocRecordsTable, $hasHoldsTable, $documentService, $studentConfiguration, $schoolId): array {
            $totalActive = Student::query()->count();
            $trashedCount = Student::onlyTrashed()->count();
            $noPortalCount = Student::query()->whereNull('user_id')->count();
            $incompleteCount = Student::query()->where(function (Builder $q): void {
                $q->whereNull('email')->orWhere('email', '')
                    ->orWhereNull('phone')->orWhere('phone', '')
                    ->orWhere(function (Builder $p): void {
                        $p->whereNull('course_id')->whereNull('shs_strand_id');
                    });
            })->count();

            $needsDocReviewQuery = Student::query();
            $this->applyNeedsDocReviewFilter($needsDocReviewQuery, $documentService, $studentConfiguration, $schoolId, $hasDocRecordsTable);
            $needsDocReviewCount = $needsDocReviewQuery->count();

            $missingDocsQuery = Student::query();
            $this->applyMissingRequiredDocsFilter($missingDocsQuery, $documentService, $studentConfiguration, $schoolId, $hasDocRecordsTable);
            $missingDocsCount = $missingDocsQuery->count();

            $activeHoldsCount = $hasHoldsTable
                ? Student::query()->whereHas('holds', fn (Builder $q) => $q->active())->count()
                : 0;

            return [
                'all' => $totalActive,
                'needs_doc_review' => $needsDocReviewCount,
                'missing_docs' => $missingDocsCount,
                'active_holds' => $activeHoldsCount,
                'no_portal' => $noPortalCount,
                'incomplete_profile' => $incompleteCount,
                'trashed' => $trashedCount,
            ];
        };

        return Inertia::render('administrators/students/index', [
            'user' => fn (): array => $this->getUserProps(),
            'filament' => [
                'students' => [
                    'index_url' => route('filament.admin.resources.students.index'),
                    'create_url' => route('filament.admin.resources.students.create'),
                ],
            ],
            'students' => $students,
            'stats' => $statsResolver,
            'triage_counts' => Inertia::defer($triageCountsResolver, 'student-triage'),
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'type' => is_string($type) ? $type : null,
                'status' => is_string($status) ? $status : null,
                'course_id' => $courseId,
                'department_id' => $departmentId,
                'year_level' => $yearLevel,
                'current_enrollment' => $currentEnrollment,
                'scholarship_type' => $scholarshipType,
                'employment_status' => $employmentStatus,
                'is_indigenous_person' => $isIndigenousPerson,
                'region_of_origin' => $regionOfOrigin,
                'previous_semester_cleared' => $previousSemesterCleared,
                'triage' => $triage,
                'trashed' => $trashedFilter,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'options' => $optionsResolver,
        ]);
    }

    public function show(Student $student): Response
    {
        $this->authorizeStudentAccess(['ViewAny:Student', 'View:Student']);

        $studentSchoolId = $student->school_id ?: $student->institution_id;
        abort_if($studentSchoolId && ! app(TenantContext::class)->canAccessOrganization((int) $studentSchoolId), 404);
        $student->loadMissing([
            'Course',
            'shsStrand',
            'user:id,name,email,email_verified_at,suspended_at',
            'studentContactsInfo',
            'studentParentInfo',
            'studentEducationInfo',
            'personalInfo',
            'DocumentLocation',
            'resources',
            'subjectEnrolled.subject', // For checklist
            'subjects', // For checklist
        ]);

        $studentType = $student->student_type;

        // Get General Settings
        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();
        $currentStatusRecord = StudentStatusRecord::query()
            ->where('student_id', $student->id)
            ->where('academic_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->first();
        $studentStatus = $currentStatusRecord?->status;

        // Construct checklist data
        $checklist = [];
        $gradingSystem = app(GradingSystemService::class);
        $gradingConfig = $gradingSystem->getConfig($student->school);
        $groupedSubjects = $student->subjects()->orderBy('academic_year')->orderBy('semester')->get()->groupBy('academic_year');
        $subjectEnrolled = $student->subjectEnrolled
            ->filter(fn (SubjectEnrollment $enrollment): bool => $enrollment->classification !== SubjectEnrolledEnum::NON_CREDITED->value)
            ->groupBy('subject_id');

        $nonCreditedSubjects = $student->subjectEnrolled
            ->filter(fn (SubjectEnrollment $enrollment): bool => $enrollment->classification === SubjectEnrolledEnum::NON_CREDITED->value)
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (SubjectEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'grade' => $enrollment->grade,
                'remarks' => $enrollment->remarks,
                'school_name' => $enrollment->school_name,
                'external_subject_code' => $enrollment->external_subject_code,
                'external_subject_title' => $enrollment->external_subject_title,
                'external_subject_units' => $enrollment->external_subject_units,
                'academic_year' => $enrollment->academic_year,
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'linked_subject' => $enrollment->subject ? [
                    'id' => $enrollment->subject->id,
                    'code' => $enrollment->subject->code,
                    'title' => $enrollment->subject->title,
                ] : null,
            ])
            ->all();

        foreach ($groupedSubjects as $year => $subjectsForYear) {
            $semesters = $subjectsForYear->groupBy('semester');
            $semesterData = [];

            foreach ($semesters as $semester => $subjects) {
                $subjectList = [];
                foreach ($subjects as $subject) {
                    $enrollments = $subjectEnrolled->get($subject->id, collect());

                    $retakeStrategy = $gradingConfig['retake_strategy'] ?? 'latest';
                    $gradedEnrollments = $enrollments->filter(fn ($e): bool => $e->grade !== null || $e->grade_symbol !== null);

                    $enrolledSubject = match ($retakeStrategy) {
                        'highest' => $gradedEnrollments->isNotEmpty()
                            ? (($gradingConfig['direction'] ?? 'higher_is_better') === 'lower_is_better'
                                ? $gradedEnrollments->sortBy(fn ($e): float => is_numeric($e->grade) ? (float) $e->grade : INF)->first()
                                : $gradedEnrollments->sortByDesc(fn ($e): float => is_numeric($e->grade) ? (float) $e->grade : -INF)->first())
                            : $enrollments->sortByDesc('id')->first(),
                        'first' => $enrollments->firstWhere(fn ($e): bool => $e->grade !== null || $e->grade_symbol !== null) ?? $enrollments->first(),
                        default => $enrollments->sortByDesc('id')->firstWhere(fn ($e): bool => $e->grade !== null || $e->grade_symbol !== null) ?? $enrollments->sortByDesc('id')->first(),
                    };

                    $status = 'Not Completed';
                    $grade = '-';

                    if ($enrolledSubject) {
                        if ($enrolledSubject->grade !== null || $enrolledSubject->grade_symbol !== null) {
                            $outcome = $enrolledSubject->grade_outcome ?? null;
                            $zeroIsDropped = (bool) ($gradingConfig['zero_is_dropped'] ?? false);
                            $isDropped = in_array($outcome, ['withdrawn', 'dropped'], true)
                                || ($zeroIsDropped && (float) $enrolledSubject->grade === 0.0);

                            if ($isDropped) {
                                $status = 'Dropped';
                            } elseif ($outcome === 'pass' || $gradingSystem->isPassingGrade($enrolledSubject->grade, $gradingConfig)) {
                                $status = 'Completed';
                            } else {
                                $status = 'Failed';
                            }
                            $grade = $enrolledSubject->grade_symbol ?? number_format((float) $enrolledSubject->grade, (int) $gradingConfig['decimal_places']);
                        } else {
                            $status = 'In Progress';
                        }
                    }

                    $enrollmentsHistory = $enrollments->map(fn ($e): array => [
                        'id' => $e->id,
                        'enrollment_id' => $e->enrollment_id,
                        'grade' => $e->grade,
                        'grade_symbol' => $e->grade_symbol,
                        'grade_outcome' => $e->grade_outcome,
                        'grade_quality_points' => $e->grade_quality_points,
                        'remarks' => $e->remarks,
                        'classification' => $e->classification,
                        'school_name' => $e->school_name,
                        'external_subject_code' => $e->external_subject_code,
                        'external_subject_title' => $e->external_subject_title,
                        'external_subject_units' => $e->external_subject_units,
                        'credited_subject_id' => $e->credited_subject_id,
                        'academic_year' => $e->academic_year,
                        'school_year' => $e->school_year,
                        'semester' => $e->semester,
                        'created_at' => $e->created_at,
                    ])->sortByDesc('created_at')->values()->all();

                    $subjectList[] = [
                        'id' => $subject->id,
                        'enrollment_id' => $enrolledSubject ? $enrolledSubject->id : null,
                        'is_enrolled' => $enrolledSubject !== null,
                        'code' => $subject->code,
                        'title' => $subject->title,
                        'units' => $subject->units,
                        'status' => $status,
                        'grade' => $enrolledSubject ? ($enrolledSubject->grade_symbol ?? (is_numeric($enrolledSubject->grade) ? (float) $enrolledSubject->grade : $enrolledSubject->grade)) : null,
                        'grade_symbol' => $enrolledSubject?->grade_symbol,
                        'grade_outcome' => $enrolledSubject?->grade_outcome,
                        'grade_quality_points' => $enrolledSubject?->grade_quality_points,
                        'remarks' => $enrolledSubject ? $enrolledSubject->remarks : null,
                        'classification' => $enrolledSubject ? $enrolledSubject->classification : 'internal',
                        'school_name' => $enrolledSubject ? $enrolledSubject->school_name : null,
                        'external_subject_code' => $enrolledSubject ? $enrolledSubject->external_subject_code : null,
                        'external_subject_title' => $enrolledSubject ? $enrolledSubject->external_subject_title : null,
                        'external_subject_units' => $enrolledSubject ? $enrolledSubject->external_subject_units : null,
                        'credited_subject_id' => $enrolledSubject ? $enrolledSubject->credited_subject_id : null,
                        'academic_year' => $enrolledSubject ? $enrolledSubject->academic_year : $year,
                        'school_year' => $enrolledSubject ? $enrolledSubject->school_year : $currentSchoolYear,
                        'semester' => $enrolledSubject ? $enrolledSubject->semester : $semester,
                        'history' => $enrollmentsHistory,
                    ];
                }
                $semesterData[] = [
                    'semester' => $semester,
                    'subjects' => $subjectList,
                ];
            }

            $checklist[] = [
                'year' => $year,
                'semesters' => $semesterData,
            ];
        }

        // Generate School Years (2000 to Current + 1)
        $schoolYears = [];
        $startYear = 2000;
        $endYear = (int) date('Y');
        for ($year = $startYear; $year <= $endYear; $year++) {
            $sy = $year.' - '.($year + 1);
            $schoolYears[] = ['value' => $sy, 'label' => $sy];
        }
        $schoolYears = array_reverse($schoolYears);

        // Additional Options for Actions
        $studentIdUpdateService = app(StudentIdUpdateService::class);
        $idChanges = $studentIdUpdateService->getStudentChangeHistory($student->id)
            ->where('is_undone', false)
            ->map(function ($change): array {
                $date = $change->created_at->format('M j, Y g:i A');

                return [
                    'value' => $change->id,
                    'label' => sprintf('Changed from %s to %s on %s', $change->old_student_id, $change->new_student_id, $date),
                ];
            })->values()->all();

        $enrollmentIds = $student->subjectEnrolled()
            ->select('enrollment_id')
            ->distinct()
            ->whereNotNull('enrollment_id')
            ->get()
            ->pluck('enrollment_id')
            ->map(fn ($id): array => ['value' => $id, 'label' => 'Enrollment #'.$id])
            ->values()
            ->all();

        $previousPeriod = $student->getPreviousAcademicPeriod();

        // Get current enrollment and tuition (including trashed records)
        // $generalSettingsService and current years already initialized at top
        $activeEnrollment = $this->findCurrentEnrollmentForStudent($student, $currentSchoolYear, $currentSemester, includeTrashed: false);
        $currentEnrollment = $activeEnrollment
            ?? $this->findCurrentEnrollmentForStudent($student, $currentSchoolYear, $currentSemester, includeTrashed: true);

        if ($currentEnrollment) {
            $currentEnrollment->loadMissing([
                'studentTuition' => function ($query): void {
                    $query->withTrashed();
                },
            ]);
        }

        // Get tuition from enrollment (this is the correct way)
        $tuition = $currentEnrollment?->studentTuition;

        // Format tuition data
        $tuitionData = null;
        if ($tuition) {
            // Append the total_paid accessor to calculate it
            $tuition->append('total_paid');

            $tuitionData = [
                'id' => $tuition->id,
                'semester' => $tuition->semester,
                'school_year' => $tuition->school_year,
                'academic_year' => $tuition->academic_year,
                'total_tuition' => $tuition->formatted_total_tuition,
                'total_lectures' => $tuition->formatted_total_lectures,
                'total_laboratory' => $tuition->formatted_total_laboratory,
                'total_miscelaneous_fees' => $tuition->formatted_total_miscelaneous_fees,
                'discount' => $tuition->formatted_discount,
                'downpayment' => $tuition->formatted_downpayment,
                'overall_tuition' => $tuition->formatted_overall_tuition,
                'total_paid' => $tuition->formatted_total_paid,
                'total_balance' => $tuition->formatted_total_balance,
                'payment_status' => $tuition->payment_status,
                'payment_progress' => $tuition->payment_progress,
                'status_class' => $tuition->status_class,
                'adjustment_note' => $tuition->adjustment_note,
                'adjusted_at' => $tuition->adjusted_at?->format('M j, Y g:i A'),
            ];
        }

        // Get Enrolled Subjects and Classes with Schedules (Current)

        $currentEnrolledClasses = $student->classEnrollments()
            ->with([
                'class.subject',
                'class.schedules.room',
                'class.faculty',
            ])
            ->whereHas('class', function ($query) use ($currentSchoolYear, $currentSemester): void {
                $schoolYearWithSpaces = $currentSchoolYear;
                $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);
                $query->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                    ->where('semester', $currentSemester);
            })
            ->get()
            ->map(function ($enrollment): ?array {
                if (! $enrollment->class) {
                    return null;
                }

                $class = $enrollment->class;
                $schedules = $class->schedules->map(fn ($schedule): array => [
                    'day' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time instanceof \Carbon\Carbon
                        ? $schedule->start_time->format('H:i')
                        : date('H:i', strtotime((string) $schedule->start_time)),
                    'end_time' => $schedule->end_time instanceof \Carbon\Carbon
                        ? $schedule->end_time->format('H:i')
                        : date('H:i', strtotime((string) $schedule->end_time)),
                    'room' => $schedule->room->name ?? 'TBA',
                ])->values()->all();

                // Deterministic color assignment
                $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6'];
                $colorIndex = abs(crc32($class->subject_code ?? 'default')) % count($colors);

                return [
                    'class_id' => $class->id,
                    'subject_code' => $class->subject_code ?? 'N/A',
                    'subject_title' => $class->subject_title ?? 'N/A',
                    'units' => $class->subject->units ?? 0,
                    'section' => $class->section ?? 'N/A',
                    'faculty' => $class->faculty->full_name ?? 'TBA',
                    'schedules' => $schedules,
                    'color' => $colors[$colorIndex],
                ];
            })
            ->filter()
            ->values();

        $actor = Auth::user();
        $isGradeReleaseAdmin = in_array($actor?->role, [UserRole::Developer, UserRole::SuperAdmin, UserRole::Admin], true);
        $canManageGradeClearance = $isGradeReleaseAdmin || (bool) $actor?->can('manage_clearance');
        $canPublishGrades = $isGradeReleaseAdmin || (($actor?->isAdministrative() || in_array($actor?->role, [UserRole::Registrar, UserRole::AssistantRegistrar], true)) && $actor?->can('Update:Student'));
        $canViewGradeRelease = $canManageGradeClearance || $canPublishGrades || (bool) $actor?->can('view_clearance');

        $portalUser = $student->user;
        if (! $portalUser && filled($student->email)) {
            $portalUser = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim((string) $student->email))])
                ->first(['id', 'name', 'email', 'email_verified_at', 'suspended_at']);
        }

        $portalAccount = $portalUser ? [
            'id' => $portalUser->id,
            'name' => $portalUser->name,
            'email' => $portalUser->email,
            'is_linked' => (int) $student->user_id === (int) $portalUser->id,
            'email_verified' => $portalUser->email_verified_at !== null,
            'suspended' => $portalUser->suspended_at !== null,
            'email_mismatch' => filled($student->email) && strcasecmp((string) $student->email, (string) $portalUser->email) !== 0,
        ] : null;

        $activeHolds = Schema::hasTable('student_holds')
            ? $student->holds()
                ->active()
                ->with('placedBy:id,name')
                ->latest('placed_at')
                ->get()
                ->map(fn (StudentHold $hold): array => [
                    'id' => $hold->id,
                    'office' => $hold->office,
                    'office_label' => $hold->officeLabel(),
                    'severity' => $hold->severity,
                    'title' => $hold->title,
                    'reason' => $hold->reason,
                    'blocks_enrollment' => $hold->blocks_enrollment,
                    'blocks_add_drop' => $hold->blocks_add_drop,
                    'blocks_grade_release' => $hold->blocks_grade_release,
                    'placed_by' => $hold->placedBy?->name,
                    'placed_at' => $hold->placed_at?->format('M j, Y'),
                ])
                ->values()
                ->all()
            : [];

        $dossier = app(StudentDocumentService::class)->dossierFor($student);
        $configService = app(StudentConfigurationService::class);
        $studentConfig = $configService->get($student->school_id);
        $schoolProfile = $configService->schoolProfile($student->school_id);
        $profileCompletion = app(StudentProfileCompletionService::class)->summarize($student);

        return Inertia::render('administrators/students/show', [
            'user' => $this->getUserProps(),
            'options' => [
                'school_years' => $schoolYears,
                'statuses' => collect(StudentStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
                'classifications' => array_column(SubjectEnrolledEnum::cases(), 'value'),
                'credited_subjects' => Subject::where('is_credited', true)->get()->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code.' - '.$s->title]),
                'school_names' => SubjectEnrollment::query()
                    ->whereNotNull('school_name')
                    ->distinct()
                    ->pluck('school_name')
                    ->sort()
                    ->values()
                    ->all(),
                'id_changes' => $idChanges,
                'enrollment_ids' => $enrollmentIds,
                'previous_period' => $previousPeriod,
                'courses' => Course::all(['id', 'code', 'title'])
                    ->map(fn ($c): array => ['value' => $c->id, 'label' => $c->code.' - '.$c->title])
                    ->values()
                    ->all(),
                'student_configuration' => $studentConfig,
                'identifier_policy' => app(IdentifierGenerator::class)->studentPolicy(schoolId: $student->school_id),
                'school_profile' => $schoolProfile,
            ],
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'lrn' => $student->lrn,
                'name' => $student->full_name,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'gender' => $student->gender,
                'birth_date' => $student->birth_date?->format('F j, Y'),
                'type' => $studentType instanceof StudentType ? $studentType->value : (is_string($studentType) ? $studentType : null),
                'status' => $studentStatus instanceof StudentStatus ? $studentStatus->value : (is_string($studentStatus) ? $studentStatus : null),
                'graduation_school_year' => $student->graduation_school_year,
                'graduation_semester' => $student->graduation_semester,
                'academic_year' => $student->formatted_academic_year,
                // Raw year level, needed by the inline editor to reconcile optimistic updates.
                'academic_year_value' => $student->academic_year,
                'custom_fields' => $configService->displayCustomValues($student),
                'year_level_options' => $studentConfig['year_levels'][$student->student_type?->value ?? 'college'] ?? [],
                'course' => [
                    'id' => $student->Course?->id,
                    'code' => $student->Course?->code ?? $student->shsStrand?->strand_name,
                    'title' => $student->Course?->title ?? ($student->shsStrand ? 'Senior High Strand' : null),
                ],
                'portal_account' => $portalAccount,
                'active_holds' => $activeHolds,
                'profile_completion' => $profileCompletion,
                'dossier' => $dossier,
                'export_zip_url' => route('administrators.students.documents.export-zip', $student->id),
                'created_at' => format_timestamp($student->created_at),
                'updated_at' => format_timestamp($student->updated_at),
                'deleted_at' => $student->deleted_at ? format_timestamp($student->deleted_at) : null,
                'is_trashed' => $student->trashed(),
                'contacts' => $student->studentContactsInfo,
                'parents' => $student->studentParentInfo,
                'education' => $student->studentEducationInfo,
                'personal_info' => $student->personalInfo,
                'documents' => $student->DocumentLocation?->toResolvedDocumentArray($student),
                'signature_url' => $this->resolveStudentSignatureUrl($student),
                'grade_release_periods' => $canViewGradeRelease ? app(GradeReleaseService::class)->periods($student) : [],
                'grade_release_clearance_required' => app(GradeReleaseService::class)->requiresClearance(),
                'can_manage_grade_clearance' => $canManageGradeClearance,
                'can_publish_grades' => $canPublishGrades,
                'can_view_grade_release' => $canViewGradeRelease,
                'tuition' => $tuitionData,
                'current_school_year' => $currentSchoolYear,
                'current_semester' => $currentSemester,
                'current_enrollment_id' => $activeEnrollment?->id,
                'current_enrollment_url' => $activeEnrollment
                    ? route('administrators.enrollments.show', $activeEnrollment->id)
                    : route('administrators.students.current-enrollment', $student->id),
                'current_enrollment' => $activeEnrollment ? [
                    'id' => $activeEnrollment->id,
                    'status' => $activeEnrollment->status,
                    'school_year' => $activeEnrollment->school_year,
                    'semester' => $activeEnrollment->semester,
                ] : null,
                'current_enrolled_classes' => $currentEnrolledClasses,
                'checklist' => $checklist,
                'non_credited_subjects' => $nonCreditedSubjects,
                'filament' => [
                    'view_url' => route('filament.admin.resources.students.view', $student),
                    'edit_url' => route('filament.admin.resources.students.edit', $student),
                ],
            ],
        ]);
    }

    public function printSoa(Request $request, Student $student): JsonResponse
    {
        $settings = app(GeneralSettingsService::class);
        $semester = (int) $request->input('semester', $settings->getCurrentSemester());
        $schoolYearInput = $request->input('school_year', $settings->getCurrentSchoolYearString());
        $schoolYear = is_string($schoolYearInput) && $schoolYearInput !== '' ? $schoolYearInput : $settings->getCurrentSchoolYearString();

        $student->loadMissing('Course');

        /** @var StudentTuition|null $tuition */
        $tuition = StudentTuition::with('enrollment.student')
            ->where('student_id', $student->id)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->first();

        if (! $tuition) {
            $enrollment = StudentEnrollment::query()
                ->where('student_id', $student->id)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->first();

            if ($enrollment) {
                $tuition = StudentTuition::with('enrollment.student')
                    ->where('enrollment_id', $enrollment->id)
                    ->first();
            }
        }

        if ($tuition) {
            $tuition->append([
                'formatted_total_balance',
                'formatted_overall_tuition',
                'formatted_total_tuition',
                'formatted_semester',
                'formatted_total_lectures',
                'formatted_total_laboratory',
                'formatted_total_miscelaneous_fees',
                'formatted_downpayment',
                'formatted_discount',
                'formatted_total_paid',
                'payment_progress',
                'payment_status',
            ]);
        }

        $transactions = collect();
        $enrollment = $tuition?->enrollment;

        if (! $enrollment) {
            $enrollment = StudentEnrollment::query()
                ->where('student_id', $student->id)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->first();
        }

        if ($enrollment) {
            $enrollment->setRelation('student', $student);
            $transactions = $enrollment->enrollmentTransactions()
                ->with('transaction')
                ->get();
        }

        $mappedTransactions = $transactions->map(function ($transaction): array {
            $payment = $transaction->relationLoaded('transaction') && $transaction->transaction ? $transaction->transaction : $transaction;
            $appliedAmount = $transaction->amount
                ?? $payment->raw_total_amount
                ?? $payment->total_amount
                ?? 0;

            return [
                'id' => $transaction->id,
                'date' => ($payment->transaction_date ?? $payment->created_at ?? $transaction->created_at)?->format('M d, Y'),
                'description' => $payment->description ?? 'Tuition Payment',
                'amount' => (float) $appliedAmount,
                'status' => $transaction->status ?? $payment->status,
                'invoice' => $payment->invoicenumber,
                'method' => $payment->payment_method,
            ];
        });

        $generalSettings = DB::table('general_settings')->first();
        $siteSettings = app(SiteSettings::class);
        $generatedAt = now()->format('F d, Y h:i A');
        $currencyCode = $siteSettings->getCurrency();
        $currencySymbol = match ($currencyCode) {
            'USD' => '$',
            'EUR' => '€',
            default => '₱',
        };

        $viewData = [
            'student' => [
                'id' => $student->id,
                'student_no' => $student->student_id ?: $student->id,
                'name' => $student->full_name ?? $student->name,
                'email' => $student->email,
                'course' => $student->Course?->title ?? $student->Course?->code ?? 'N/A',
            ],
            'tuition' => $tuition,
            'transactions' => $mappedTransactions,
            'filters' => [
                'semester' => $semester,
                'school_year' => $schoolYear,
            ],
            'school' => [
                'name' => $siteSettings->getOrganizationName() ?: ($generalSettings?->school_portal_title ?? $generalSettings?->site_name ?? 'KoAkademy'),
                'address' => $siteSettings->getOrganizationAddress() ?? '',
                'logo' => $siteSettings->getLogo(),
                'favicon' => $siteSettings->getFavicon(),
                'tagline' => $siteSettings->getTagline(),
            ],
            'generated_at' => $generatedAt,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
        ];

        $studentNumber = (string) ($student->student_id ?: $student->id);
        $safeStudentNumber = preg_replace('/[^A-Za-z0-9_-]/', '-', $studentNumber) ?: 'student';
        $downloadName = sprintf(
            'soa-%s-%s-sem-%s.pdf',
            $safeStudentNumber,
            preg_replace('/\s+/', '', str_replace('-', '', $schoolYear)),
            $semester
        );

        GenerateStudentSoaPdfJob::dispatch($viewData, $downloadName, (int) Auth::id());

        return response()->json([
            'message' => 'SOA PDF generation queued. You will be notified when the file is ready.',
        ], 202);
    }

    public function currentEnrollment(Request $request, Student $student): RedirectResponse
    {
        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $enrollment = $this->findCurrentEnrollmentForStudent(
            $student,
            $currentSchoolYear,
            $currentSemester,
            includeTrashed: false
        );

        if ($enrollment) {
            return redirect()->route('administrators.enrollments.show', $enrollment->id);
        }

        return redirect()
            ->route('administrators.students.show', $student->id)
            ->with('error', "No active enrollment record found for {$student->full_name} in S.Y. {$currentSchoolYear}, Semester {$currentSemester}.");
    }

    public function create(CurriculumCapabilityResolver $capabilityResolver): Response
    {
        $this->authorizeStudentAccess('Create:Student');

        return Inertia::render('administrators/students/create', [
            'user' => fn (): array => $this->getUserProps(),
            'options' => fn (): array => $this->getFormOptions($capabilityResolver, null, false),
        ]);
    }

    public function store(Request $request, CurriculumCapabilityResolver $capabilityResolver): RedirectResponse
    {
        $this->authorizeStudentAccess('Create:Student');

        $identifierGenerator = app(IdentifierGenerator::class);
        $configuration = app(StudentConfigurationService::class);
        $typeValue = (string) $request->input('student_type');
        $usesExternal = $configuration->usesExternal($typeValue);
        // Older API clients may omit the mode: supplied values are manual, never inferred from a preview.
        $mode = (string) $request->input('identifier_assignment', $request->filled('student_id') ? 'manual' : 'automatic');

        // Sanitize name fields to prevent stored XSS attacks
        $sanitizedNames = [];
        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if ($request->filled($field)) {
                $sanitizedNames[$field] = strip_tags((string) $request->input($field));
            }
        }
        if ($sanitizedNames !== []) {
            $request->merge($sanitizedNames);
        }

        $rules = [
            'identifier_assignment' => ['nullable', 'in:automatic,manual'],
            'student_type' => ['required', Rule::enum(StudentType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female,other,prefer_not_to_say'],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'course_id' => [
                Rule::requiredIf(fn (): bool => $request->student_type !== StudentType::SeniorHighSchool->value),
                'nullable',
                'exists:courses,id',
            ],
            'academic_year' => ['required', 'integer'],
            'shs_strand_id' => [
                Rule::requiredIf(fn (): bool => $request->student_type === StudentType::SeniorHighSchool->value),
                'nullable',
                'exists:shs_strands,id',
            ],
            'student_id' => $usesExternal || $mode === 'automatic' ? ['exclude'] : $identifierGenerator->getStudentIdValidationRules(StudentType::tryFrom($typeValue)),
            'lrn' => [
                Rule::requiredIf(fn (): bool => $request->student_type === StudentType::SeniorHighSchool->value),
                'nullable',
                'string',
                'max:20',
                'unique:students,lrn',
            ],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'remarks' => ['nullable', 'string'],
            'submit_action' => ['nullable', 'string', 'in:view,create_another,create_enrollment'],

            'personal_contact' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'facebook_contact' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'fathers_name' => ['nullable', 'string', 'max:100'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'father_contact' => ['nullable', 'string', 'max:30'],
            'father_email' => ['nullable', 'email', 'max:255'],
            'mothers_name' => ['nullable', 'string', 'max:100'],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'mother_contact' => ['nullable', 'string', 'max:30'],
            'mother_email' => ['nullable', 'email', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_contact' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'family_address' => ['nullable', 'string', 'max:500'],
            'elementary_school' => ['nullable', 'string', 'max:255'],
            'elementary_graduate_year' => ['nullable', 'string', 'max:4'],
            'elementary_school_address' => ['nullable', 'string', 'max:500'],
            'junior_high_school_name' => ['nullable', 'string', 'max:255'],
            'junior_high_graduation_year' => ['nullable', 'string', 'max:4'],
            'junior_high_school_address' => ['nullable', 'string', 'max:500'],
            'senior_high_name' => ['nullable', 'string', 'max:255'],
            'senior_high_graduate_year' => ['nullable', 'string', 'max:4'],
            'senior_high_address' => ['nullable', 'string', 'max:500'],
            'college_school' => ['nullable', 'string', 'max:255'],
            'college_course' => ['nullable', 'string', 'max:255'],
            'college_year_graduated' => ['nullable', 'string', 'max:4'],
            'vocational_school' => ['nullable', 'string', 'max:255'],
            'vocational_course' => ['nullable', 'string', 'max:255'],
            'vocational_year_graduated' => ['nullable', 'string', 'max:4'],
            'current_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'city_of_origin' => ['nullable', 'string', 'max:100'],
            'province_of_origin' => ['nullable', 'string', 'max:100'],
            'region_of_origin' => ['nullable', 'string', 'max:50'],
            'is_indigenous_person' => ['nullable', 'boolean'],
            'indigenous_group' => ['nullable', 'string', 'max:100'],
            'is_pwd' => ['nullable', 'boolean'],
            'pwd_type' => ['nullable', 'string', 'max:100'],
            'is_solo_parent' => ['nullable', 'boolean'],
            'is_solo_parent_dependent' => ['nullable', 'boolean'],
            'is_senior_citizen' => ['nullable', 'boolean'],
            'is_magna_carta' => ['nullable', 'boolean'],
            'is_underprivileged' => ['nullable', 'boolean'],
            'is_first_generation' => ['nullable', 'boolean'],
            'income_bracket_mode' => ['nullable', 'string', 'max:50'],
            'use_same_parent_income' => ['nullable', 'boolean'],
            'family_income_bracket' => ['nullable', 'string', 'max:50'],
            'father_income_bracket' => ['nullable', 'string', 'max:50'],
            'mother_income_bracket' => ['nullable', 'string', 'max:50'],
            'withdrawal_date' => ['nullable', 'date'],
            'withdrawal_reason' => ['nullable', 'string'],
            'attrition_category' => ['nullable', Rule::enum(AttritionCategory::class)],
            'dropout_date' => ['nullable', 'date'],
            'scholarship_type' => ['nullable', Rule::enum(ScholarshipType::class)],
            'scholarship_details' => ['nullable', 'string'],
            'employment_status' => ['nullable', Rule::enum(EmploymentStatus::class)],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'job_position' => ['nullable', 'string', 'max:255'],
            'employment_date' => ['nullable', 'date'],
            'employed_by_institution' => ['nullable', 'boolean'],
            'picture_1x1' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'signature' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:5120'],
            'documents.picture_1x1' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'provision_portal_account' => ['nullable', 'boolean'],
            'send_activation_link' => ['nullable', 'boolean'],
        ];
        $validated = $request->validate([...$configuration->rules($rules, $typeValue), ...$configuration->customFieldRules()]);

        if (($validated['student_type'] ?? null) === StudentType::TESDA->value && isset($validated['course_id'])) {
            $course = Course::query()->with('department')->find($validated['course_id']);

            if ($course instanceof Course && ! $capabilityResolver->isTesdaCourse($course)) {
                return back()->withErrors(['course_id' => 'Select a TESDA qualification for a TESDA student.'])->withInput();
            }
        }

        if (($validated['student_type'] ?? null) === StudentType::College->value && isset($validated['course_id'])) {
            $course = Course::query()->with('department')->find($validated['course_id']);

            if ($course instanceof Course && ! $capabilityResolver->isCollegeCourse($course)) {
                return back()->withErrors(['course_id' => 'Select a college program for a college student.'])->withInput();
            }
        }

        if (blank($validated['guardian_name'] ?? null) && filled($validated['emergency_contact_name'] ?? null)) {
            $validated['guardian_name'] = $validated['emergency_contact_name'];
        }

        if (blank($validated['guardian_contact'] ?? null) && filled($validated['emergency_contact_phone'] ?? null)) {
            $validated['guardian_contact'] = $validated['emergency_contact_phone'];
        }

        if (blank($validated['guardian_relationship'] ?? null) && filled($validated['emergency_contact_relationship'] ?? null)) {
            $validated['guardian_relationship'] = $validated['emergency_contact_relationship'];
        }

        $schoolId = app(TenantContext::class)->getCurrentSchoolId();
        $student = DB::transaction(function () use ($identifierGenerator, $validated, $usesExternal, $mode, $configuration, $schoolId): Student {
            $identifierGenerator->sequenceFor(IdentifierGenerator::Student, lock: true, schoolId: $schoolId);
            $configuration->forget($schoolId);
            \Illuminate\Support\Facades\Validator::make($validated, $configuration->customFieldRules(schoolId: $schoolId))->validate();
            $studentType = StudentType::from($validated['student_type']);
            $status = StudentStatus::from($validated['status']);
            $birthDate = Carbon::parse($validated['birth_date']);

            $student = new Student();
            $student->fill([
                'student_type' => $studentType->value,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'suffix' => $validated['suffix'] ?? null,
                'gender' => $validated['gender'],
                'birth_date' => $birthDate->toDateString(),
                'age' => $birthDate->age,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'civil_status' => $validated['civil_status'] ?? null,
                'nationality' => $validated['nationality'] ?? ($validated['citizenship'] ?? null),
                'religion' => $validated['religion'] ?? null,
                'address' => ($validated['current_address'] ?? null) ?: ($validated['permanent_address'] ?? null),
                'emergency_contact' => $validated['emergency_contact_name'] ?? null,
                'academic_year' => $validated['academic_year'],
                'remarks' => $validated['remarks'] ?? null,
                'status' => $status->value,
                'ethnicity' => $validated['ethnicity'] ?? null,
                'city_of_origin' => $validated['city_of_origin'] ?? null,
                'province_of_origin' => $validated['province_of_origin'] ?? null,
                'region_of_origin' => $validated['region_of_origin'] ?? null,
                'is_indigenous_person' => $validated['is_indigenous_person'] ?? false,
                'indigenous_group' => $validated['indigenous_group'] ?? null,
                'is_pwd' => $validated['is_pwd'] ?? false,
                'pwd_type' => $validated['pwd_type'] ?? null,
                'is_solo_parent' => $validated['is_solo_parent'] ?? false,
                'is_senior_citizen' => $validated['is_senior_citizen'] ?? false,
                'is_magna_carta' => $validated['is_magna_carta'] ?? false,
                'is_underprivileged' => $validated['is_underprivileged'] ?? false,
                'is_first_generation' => $validated['is_first_generation'] ?? false,
                'income_bracket_mode' => $validated['income_bracket_mode'] ?? (string) config('income_brackets.default_mode', 'annual'),
                'use_same_parent_income' => $validated['use_same_parent_income'] ?? true,
                'family_income_bracket' => $validated['family_income_bracket'] ?? null,
                'father_income_bracket' => $validated['father_income_bracket'] ?? null,
                'mother_income_bracket' => $validated['mother_income_bracket'] ?? null,
                'withdrawal_date' => $validated['withdrawal_date'] ?? null,
                'withdrawal_reason' => $validated['withdrawal_reason'] ?? null,
                'attrition_category' => $validated['attrition_category'] ?? null,
                'dropout_date' => $validated['dropout_date'] ?? null,
                'scholarship_type' => $validated['scholarship_type'] ?? ScholarshipType::None->value,
                'scholarship_details' => $validated['scholarship_details'] ?? null,
                'employment_status' => $validated['employment_status'] ?? EmploymentStatus::NotApplicable->value,
                'employer_name' => $validated['employer_name'] ?? null,
                'job_position' => $validated['job_position'] ?? null,
                'employment_date' => $validated['employment_date'] ?? null,
                'employed_by_institution' => $validated['employed_by_institution'] ?? false,
                'contacts' => $this->studentContactsPayload($validated),
            ]);

            if ($this->hasColumn('students', 'is_solo_parent_dependent')) {
                $student->is_solo_parent_dependent = (bool) ($validated['is_solo_parent_dependent'] ?? false);
            }

            $student->lrn = $validated['lrn'] ?? null;
            $student->student_id = $usesExternal
                ? $identifierGenerator->claimExternalIdentifier($studentType, $validated['lrn'])
                : $identifierGenerator->assign($studentType, $validated['student_id'] ?? null, $mode);
            $configuration->mergeCustomValues($student, $validated, $schoolId);
            if ($studentType === StudentType::SeniorHighSchool) {
                $student->shs_strand_id = $validated['shs_strand_id'];
            } else {
                $student->course_id = $validated['course_id'];
            }

            $student->save();

            $this->syncStudentRelations($student, $validated);
            $student->save();
            $this->syncCurrentStudentStatus($student, $status);

            return $student;
        });

        $assetResult = $this->persistStudentAssets($request, $student);
        $failedDocumentSlots = $assetResult['failed_slots'];
        $portalProvisioningError = null;

        if ((bool) ($validated['provision_portal_account'] ?? false) && filled($student->email)) {
            $portalRequest = Request::create('', 'POST', [
                'mode' => 'auto',
                'send_reset_link' => (bool) ($validated['send_activation_link'] ?? true),
            ]);
            $portalRequest->headers->set('Accept', 'application/json');

            try {
                $portalResponse = $this->managePortalAccount($portalRequest, $student);
                if ($portalResponse instanceof JsonResponse && $portalResponse->getStatusCode() >= 400) {
                    $payload = $portalResponse->getData(true);
                    $portalProvisioningError = is_array($payload) && is_string($payload['message'] ?? null)
                        ? $payload['message']
                        : 'Portal account could not be provisioned for this email address.';
                }
            } catch (Throwable $e) {
                Log::warning('Failed to provision student portal account during creation.', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
                $portalProvisioningError = 'Portal account provisioning failed. You can update the email and retry from this student record.';
            }
        }

        if ($failedDocumentSlots !== [] || $portalProvisioningError !== null) {
            $warnings = [];
            $errorBag = [];

            if ($failedDocumentSlots !== []) {
                $failedLabels = implode(', ', array_keys($failedDocumentSlots));
                $warnings[] = "Some document uploads failed ({$failedLabels}). Already uploaded files were preserved — retry only the failed slot(s) below.";
                foreach ($failedDocumentSlots as $slotKey => $slotError) {
                    $fieldKey = in_array($slotKey, ['picture_1x1', 'signature'], true)
                        ? $slotKey
                        : "documents.{$slotKey}";
                    $errorBag[$fieldKey] = $slotError;
                }
            }

            if ($portalProvisioningError !== null) {
                $warnings[] = 'Portal account was not provisioned: '.$portalProvisioningError;
            }

            $redirect = to_route('administrators.students.edit', $student, 303)
                ->with('success', 'Student created successfully.')
                ->with('warning', implode(' ', $warnings))
                ->with('saved_student_id', $student->id);

            if ($failedDocumentSlots !== []) {
                $redirect->with('failed_document_slots', array_keys($failedDocumentSlots));
            }

            if ($portalProvisioningError !== null) {
                $redirect->with('portal_provisioning_error', $portalProvisioningError);
            }

            if ($errorBag !== []) {
                $redirect->withErrors($errorBag);
            }

            return $redirect;
        }

        return match ($validated['submit_action'] ?? 'view') {
            'create_another' => to_route('administrators.students.create', status: 303)
                ->with('success', 'Student created successfully. You can now add another student.'),
            'create_enrollment' => to_route('administrators.enrollments.create', ['student_id' => $student->id], 303)
                ->with('success', 'Student created successfully. You can now create an enrollment.'),
            default => to_route('administrators.students.show', $student, 303)
                ->with('success', 'Student created successfully.'),
        };
    }

    public function edit(Student $student, CurriculumCapabilityResolver $capabilityResolver): Response
    {
        $this->authorizeStudentAccess('Update:Student');

        $relationsLoaded = false;
        $ensureStudentLoaded = function () use ($student, &$relationsLoaded): void {
            if ($relationsLoaded) {
                return;
            }

            $student->loadMissing([
                'Course',
                'shsStrand',
                'DocumentLocation',
                'user:id,name,email,email_verified_at,suspended_at',
                'studentContactsInfo',
                'studentParentInfo',
                'studentEducationInfo',
                'personalInfo',
                'subjectEnrolledCurrent.subject',
            ]);

            $relationsLoaded = true;
        };

        return Inertia::render('administrators/students/edit', [
            'user' => fn (): array => $this->getUserProps(),
            'student' => function () use ($student, $ensureStudentLoaded): Student {
                $ensureStudentLoaded();

                return $student;
            },
            'portal_account' => function () use ($student, $ensureStudentLoaded): ?array {
                $ensureStudentLoaded();

                $portalUser = $student->user;
                if (! $portalUser && filled($student->email)) {
                    $portalUser = User::query()
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim((string) $student->email))])
                        ->first(['id', 'name', 'email', 'email_verified_at', 'suspended_at']);
                }

                if (! $portalUser) {
                    return null;
                }

                return [
                    'id' => $portalUser->id,
                    'name' => $portalUser->name,
                    'email' => $portalUser->email,
                    'is_linked' => (int) $student->user_id === (int) $portalUser->id,
                    'email_verified' => $portalUser->email_verified_at !== null,
                    'suspended' => $portalUser->suspended_at !== null,
                ];
            },
            'student_assets' => function () use ($student, $ensureStudentLoaded): array {
                $ensureStudentLoaded();

                return [
                    'picture_1x1_url' => $student->DocumentLocation?->toResolvedDocumentArray($student)['picture_1x1'] ?? null,
                    'signature_url' => $this->resolveStudentSignatureUrl($student),
                ];
            },
            'current_enrollments' => fn () => $student->subjectEnrolledCurrent()->with(['subject', 'class'])->get()->map(function ($enrollment): ?array {
                // Handle null subject
                if (! $enrollment->subject) {
                    return null;
                }

                return [
                    'id' => $enrollment->id,
                    'subject' => [
                        'code' => $enrollment->subject->code,
                        'title' => $enrollment->subject->title,
                        'units' => $enrollment->subject->units,
                    ],
                ];
            })->filter()->values(),
            'current_classes' => fn () => $student->getCurrentClasses(),
            'options' => fn (): array => $this->getFormOptions($capabilityResolver, $student, true),
        ]);
    }

    public function generateId(Request $request, IdentifierGenerator $identifierGenerator): JsonResponse
    {
        $rawType = $request->query('type') ?? $request->query('student_type');
        $type = is_string($rawType) ? StudentType::tryFrom($rawType) : null;

        if (! $type) {
            return response()->json(['id' => null], 400);
        }

        if (app(StudentConfigurationService::class)->usesExternal($type->value)) {
            return response()->json(['id' => null]);
        }

        if (! in_array($type->value, app(StudentConfigurationService::class)->get()['enabled_types'], true)) {
            return response()->json(['id' => null], 422);
        }
        $nextId = $identifierGenerator->previewStudentId($type);

        return response()->json(['id' => $nextId]);
    }

    public function fieldValues(Request $request): JsonResponse
    {
        $field = $request->query('field', '');
        $search = $request->query('search', '');

        if (mb_strlen($search) < 1) {
            return response()->json([]);
        }

        // Whitelist of allowed fields mapped to their table and column
        $allowedFields = [
            // Student model fields
            'first_name' => ['students', 'first_name'],
            'last_name' => ['students', 'last_name'],
            'middle_name' => ['students', 'middle_name'],
            'religion' => ['students', 'religion'],
            'city_of_origin' => ['students', 'city_of_origin'],
            'province_of_origin' => ['students', 'province_of_origin'],
            'employer_name' => ['students', 'employer_name'],
            'job_position' => ['students', 'job_position'],
            'current_address' => ['students', 'current_address'],
            'permanent_address' => ['students', 'permanent_address'],
            // StudentContactsInfo fields
            'emergency_contact_name' => ['student_contacts', 'emergency_contact_name'],
            'emergency_contact_address' => ['student_contacts', 'emergency_contact_address'],
            'emergency_contact_relationship' => ['student_contacts', 'emergency_contact_relationship'],
            // StudentParentsInfo fields
            'fathers_name' => ['student_parents_info', 'father_name'],
            'mothers_name' => ['student_parents_info', 'mother_name'],
            'guardian_name' => ['student_parents_info', 'guardian_name'],
            'father_occupation' => ['student_parents_info', 'father_occupation'],
            'mother_occupation' => ['student_parents_info', 'mother_occupation'],
            'guardian_relationship' => ['student_parents_info', 'guardian_relationship'],
            'family_address' => ['student_parents_info', 'family_address'],
            // StudentEducationInfo fields
            'elementary_school' => ['student_education_info', 'elementary_school'],
            'elementary_school_address' => ['student_education_info', 'elementary_school_address'],
            'junior_high_school_name' => ['student_education_info', 'junior_high_school_name'],
            'junior_high_school_address' => ['student_education_info', 'junior_high_school_address'],
            'senior_high_name' => ['student_education_info', ['senior_high_name', 'senior_high_school']],
            'senior_high_address' => ['student_education_info', 'senior_high_address'],
            'college_school' => ['student_education_info', 'college_school'],
            'college_course' => ['student_education_info', 'college_course'],
            'vocational_school' => ['student_education_info', 'vocational_school'],
            'vocational_course' => ['student_education_info', 'vocational_course'],
            // StudentsPersonalInfo fields
            'birthplace' => ['students_personal_info', 'birthplace'],
            'ethnicity' => ['students_personal_info', 'ethnicity'],
        ];

        if (! array_key_exists($field, $allowedFields)) {
            return response()->json([]);
        }

        [$table, $column] = $allowedFields[$field];
        $columns = collect(is_array($column) ? $column : [$column])
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values();
        $like = '%'.mb_strtolower($search).'%';

        if ($columns->isEmpty()) {
            return response()->json([]);
        }

        $schoolId = app(TenantContext::class)->getCurrentSchoolId();

        $results = $columns
            ->flatMap(function (string $column) use ($table, $like, $schoolId): array {
                $query = DB::table($table)
                    ->whereRaw('LOWER('.$table.'.'.$column.') LIKE ?', [$like])
                    ->whereNotNull($table.'.'.$column)
                    ->where($table.'.'.$column, '!=', '');

                if ($schoolId !== null) {
                    if ($table === 'students') {
                        $query->where('students.school_id', $schoolId);
                    } else {
                        $fkColumn = match ($table) {
                            'student_contacts' => 'student_contact_id',
                            'student_parents_info' => 'student_parent_info',
                            'student_education_info' => 'student_education_id',
                            'students_personal_info' => 'student_personal_id',
                            default => null,
                        };

                        if ($fkColumn !== null) {
                            $query->where(function ($scope) use ($table, $fkColumn, $schoolId): void {
                                $scope->whereExists(function ($sub) use ($table, $fkColumn, $schoolId): void {
                                    $sub->selectRaw('1')
                                        ->from('students')
                                        ->whereColumn("students.{$fkColumn}", "{$table}.id")
                                        ->where('students.school_id', $schoolId);
                                })->orWhereNotExists(function ($sub) use ($table, $fkColumn): void {
                                    $sub->selectRaw('1')
                                        ->from('students')
                                        ->whereColumn("students.{$fkColumn}", "{$table}.id");
                                });
                            });
                        }
                    }
                }

                return $query
                    ->distinct()
                    ->orderBy($table.'.'.$column)
                    ->limit(15)
                    ->pluck($table.'.'.$column)
                    ->all();
            })
            ->map(fn (mixed $value): string => mb_trim((string) $value))
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->sortBy(fn (string $value): string => mb_strtolower($value))
            ->values()
            ->take(15);

        return response()->json($results);
    }

    public function educationSchoolOptions(Request $request, StudentSchoolOptionService $schoolOptions): JsonResponse
    {
        $field = (string) $request->query('field', '');
        $search = (string) $request->query('search', '');

        return response()->json($schoolOptions->search($field, $search));
    }

    public function update(Request $request, Student $student, CurriculumCapabilityResolver $capabilityResolver): RedirectResponse
    {
        $this->authorizeStudentAccess('Update:Student');

        $identifierGenerator = app(IdentifierGenerator::class);
        $configuration = app(StudentConfigurationService::class);
        $usesExternal = $configuration->usesExternal((string) $request->input('student_type'), $student->school_id)
            && ((string) $student->student_id === (string) $student->lrn || blank($student->student_id));
        if (! $usesExternal && blank($request->input('student_id'))) {
            $request->merge(['student_id' => $student->student_id]);
        }

        // Sanitize name fields to prevent stored XSS attacks
        $sanitizedNames = [];
        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if ($request->filled($field)) {
                $sanitizedNames[$field] = strip_tags((string) $request->input($field));
            }
        }
        if ($sanitizedNames !== []) {
            $request->merge($sanitizedNames);
        }

        $rules = [
            'student_type' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female,other,prefer_not_to_say'],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'course_id' => [
                Rule::requiredIf(fn (): bool => $request->student_type !== StudentType::SeniorHighSchool->value),
                'nullable',
                'exists:courses,id',
            ],
            'academic_year' => ['required', 'integer'],
            'shs_strand_id' => [
                Rule::requiredIf(fn (): bool => $request->student_type === StudentType::SeniorHighSchool->value),
                'nullable',
                'exists:shs_strands,id',
            ],
            'student_id' => $usesExternal ? ['exclude'] : [
                ...$identifierGenerator->getStudentIdValidationRules(
                    StudentType::tryFrom((string) $request->student_type),
                    ignoreId: $student->id,
                    schoolId: $student->school_id,
                ),
            ],
            'lrn' => [
                Rule::requiredIf(fn (): bool => $request->student_type === StudentType::SeniorHighSchool->value),
                'nullable',
                'string',
                'max:20',
                Rule::unique('students', 'lrn')->ignore($student->id),
            ],
            'remarks' => ['nullable', 'string'],

            // Additional info - Guardian Contact
            'personal_contact' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'facebook_contact' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],

            // Parent Info
            'fathers_name' => ['nullable', 'string', 'max:100'],
            'mothers_name' => ['nullable', 'string', 'max:100'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'father_contact' => ['nullable', 'string', 'max:30'],
            'father_email' => ['nullable', 'email', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'mother_contact' => ['nullable', 'string', 'max:30'],
            'mother_email' => ['nullable', 'email', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_contact' => ['nullable', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'family_address' => ['nullable', 'string', 'max:500'],

            // Education Info
            'elementary_school' => ['nullable', 'string', 'max:255'],
            'elementary_graduate_year' => ['nullable', 'string', 'max:4'],
            'elementary_school_address' => ['nullable', 'string', 'max:500'],
            'junior_high_school_name' => ['nullable', 'string', 'max:255'],
            'junior_high_graduation_year' => ['nullable', 'string', 'max:4'],
            'junior_high_school_address' => ['nullable', 'string', 'max:500'],
            'senior_high_name' => ['nullable', 'string', 'max:255'],
            'senior_high_graduate_year' => ['nullable', 'string', 'max:4'],
            'senior_high_address' => ['nullable', 'string', 'max:500'],
            'college_school' => ['nullable', 'string', 'max:255'],
            'college_course' => ['nullable', 'string', 'max:255'],
            'college_year_graduated' => ['nullable', 'string', 'max:4'],
            'vocational_school' => ['nullable', 'string', 'max:255'],
            'vocational_course' => ['nullable', 'string', 'max:255'],
            'vocational_year_graduated' => ['nullable', 'string', 'max:4'],

            // Address & Personal Info
            'current_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],

            // Statistical Data
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'city_of_origin' => ['nullable', 'string', 'max:100'],
            'province_of_origin' => ['nullable', 'string', 'max:100'],
            'region_of_origin' => ['nullable', 'string', 'max:50'],
            'is_indigenous_person' => ['nullable', 'boolean'],
            'indigenous_group' => ['nullable', 'string', 'max:100'],
            'is_pwd' => ['nullable', 'boolean'],
            'pwd_type' => ['nullable', 'string', 'max:100'],
            'is_solo_parent' => ['nullable', 'boolean'],
            'is_solo_parent_dependent' => ['nullable', 'boolean'],
            'is_senior_citizen' => ['nullable', 'boolean'],
            'is_magna_carta' => ['nullable', 'boolean'],
            'is_underprivileged' => ['nullable', 'boolean'],
            'is_first_generation' => ['nullable', 'boolean'],
            'income_bracket_mode' => ['nullable', 'string', 'max:50'],
            'use_same_parent_income' => ['nullable', 'boolean'],
            'family_income_bracket' => ['nullable', 'string', 'max:50'],
            'father_income_bracket' => ['nullable', 'string', 'max:50'],
            'mother_income_bracket' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(StudentStatus::class)],
            'withdrawal_date' => ['nullable', 'date'],
            'withdrawal_reason' => ['nullable', 'string'],
            'attrition_category' => ['nullable', Rule::enum(AttritionCategory::class)],
            'dropout_date' => ['nullable', 'date'],
            'scholarship_type' => ['nullable', Rule::enum(ScholarshipType::class)],
            'scholarship_details' => ['nullable', 'string'],
            'employment_status' => ['nullable', Rule::enum(EmploymentStatus::class)],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'job_position' => ['nullable', 'string', 'max:255'],
            'employment_date' => ['nullable', 'date'],
            'employed_by_institution' => ['nullable', 'boolean'],
            'stay' => ['nullable', 'boolean'],
            'picture_1x1' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'signature' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:5120'],
            'documents.picture_1x1' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'provision_portal_account' => ['nullable', 'boolean'],
            'send_activation_link' => ['nullable', 'boolean'],
        ];
        $validated = $request->validate([...$configuration->rules($rules, (string) $request->input('student_type'), $student), ...$configuration->customFieldRules($student)]);
        $validated = $configuration->preserveHidden($validated, $student);

        if (($validated['student_type'] ?? null) === StudentType::TESDA->value && isset($validated['course_id'])) {
            $course = Course::query()->with('department')->find($validated['course_id']);
            if ($course instanceof Course && ! $capabilityResolver->isTesdaCourse($course)) {
                return back()->withErrors(['course_id' => 'Select a TESDA qualification for a TESDA student.'])->withInput();
            }
        }

        if (($validated['student_type'] ?? null) === StudentType::College->value && isset($validated['course_id'])) {
            $course = Course::query()->with('department')->find($validated['course_id']);
            if ($course instanceof Course && ! $capabilityResolver->isCollegeCourse($course)) {
                return back()->withErrors(['course_id' => 'Select a college program for a college student.'])->withInput();
            }
        }

        DB::transaction(function () use ($validated, $student, $usesExternal, $configuration, $identifierGenerator): void {
            $identifierGenerator->sequenceFor(IdentifierGenerator::Student, lock: true, schoolId: $student->school_id);
            $configuration->forget($student->school_id);
            $student->refresh();
            $originalLrn = $student->lrn;
            $originalSchoolId = $student->school_id;
            $originalType = $student->student_type;
            \Illuminate\Support\Facades\Validator::make($validated, $configuration->customFieldRules($student))->validate();
            // Update main student record
            $student->student_type = $validated['student_type'];
            $configuration->mergeCustomValues($student, $validated);
            $student->first_name = $validated['first_name'];
            $student->last_name = $validated['last_name'];
            $student->middle_name = $validated['middle_name'] ?? null;
            $student->suffix = $validated['suffix'] ?? null;
            $student->gender = $validated['gender'];
            $student->birth_date = $validated['birth_date'];
            $student->email = $validated['email'] ?? null;
            $student->phone = $validated['phone'] ?? null;
            $student->civil_status = $validated['civil_status'] ?? null;
            $student->nationality = $validated['nationality'] ?? ($validated['citizenship'] ?? null);
            $student->religion = $validated['religion'] ?? null;
            if ($configuration->fieldIsVisible('current_address', $student->school_id) || $configuration->fieldIsVisible('permanent_address', $student->school_id)) {
                $student->address = ($validated['current_address'] ?? null) ?: ($validated['permanent_address'] ?? null);
            }
            $student->emergency_contact = $validated['emergency_contact_name'] ?? null;
            $student->academic_year = $validated['academic_year'];
            $student->remarks = $validated['remarks'] ?? null;
            $student->contacts = $this->studentContactsPayload($validated);

            // Statistical Data
            $student->ethnicity = $validated['ethnicity'] ?? null;
            $student->city_of_origin = $validated['city_of_origin'] ?? null;
            $student->province_of_origin = $validated['province_of_origin'] ?? null;
            $student->region_of_origin = $validated['region_of_origin'] ?? null;
            $student->is_indigenous_person = $validated['is_indigenous_person'] ?? false;
            $student->indigenous_group = $validated['indigenous_group'] ?? null;
            $student->is_pwd = $validated['is_pwd'] ?? false;
            $student->pwd_type = $validated['pwd_type'] ?? null;
            $student->is_solo_parent = $validated['is_solo_parent'] ?? false;
            if (
                $this->hasColumn('students', 'is_solo_parent_dependent')
                && array_key_exists('is_solo_parent_dependent', $validated)
                && $validated['is_solo_parent_dependent'] !== null
            ) {
                $student->is_solo_parent_dependent = (bool) $validated['is_solo_parent_dependent'];
            }
            $student->is_senior_citizen = $validated['is_senior_citizen'] ?? false;
            $student->is_magna_carta = $validated['is_magna_carta'] ?? false;
            $student->is_underprivileged = $validated['is_underprivileged'] ?? false;
            $student->is_first_generation = $validated['is_first_generation'] ?? false;
            $usesSameParentIncome = $validated['use_same_parent_income'] ?? true;
            $student->income_bracket_mode = $usesSameParentIncome ? 'annual' : ($validated['income_bracket_mode'] ?? $student->income_bracket_mode ?? (string) config('income_brackets.default_mode', 'annual'));
            $student->use_same_parent_income = $usesSameParentIncome;
            $student->family_income_bracket = $usesSameParentIncome ? ($validated['family_income_bracket'] ?? null) : null;
            $student->father_income_bracket = $usesSameParentIncome ? null : ($validated['father_income_bracket'] ?? null);
            $student->mother_income_bracket = $usesSameParentIncome ? null : ($validated['mother_income_bracket'] ?? null);
            $student->status = $validated['status'] ?? 'enrolled';
            $student->withdrawal_date = $validated['withdrawal_date'] ?? null;
            $student->withdrawal_reason = $validated['withdrawal_reason'] ?? null;
            $student->attrition_category = $validated['attrition_category'] ?? null;
            $student->dropout_date = $validated['dropout_date'] ?? null;
            $student->scholarship_type = $validated['scholarship_type'] ?? null;
            $student->scholarship_details = $validated['scholarship_details'] ?? null;
            $student->employment_status = $validated['employment_status'] ?? null;
            $student->employer_name = $validated['employer_name'] ?? null;
            $student->job_position = $validated['job_position'] ?? null;
            $student->employment_date = $validated['employment_date'] ?? null;
            $student->employed_by_institution = $validated['employed_by_institution'] ?? false;

            // Handle Type Specifics
            $student->lrn = array_key_exists('lrn', $validated) ? $validated['lrn'] : $student->lrn;
            $student->student_id = $usesExternal ? $validated['lrn'] : $validated['student_id'];
            if ($validated['student_type'] === StudentType::SeniorHighSchool->value) {
                $student->shs_strand_id = $validated['shs_strand_id'];
                $student->course_id = null;
            } else {
                $student->course_id = $validated['course_id'];
                $student->shs_strand_id = null;
            }

            // Calculate Age
            $student->age = Carbon::parse($validated['birth_date'])->age;

            // Sync relations first so the related-record ids are set on the model
            // before the single save below.
            $this->syncStudentRelations($student, $validated);
            $student->save();

            // Reconcile the SHS roster row keyed by the previous LRN when an optional LRN
            // is cleared, replaced, or when the student leaves SHS while changing/clearing LRN.
            if (filled($originalLrn) && Schema::hasTable('shs_students')) {
                $lrnCleared = blank($student->lrn);
                $lrnChanged = ! $lrnCleared && (string) $student->lrn !== (string) $originalLrn;
                $leftShs = $originalType === StudentType::SeniorHighSchool
                    && $student->student_type !== StudentType::SeniorHighSchool;

                if ($lrnCleared || $lrnChanged || $leftShs) {
                    ShsStudent::query()
                        ->where('student_lrn', $originalLrn)
                        ->when(
                            $originalSchoolId !== null,
                            fn (Builder $query) => $query->where('school_id', $originalSchoolId),
                            fn (Builder $query) => $query->whereNull('school_id'),
                        )
                        ->delete();
                }
            }

            if (isset($validated['status'])) {
                $this->syncCurrentStudentStatus($student, StudentStatus::from($validated['status']));
            }
        });

        $assetResult = $this->persistStudentAssets($request, $student);
        $failedDocumentSlots = $assetResult['failed_slots'];

        if ($failedDocumentSlots !== []) {
            $failedLabels = implode(', ', array_keys($failedDocumentSlots));
            $errorBag = [];
            foreach ($failedDocumentSlots as $slotKey => $slotError) {
                $fieldKey = in_array($slotKey, ['picture_1x1', 'signature'], true)
                    ? $slotKey
                    : "documents.{$slotKey}";
                $errorBag[$fieldKey] = $slotError;
            }

            return redirect()->route('administrators.students.edit', $student)
                ->with('success', 'Student updated successfully.')
                ->with('warning', "Some document uploads failed ({$failedLabels}). Already uploaded files were preserved — retry only the failed slot(s) below.")
                ->with('failed_document_slots', array_keys($failedDocumentSlots))
                ->withErrors($errorBag);
        }

        $message = 'Student updated successfully.';

        if ($request->boolean('stay', false)) {
            return redirect()->route('administrators.students.edit', $student)
                ->with('success', $message);
        }

        return redirect()->route('administrators.students.show', $student)
            ->with('success', $message);
    }

    public function addSubject(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subject,id'],
        ]);

        $settings = app(GeneralSettingsService::class);
        $subject = Subject::findOrFail($validated['subject_id']);

        // Check if already enrolled
        $exists = SubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where('school_year', $settings->getCurrentSchoolYearString())
            ->where('semester', $settings->getCurrentSemester())
            ->exists();

        if ($exists) {
            return back()->with('error', 'Student is already enrolled in this subject.');
        }

        $enrollment = StudentEnrollment::firstOrCreate([
            'student_id' => $student->id,
            'school_year' => $settings->getCurrentSchoolYearString(),
            'semester' => $settings->getCurrentSemester(),
        ], [
            'status' => 'enrolled',
            'academic_year' => $student->academic_year,
            'course_id' => $student->course_id,
        ]);

        SubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'enrollment_id' => $enrollment->id,
            'school_year' => $settings->getCurrentSchoolYearString(),
            'semester' => $settings->getCurrentSemester(),
            'academic_year' => $student->academic_year, // Default to student's current year
            'grade' => null,
            'remarks' => null,
        ]);

        return back()->with('success', 'Subject added successfully.');
    }

    public function updateSubjectGrade(Request $request, Student $student, Subject $subject, GradingSystemService $gradingSystem): RedirectResponse
    {
        $validated = $request->validate([
            'enrollment_record_id' => ['nullable', 'integer'],
            'is_new_record' => ['nullable', 'boolean'],
            'grade' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_scalar($value)) {
                        $fail('The grade must be a valid number or symbol.');
                    } elseif (mb_strlen((string) $value) > 32) {
                        $fail('The grade must not exceed 32 characters.');
                    }
                },
            ],
            'remarks' => ['nullable', 'string'],
            'classification' => ['required', 'string', 'in:'.implode(',', array_column(SubjectEnrolledEnum::cases(), 'value'))],
            'school_name' => ['nullable', 'string', 'required_if:classification,credited,non_credited'],
            'external_subject_code' => ['nullable', 'string', 'required_if:classification,credited,non_credited'],
            'external_subject_title' => ['nullable', 'string', 'required_if:classification,credited,non_credited'],
            'external_subject_units' => ['nullable', 'numeric', 'required_if:classification,credited,non_credited'],
            'credited_subject_id' => ['nullable', 'exists:subject,id', 'required_if:classification,credited'],
            'academic_year' => ['required', 'integer'],
            'school_year' => ['required', 'string'],
            'semester' => ['required', 'integer'],
        ]);

        app(GeneralSettingsService::class);

        $enrollmentRecordId = $validated['enrollment_record_id'] ?? null;
        $isNewRecord = $validated['is_new_record'] ?? false;

        if ($enrollmentRecordId && ! $isNewRecord) {
            $subjectEnrollment = SubjectEnrollment::where('student_id', $student->id)
                ->where('id', $enrollmentRecordId)
                ->where(function ($query) use ($subject): void {
                    $query->where('subject_id', $subject->id)
                        ->orWhere('classification', SubjectEnrolledEnum::NON_CREDITED->value);
                })
                ->first();

            abort_if(! $subjectEnrollment, 404, 'Subject enrollment not found or does not belong to this subject.');
        } elseif (! $isNewRecord) {
            // Find latest existing enrollment
            $subjectEnrollment = SubjectEnrollment::where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->latest()
                ->first();
        } else {
            $subjectEnrollment = null;
        }

        $gradingConfig = $gradingSystem->getConfig($student->school);

        if (array_key_exists('grade', $validated) && $validated['grade'] !== null && $validated['grade'] !== '' && $validated['grade'] !== '-') {
            $gradeInput = (string) $validated['grade'];
            $normalizedSymbol = mb_strtoupper(mb_trim($gradeInput));
            $recognizedSpecial = in_array($normalizedSymbol, ['DROP', 'DROPPED', 'DRP', 'W', 'WITHDRAWN', 'INC', 'INCOMPLETE'], true);

            if (! $recognizedSpecial) {
                if (($gradingConfig['input_type'] ?? 'numeric') === 'numeric') {
                    if (! is_numeric($gradeInput)) {
                        throw ValidationException::withMessages([
                            'grade' => "Grade must be a number between {$gradingConfig['numeric_min']} and {$gradingConfig['numeric_max']} or a recognized status.",
                        ]);
                    }
                    $numericVal = (float) $gradeInput;
                    $isTransfereeClassification = in_array($validated['classification'] ?? '', [
                        SubjectEnrolledEnum::CREDITED->value,
                        SubjectEnrolledEnum::NON_CREDITED->value,
                    ], true);

                    $isTransfereeScale = $isTransfereeClassification
                        && (bool) ($gradingConfig['transferee_scale_enabled'] ?? true)
                        && $numericVal >= (float) ($gradingConfig['transferee_point_scale_min'] ?? 1.0)
                        && $numericVal <= (float) ($gradingConfig['transferee_point_scale_max'] ?? 5.0);

                    $isWithinPolicyBounds = $numericVal >= (float) $gradingConfig['numeric_min']
                        && $numericVal <= (float) $gradingConfig['numeric_max'];

                    if (! $isWithinPolicyBounds && ! $isTransfereeScale) {
                        throw ValidationException::withMessages([
                            'grade' => "Grade must be between {$gradingConfig['numeric_min']} and {$gradingConfig['numeric_max']}.",
                        ]);
                    }
                } else {
                    $validSymbols = collect($gradingConfig['bands'] ?? [])
                        ->pluck('symbol')
                        ->filter()
                        ->map(fn ($s): string => mb_strtoupper(mb_trim((string) $s)))
                        ->all();

                    if (! in_array($normalizedSymbol, $validSymbols, true)) {
                        throw ValidationException::withMessages([
                            'grade' => 'Grade must be one of the configured symbols: '.implode(', ', $validSymbols).'.',
                        ]);
                    }
                }
            }
        }

        $rawGrade = $validated['grade'] ?? null;
        if ($rawGrade === '' || $rawGrade === '-') {
            $rawGrade = null;
        }

        $evaluation = app(GradeEvaluationService::class)->evaluate($rawGrade, $gradingConfig, [
            'classification' => $validated['classification'],
        ]);

        $data = [
            'grade' => $evaluation['numeric_grade'],
            'grade_symbol' => $evaluation['symbol'],
            'grade_outcome' => $evaluation['outcome'],
            'grade_quality_points' => $evaluation['quality_points'],
            'grading_policy_version_id' => $gradingConfig['policy_version_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'classification' => $validated['classification'],
            'academic_year' => $validated['academic_year'],
            'school_year' => $validated['school_year'],
            'semester' => $validated['semester'],
        ];

        if ($validated['classification'] === SubjectEnrolledEnum::INTERNAL->value) {
            $data['school_name'] = null;
            $data['external_subject_code'] = null;
            $data['external_subject_title'] = null;
            $data['external_subject_units'] = null;
            $data['credited_subject_id'] = null;
        } else {
            $data['school_name'] = $validated['school_name'];
            $data['external_subject_code'] = $validated['external_subject_code'];
            $data['external_subject_title'] = $validated['external_subject_title'];
            $data['external_subject_units'] = $validated['external_subject_units'];

            if ($validated['classification'] === SubjectEnrolledEnum::CREDITED->value) {
                $data['credited_subject_id'] = $validated['credited_subject_id'];
            } else {
                $data['credited_subject_id'] = null;
            }
        }

        if ($subjectEnrollment) {
            if ($validated['classification'] === SubjectEnrolledEnum::NON_CREDITED->value) {
                $data['subject_id'] = null;
            }

            $subjectEnrollment->update($data);
        } else {
            // Create new enrollment if it doesn't exist
            // We need a parent StudentEnrollment
            // Note: We use the *selected* school year and semester for the StudentEnrollment if we are creating a new one?
            // Or do we adhere to "Current" settings?
            // Usually, StudentEnrollment is per term. If we are backlogging a grade from 2020, we should probably find/create the enrollment for 2020.
            // But previous code used current. Let's try to match the SubjectEnrollment's school_year/semester.

            $enrollment = StudentEnrollment::firstOrCreate([
                'student_id' => $student->id,
                'school_year' => $validated['school_year'],
                'semester' => $validated['semester'],
            ], [
                'status' => 'enrolled', // or 'completed' if grade exists? Keep 'enrolled' as generic status
                'academic_year' => $validated['academic_year'],
                'course_id' => $student->course_id,
            ]);

            $data['student_id'] = $student->id;
            $data['subject_id'] = $validated['classification'] === SubjectEnrolledEnum::NON_CREDITED->value ? null : $subject->id;
            $data['enrollment_id'] = $enrollment->id;

            SubjectEnrollment::create($data);
        }

        return back()->with('success', 'Subject updated successfully.');
    }

    public function linkAccount(Student $student): RedirectResponse
    {
        if (! $student->email) {
            return back()->with('error', 'This student does not have an email address.');
        }

        $normalizedEmail = mb_strtolower(mb_trim((string) $student->email));
        $portalUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($portalUser instanceof User) {
            if ($portalUser->canAccessAdminPortal() || $portalUser->isFaculty()) {
                return back()->with('error', 'This email belongs to a staff or faculty account and cannot be linked to a student record.');
            }

            if (filled($portalUser->record_id) && (string) $portalUser->record_id !== (string) $student->id) {
                return back()->with('error', 'This portal account is already linked to a different student record.');
            }

            $role = $student->student_type === StudentType::SeniorHighSchool
                ? UserRole::ShsStudent
                : UserRole::Student;

            DB::transaction(function () use ($portalUser, $student, $role): void {
                app(UserSystemRoleService::class)->assign($portalUser, $role);
                app(StudentOrganizationAssignmentService::class)->assign($portalUser, $student);
            });

            return back()->with('success', 'Portal user account linked successfully.');
        }

        $account = Account::where('email', $student->email)->first();

        if (! $account) {
            return back()->with('error', 'No account found with email: '.$student->email);
        }

        try {
            $account->update([
                'role' => 'student',
                'person_id' => $student->id,
                'person_type' => Student::class,
            ]);

            return back()->with('success', 'Account linked successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Error linking account: '.$e->getMessage());
        }
    }

    /**
     * Create, link, or repair the modern User portal account for a student record.
     */
    public function managePortalAccount(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:create,repair,auto'],
            'send_reset_link' => ['nullable', 'boolean'],
        ]);

        if (blank($student->email)) {
            $message = 'Add an email address to this student record before provisioning a portal account.';

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $normalizedEmail = mb_strtolower(mb_trim((string) $student->email));
        $mode = (string) ($validated['mode'] ?? 'auto');
        $portalUser = $student->user_id !== null
            ? User::query()->find($student->user_id)
            : User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if (! $portalUser && $mode === 'repair') {
            $message = 'No portal user exists for this student email. Create a portal account instead.';

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if ($portalUser instanceof User) {
            if ($portalUser->canAccessAdminPortal() || $portalUser->isFaculty()) {
                $message = 'This email belongs to a staff or faculty portal account and cannot be overwritten for a student.';

                if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                    return response()->json(['message' => $message], 422);
                }

                return back()->with('error', $message);
            }

            if (filled($portalUser->record_id) && (string) $portalUser->record_id !== (string) $student->id) {
                $message = 'This portal account is already bound to a different student record.';

                if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                    return response()->json(['message' => $message], 422);
                }

                return back()->with('error', $message);
            }
        }

        $targetRole = $student->student_type === StudentType::SeniorHighSchool
            ? UserRole::ShsStudent
            : UserRole::Student;

        DB::transaction(function () use (&$portalUser, $student, $normalizedEmail, $targetRole): void {
            if (! $portalUser) {
                $portalUser = User::create([
                    'name' => $student->full_name,
                    'email' => $normalizedEmail,
                    'password' => Hash::make(Str::random(32)),
                    'role' => $targetRole->value,
                    'school_id' => $student->school_id,
                    'record_id' => $student->id,
                    'email_verified_at' => now(),
                ]);
            } else {
                $portalUser->forceFill([
                    'name' => $portalUser->name ?: $student->full_name,
                    'school_id' => $student->school_id ?? $portalUser->school_id,
                    'record_id' => $student->id,
                ])->save();
            }

            app(UserSystemRoleService::class)->assign($portalUser, $targetRole);

            if ($student->school_id !== null) {
                app(StudentOrganizationAssignmentService::class)->assign($portalUser, $student);
            } else {
                $student->forceFill(['user_id' => $portalUser->id])->save();
            }
        });

        $resetSent = false;
        if ((bool) ($validated['send_reset_link'] ?? false)) {
            $token = Password::broker()->createToken($portalUser);
            $portalUser->notify(new PortalAccountWelcomeNotification(
                token: $token,
                identifier: filled($student->student_id) ? (string) $student->student_id : null,
                identifierLabel: 'Student Number',
                portalLabel: 'Student Portal',
            ));
            $resetSent = true;
        }

        $message = $resetSent
            ? 'Student portal account is ready and an activation reset link has been sent.'
            : 'Student portal account is ready and linked.';

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $message,
                'portal_account' => [
                    'id' => $portalUser->id,
                    'name' => $portalUser->name,
                    'email' => $portalUser->email,
                    'is_linked' => true,
                    'email_verified' => $portalUser->email_verified_at !== null,
                    'suspended' => $portalUser->suspended_at !== null,
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Inline walk-in student quick-creation used inside the Precision Ledger enrollment workbench.
     */
    public function quickStore(Request $request, CurriculumCapabilityResolver $capabilityResolver): JsonResponse
    {
        $this->authorizeStudentAccess('Create:Student');

        $identifierGenerator = app(IdentifierGenerator::class);
        $configuration = app(StudentConfigurationService::class);
        $schoolId = app(TenantContext::class)->getCurrentSchoolId();
        $config = $configuration->get($schoolId);
        $typeValue = (string) $request->input('student_type', $config['default_type'] ?? StudentType::College->value);
        $usesExternal = $configuration->usesExternal($typeValue, $schoolId);
        $policy = $identifierGenerator->studentPolicy(StudentType::tryFrom($typeValue), $schoolId);
        $defaultMode = ($policy['assignment_mode'] ?? 'automatic') === 'manual' ? 'manual' : ($request->filled('student_id') ? 'manual' : 'automatic');
        $mode = (string) $request->input('identifier_assignment', $defaultMode);

        $sanitizedNames = [];
        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if ($request->filled($field)) {
                $sanitizedNames[$field] = strip_tags((string) $request->input($field));
            }
        }
        if ($sanitizedNames !== []) {
            $request->merge($sanitizedNames);
        }

        $rules = [
            'identifier_assignment' => ['nullable', 'in:automatic,manual'],
            'student_type' => ['required', Rule::enum(StudentType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female,other,prefer_not_to_say'],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'course_id' => [
                Rule::requiredIf(fn (): bool => $typeValue !== StudentType::SeniorHighSchool->value),
                'nullable',
                'exists:courses,id',
            ],
            'shs_strand_id' => [
                Rule::requiredIf(fn (): bool => $typeValue === StudentType::SeniorHighSchool->value),
                'nullable',
                'exists:shs_strands,id',
            ],
            'academic_year' => ['required', 'integer'],
            'student_id' => $usesExternal || $mode === 'automatic'
                ? ['exclude']
                : $identifierGenerator->getStudentIdValidationRules(StudentType::tryFrom($typeValue), schoolId: $schoolId),
            'lrn' => [
                Rule::requiredIf(fn (): bool => $typeValue === StudentType::SeniorHighSchool->value || $usesExternal),
                'nullable',
                'string',
                'max:20',
                Rule::unique('students', 'lrn')->where('school_id', $schoolId),
            ],
            'status' => ['nullable', Rule::enum(StudentStatus::class)],
            'provision_portal_account' => ['nullable', 'boolean'],
            'send_activation_link' => ['nullable', 'boolean'],
            'allow_duplicate' => ['nullable', 'boolean'],
        ];

        $configuration->assertRequiredFieldsSupported(['email', 'phone'], $schoolId);

        $validated = $request->validate($configuration->rules($rules, $typeValue, schoolId: $schoolId));

        // Fuzzy duplicate detection on name + birth_date or email before creating a new walk-in record
        if (! (bool) ($validated['allow_duplicate'] ?? false)) {
            $birthDateString = Carbon::parse($validated['birth_date'])->toDateString();
            $duplicates = Student::query()
                ->with('Course:id,code,title')
                ->where(function (Builder $query) use ($validated, $birthDateString): void {
                    $query->where(function (Builder $nameQuery) use ($validated, $birthDateString): void {
                        $nameQuery->whereRaw('LOWER(first_name) = ?', [mb_strtolower(mb_trim($validated['first_name']))])
                            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(mb_trim($validated['last_name']))])
                            ->whereDate('birth_date', $birthDateString);
                    });

                    if (filled($validated['email'] ?? null)) {
                        $query->orWhereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim((string) $validated['email']))]);
                    }
                })
                ->limit(5)
                ->get();

            if ($duplicates->isNotEmpty()) {
                return response()->json([
                    'message' => 'A matching student record already exists. Select the existing student or confirm creating a separate record.',
                    'duplicates' => $duplicates->map(fn (Student $dup): array => [
                        'id' => $dup->id,
                        'student_id' => $dup->student_id,
                        'full_name' => $dup->full_name,
                        'email' => $dup->email,
                        'course_id' => $dup->course_id,
                        'course_code' => $dup->Course?->code,
                        'course_title' => $dup->Course?->title,
                        'academic_year' => $dup->academic_year,
                        'formatted_academic_year' => $dup->formatted_academic_year,
                        'label' => sprintf(
                            '%s - %s, %s | %s | %s',
                            (string) $dup->student_id,
                            $dup->last_name,
                            $dup->first_name,
                            $dup->Course?->code ?? 'No Course',
                            $dup->formatted_academic_year ?? 'No Year'
                        ),
                    ])->values(),
                ], 409);
            }
        }

        $student = DB::transaction(function () use ($identifierGenerator, $configuration, $validated, $usesExternal, $schoolId, $mode): Student {
            $identifierGenerator->sequenceFor(IdentifierGenerator::Student, lock: true, schoolId: $schoolId);
            $configuration->forget($schoolId);
            $configuration->assertRequiredFieldsSupported(['email', 'phone'], $schoolId);
            $studentType = StudentType::from($validated['student_type']);
            $status = StudentStatus::tryFrom((string) ($validated['status'] ?? StudentStatus::Applicant->value)) ?? StudentStatus::Applicant;
            $birthDate = Carbon::parse($validated['birth_date']);

            $student = new Student;
            $student->fill([
                'student_type' => $studentType->value,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'gender' => $validated['gender'],
                'birth_date' => $birthDate->toDateString(),
                'age' => $birthDate->age,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'academic_year' => (int) $validated['academic_year'],
                'status' => $status->value,
                'scholarship_type' => ScholarshipType::None->value,
                'employment_status' => EmploymentStatus::NotApplicable->value,
            ]);

            $student->lrn = $validated['lrn'] ?? null;
            $student->student_id = $usesExternal
                ? $identifierGenerator->claimExternalIdentifier($studentType, (string) $validated['lrn'], $schoolId)
                : $identifierGenerator->assign($studentType, $validated['student_id'] ?? null, $mode, $schoolId);

            if ($studentType === StudentType::SeniorHighSchool) {
                $student->shs_strand_id = $validated['shs_strand_id'];
            } else {
                $student->course_id = $validated['course_id'];
            }

            $student->save();
            $this->syncCurrentStudentStatus($student, $status);

            return $student;
        });

        $portalProvisioning = [
            'requested' => (bool) ($validated['provision_portal_account'] ?? false) && filled($student->email),
            'provisioned' => false,
            'status' => 'skipped',
            'error' => null,
        ];

        if ($portalProvisioning['requested']) {
            $portalRequest = Request::create('', 'POST', [
                'mode' => 'auto',
                'send_reset_link' => (bool) ($validated['send_activation_link'] ?? true),
            ]);
            $portalRequest->headers->set('Accept', 'application/json');

            try {
                $portalResponse = $this->managePortalAccount($portalRequest, $student);
                if ($portalResponse instanceof JsonResponse && $portalResponse->getStatusCode() >= 400) {
                    $payload = $portalResponse->getData(true);
                    $portalProvisioning['status'] = 'failed';
                    $portalProvisioning['error'] = is_array($payload) && is_string($payload['message'] ?? null)
                        ? $payload['message']
                        : 'Portal account could not be provisioned for this email address.';
                } else {
                    $portalProvisioning['provisioned'] = true;
                    $portalProvisioning['status'] = 'provisioned';
                }
            } catch (Throwable $e) {
                Log::warning('Failed to provision student portal account during quick intake.', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
                $portalProvisioning['status'] = 'failed';
                $portalProvisioning['error'] = 'Portal account provisioning failed. Update the email and retry from the student record.';
            }

            $student->refresh();
        }

        $student->load('Course');

        $responseMessage = $portalProvisioning['status'] === 'failed'
            ? 'Student created successfully, but portal account provisioning was refused: '.$portalProvisioning['error']
            : 'Student created successfully.';

        return response()->json([
            'message' => $responseMessage,
            'warning' => $portalProvisioning['error'],
            'portal_provisioning' => $portalProvisioning,
            'edit_url' => route('administrators.students.edit', $student),
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'course_id' => $student->course_id,
                'course_code' => $student->Course?->code,
                'course_title' => $student->Course?->title,
                'curriculum_kind' => $student->Course ? $capabilityResolver->kindForCourse($student->Course) : null,
                'qualification_level' => $student->Course?->qualification_level,
                'tesda_program_type' => $student->Course?->tesda_program_type,
                'duration_hours' => $student->Course?->duration_hours,
                'duration_years' => $student->Course?->duration_years,
                'internship_hours' => $student->Course?->internship_hours,
                'bundled_qualifications' => $student->Course?->bundled_qualifications,
                'academic_year' => $student->academic_year,
                'formatted_academic_year' => $student->formatted_academic_year,
                'label' => sprintf(
                    '%s - %s, %s | %s | %s',
                    (string) $student->student_id,
                    $student->last_name,
                    $student->first_name,
                    $student->Course?->code ?? 'No Course',
                    $student->formatted_academic_year ?? 'No Year'
                ),
            ],
        ], 201);
    }

    /**
     * Bulk provision portal accounts for selected students in the Cohort Command Deck.
     */
    public function bulkProvisionPortalAccounts(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'exists:students,id'],
            'send_reset_link' => ['nullable', 'boolean'],
        ]);

        $sendResetLink = (bool) ($validated['send_reset_link'] ?? true);
        $students = Student::query()->whereIn('id', $validated['ids'])->get();

        $provisioned = 0;
        $skipped = 0;

        foreach ($students as $student) {
            if (blank($student->email)) {
                $skipped++;

                continue;
            }

            try {
                $innerRequest = Request::create('', 'POST', [
                    'mode' => 'auto',
                    'send_reset_link' => $sendResetLink,
                ]);
                $innerRequest->headers->set('Accept', 'application/json');
                $response = $this->managePortalAccount($innerRequest, $student);
                if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
                    $skipped++;

                    continue;
                }
                $provisioned++;
            } catch (Exception) {
                $skipped++;
            }
        }

        $message = sprintf(
            'Provisioned portal accounts for %d student%s%s.',
            $provisioned,
            $provisioned === 1 ? '' : 's',
            $skipped > 0 ? sprintf(' (%d skipped: missing/conflicting email)', $skipped) : '',
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'provisioned' => $provisioned,
                'skipped' => $skipped,
                'message' => $message,
            ]);
        }

        return back()->with($provisioned > 0 ? 'success' : 'error', $message);
    }

    public function updateStudentId(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'new_student_id' => app(IdentifierGenerator::class)->getStudentIdValidationRules($student->student_type, $student->id, schoolId: $student->school_id),
            'confirm_operation' => ['required', 'accepted'],
        ]);

        $newId = (string) $validated['new_student_id'];

        if ($newId === $student->student_id) {
            return back()->with('error', 'New ID cannot be the same as current ID.');
        }

        $service = app(StudentIdUpdateService::class);
        $result = $service->updateStudentId($student, $newId, true);

        if ($result['success']) {
            return back()->with('success', 'Student ID updated successfully.');
        }

        return back()->with('error', 'Failed to update ID: '.$result['message']);
    }

    public function undoStudentIdChange(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'change_log_id' => ['required', 'integer'],
            'confirm_undo' => ['required', 'accepted'],
        ]);

        $service = app(StudentIdUpdateService::class);
        $result = $service->undoStudentIdChange($validated['change_log_id'], $student);

        if ($result['success']) {
            return back()->with('success', "Student ID reverted from {$result['old_id']} to {$result['new_id']}.");
        }

        return back()->with('error', 'Failed to undo ID change: '.$result['message']);
    }

    public function getCourseSubjects(Course $course): JsonResponse
    {
        $subjects = $course->subjects()
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->get()
            ->map(fn ($subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
                'year' => $subject->academic_year,
                'semester' => $subject->semester,
            ]);

        // Group by Year and Semester
        $grouped = $subjects->groupBy('year')->map(fn ($yearSubjects, $year): array => [
            'year' => $year,
            'semesters' => $yearSubjects->groupBy('semester')->map(fn ($semSubjects, $sem): array => [
                'semester' => $sem,
                'subjects' => $semSubjects->values(),
            ])->values(),
        ])->values();

        return response()->json($grouped);
    }

    public function changeCourse(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudentAccess('Update:Student');

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'credits' => ['nullable', 'array'],
            'credits.*.source_subject_id' => ['required', 'exists:subject,id'],
            'credits.*.target_subject_id' => ['required', 'exists:subject,id'],
        ]);

        DB::transaction(function () use ($student, $validated): void {
            // 1. Update Student Course
            $student->update(['course_id' => $validated['course_id']]);

            // 2. Create StudentEnrollment for the new course
            $settings = app(GeneralSettingsService::class);
            $currentSY = $settings->getCurrentSchoolYearString();
            $currentSem = $settings->getCurrentSemester();

            $enrollment = StudentEnrollment::firstOrCreate([
                'student_id' => $student->id,
                'school_year' => $currentSY,
                'semester' => $currentSem,
                'course_id' => $validated['course_id'],
            ], [
                'status' => 'enrolled',
                'academic_year' => $student->academic_year,
            ]);

            // 3. Process Credits
            if (! empty($validated['credits'])) {
                foreach ($validated['credits'] as $credit) {
                    $sourceSubjectId = $credit['source_subject_id'];
                    $targetSubjectId = $credit['target_subject_id'];

                    // Get source enrollment to copy grade
                    $sourceEnrollment = SubjectEnrollment::where('student_id', $student->id)
                        ->where('subject_id', $sourceSubjectId)
                        ->whereNotNull('grade')
                        ->latest()
                        ->first();

                    if ($sourceEnrollment) {
                        // Check if already enrolled in target
                        $exists = SubjectEnrollment::where('student_id', $student->id)
                            ->where('subject_id', $targetSubjectId)
                            ->exists();

                        if (! $exists) {
                            SubjectEnrollment::create([
                                'student_id' => $student->id,
                                'subject_id' => $targetSubjectId,
                                'enrollment_id' => $enrollment->id,
                                'school_year' => $currentSY,
                                'semester' => $currentSem,
                                'academic_year' => $student->academic_year,
                                'grade' => $sourceEnrollment->grade,
                                'classification' => 'credited',
                                'remarks' => 'Credited from '.($sourceEnrollment->subject->code ?? 'Previous Course'),
                                'is_credited' => true,
                                'credited_subject_id' => $sourceSubjectId,
                                'school_name' => 'Internal Shift',
                            ]);
                        }
                    }
                }
            }
        });

        return back()->with('success', 'Course updated and subjects credited successfully.');
    }

    public function retryClassEnrollment(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'force_enrollment' => ['boolean'],
            'enrollment_id' => ['nullable', 'integer'],
        ]);

        $force = $validated['force_enrollment'] ?? false;

        if ($force) {
            config(['enrollment.force_enroll_when_full' => true]);
        }

        try {
            $student->autoEnrollInClasses($validated['enrollment_id'] ?? null);

            return back()->with('success', 'Enrollment retry completed.');
        } catch (Exception $e) {
            return back()->with('error', 'Enrollment retry failed: '.$e->getMessage());
        }
    }

    public function updateTuition(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_tuition_fees'), 403);

        return redirect()->route('administrators.finance.tuition-adjustments.index', ['student' => $student->id]);
    }

    public function showSignature(Student $student): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $path = $student->signature_path;
        if (! is_string($path) || mb_trim($path) === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        $privateDisk = (string) config('filesystems.private_disk', 'private');
        $defaultDisk = config('filesystems.default');

        $disk = null;
        if (Storage::disk($privateDisk)->exists($path)) {
            $disk = $privateDisk;
        } elseif (is_string($defaultDisk) && $defaultDisk !== $privateDisk && Storage::disk($defaultDisk)->exists($path)) {
            $disk = $defaultDisk;
        }

        abort_if($disk === null, 404);

        return Storage::disk($disk)->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateSignature(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'file', 'mimetypes:image/png', 'max:2048'],
        ]);

        /** @var UploadedFile $signature */
        $signature = $validated['signature'];

        $privateDisk = (string) config('filesystems.private_disk', 'private');
        $newPath = $signature->store("students/{$student->id}/signatures", $privateDisk);

        if (! is_string($newPath) || $newPath === '') {
            return back()->with('error', 'Failed to store signature.');
        }

        $this->deleteStoredSignature($student->signature_path);

        $student->update([
            'signature_path' => $newPath,
        ]);

        $student->refresh();

        return back()->with([
            'success' => 'Student signature saved successfully.',
            'signature_url' => $this->resolveStudentSignatureUrl($student),
        ]);
    }

    public function destroySignature(Request $request, Student $student): RedirectResponse
    {
        $this->deleteStoredSignature($student->signature_path);

        $student->update([
            'signature_path' => null,
        ]);

        $student->refresh();

        return back()->with([
            'success' => 'Student signature removed successfully.',
            'signature_url' => null,
        ]);
    }

    public function manageClearance(Request $request, Student $student): RedirectResponse
    {
        abort(410, 'Enrollment clearance has been retired. Use grade-release clearance on the student page.');
    }

    /**
     * Lightweight patch endpoint for high-frequency single-field edits
     * (currently year level and status) performed inline on the show page.
     *
     * Deliberately avoids the full form validation, the four related-record
     * syncs, and any page-level prop rebuild so the round trip stays instant.
     * Responds with JSON so the client can reconcile optimistic state.
     */
    public function quickUpdate(Request $request, Student $student): JsonResponse
    {
        $configuration = app(StudentConfigurationService::class)->get($student->school_id);
        $levels = array_column($configuration['year_levels'][$student->student_type->value] ?? [], 'value');
        $levels[] = $student->academic_year;
        $validated = $request->validate([
            'academic_year' => ['sometimes', 'required', 'integer', Rule::in($levels)],
            'status' => ['sometimes', 'required', new RulesEnum(StudentStatus::class)],
        ]);

        if ($validated === []) {
            return response()->json([
                'message' => 'No supported fields were provided.',
                'errors' => [],
            ], 422);
        }

        $student->fill($validated);

        if ($student->isDirty()) {
            DB::transaction(function () use ($student, $validated): void {
                $student->save();

                if (isset($validated['status'])) {
                    $this->syncCurrentStudentStatus($student, StudentStatus::from($validated['status']));
                }
            });
        }

        return response()->json([
            'message' => 'Student updated successfully.',
            'student' => [
                'id' => $student->id,
                'academic_year' => $student->academic_year,
                'formatted_academic_year' => $student->formatted_academic_year,
                'status' => $student->status,
            ],
        ]);
    }

    public function updateStatus(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new RulesEnum(StudentStatus::class)],
            'graduation_school_year' => [
                'nullable',
                'string',
                'regex:/^\\d{4} - \\d{4}$/',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    if ($request->input('status') !== StudentStatus::Graduated->value || ! is_string($value) || ! preg_match('/^(\\d{4}) - (\\d{4})$/', $value, $matches)) {
                        return;
                    }

                    if ((int) $matches[2] !== (int) $matches[1] + 1) {
                        $fail('The graduation academic year must span consecutive years.');
                    }
                },
            ],
            'graduation_semester' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $studentUpdate = ['status' => $validated['status']];
        if ($validated['status'] === StudentStatus::Graduated->value) {
            $studentUpdate['graduation_school_year'] = $validated['graduation_school_year'] ?? null;
            $studentUpdate['graduation_semester'] = $validated['graduation_semester'] ?? null;
        }

        DB::transaction(function () use ($student, $currentSchoolYear, $currentSemester, $validated, $studentUpdate): void {
            StudentStatusRecord::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year' => $currentSchoolYear,
                    'semester' => $currentSemester,
                ],
                [
                    'status' => $validated['status'],
                ]
            );

            $student->update($studentUpdate);
        });

        return back()->with('success', 'Student status updated successfully.');
    }

    public function bulkUpdateStatus(BulkUpdateStudentStatusRequest $request, BulkActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $validated['student_ids'];
        $status = $validated['status'];

        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $updatedCount = 0;

        foreach (BulkChunk::of($studentIds) as $chunk) {
            // Read the students once per chunk instead of querying for every
            // single student.
            $students = Student::query()->whereIn('id', $chunk)->get();

            if ($students->isEmpty()) {
                continue;
            }

            $chunkStudentIds = $students->pluck('id')->all();

            DB::transaction(function () use ($students, $chunkStudentIds, $status, $currentSchoolYear, $currentSemester, $activityLogger): void {
                $now = now();

                $rows = [];

                foreach ($students as $student) {
                    $rows[] = [
                        'student_id' => $student->id,
                        'academic_year' => $currentSchoolYear,
                        'semester' => $currentSemester,
                        'status' => $status,
                        'school_id' => $student->school_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                StudentStatusRecord::upsert(
                    $rows,
                    ['student_id', 'academic_year', 'semester'],
                    ['status', 'updated_at'],
                );

                Student::query()->whereIn('id', $chunkStudentIds)->update(['status' => $status]);

                // A bulk write never fires Eloquent events, so the audit trail
                // is written back explicitly. The per-student revision
                // snapshots are skipped on purpose: writing one per student
                // needs a fresh read of the row and is exactly the cost this
                // bulk endpoint exists to avoid.
                foreach ($students as $student) {
                    $old = $student->getRawOriginal();
                    $activityLogger->updated($student, $old, [...$old, 'status' => $status]);
                }
            });

            $updatedCount += $students->count();
        }

        return back()->with('success', "Updated status for {$updatedCount} student(s).");
    }

    public function bulkManageClearance(BulkUpdateStudentClearanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
        abort_unless($actor instanceof User && $settings, 403);

        $gradeReleaseService = app(GradeReleaseService::class);
        $tenantContext = app(TenantContext::class);
        $schoolYear = $settings->getSchoolYearString();
        $semester = (int) $settings->semester;
        $updatedCount = 0;

        DB::transaction(function () use ($validated, $actor, $gradeReleaseService, $tenantContext, $schoolYear, $semester, &$updatedCount): void {
            foreach (BulkChunk::of($validated['student_ids']) as $chunk) {
                $students = Student::query()->whereIn('id', $chunk)->get();
                foreach ($students as $student) {
                    $studentSchoolId = $student->school_id ?: $student->institution_id;
                    abort_if($studentSchoolId && ! $tenantContext->canAccessOrganization((int) $studentSchoolId), 404);
                    $gradeReleaseService->updateClearance(
                        $student,
                        $schoolYear,
                        $semester,
                        (bool) $validated['is_cleared'],
                        $validated['remarks'] ?? null,
                        $actor,
                    );
                    $updatedCount++;
                }
            }
        });

        return back()->with('success', "Updated grade-release clearance for {$updatedCount} student(s).");
    }

    public function bulkSendEmail(BulkEmailStudentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $validated['student_ids'];
        $subjectLine = $validated['subject'];
        $body = $validated['message'];

        $user = $request->user();
        $senderName = $user?->name ?? 'Administrator';
        $senderRole = $user?->role?->getLabel() ?? 'Administrator';
        $schoolName = (string) config('app.name', 'Administration');

        $sentCount = 0;
        $skippedCount = 0;

        // The students are read in chunks so a large selection never turns
        // into one unbounded `where in (...)` statement.
        foreach (BulkChunk::of($studentIds) as $chunk) {
            foreach (Student::query()->whereIn('id', $chunk)->get() as $student) {
                if (! $student->email) {
                    $skippedCount++;

                    continue;
                }

                Mail::to($student->email)->send(new StudentBulkMessage(
                    studentName: $student->full_name,
                    subjectLine: $subjectLine,
                    body: $body,
                    senderName: $senderName,
                    senderRole: $senderRole,
                    schoolName: $schoolName,
                ));

                $sentCount++;
            }
        }

        $message = "Sent {$sentCount} email(s).";
        if ($skippedCount > 0) {
            $message .= " Skipped {$skippedCount} student(s) without an email address.";
        }

        return back()->with('success', $message);
    }

    public function bulkDestroy(BulkDeleteStudentRequest $request, BulkActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $validated['student_ids'];

        $deletedCount = 0;

        foreach (BulkChunk::of($studentIds) as $chunk) {
            $students = Student::query()->whereIn('id', $chunk)->get();

            if ($students->isEmpty()) {
                continue;
            }

            DB::transaction(function () use ($students, $activityLogger): void {
                Student::query()
                    ->whereIn('id', $students->pluck('id')->all())
                    ->delete();

                // A bulk delete never fires Eloquent events, so the audit trail
                // is written back explicitly.
                foreach ($students as $student) {
                    $activityLogger->deleted($student, $student->getRawOriginal());
                }
            });

            $deletedCount += $students->count();
        }

        return back()->with('success', "Deleted {$deletedCount} student(s).");
    }

    public function bulkForceDestroy(BulkForceDeleteStudentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $validated['student_ids'];

        $expectedConfirm = 'PERMANENTLY DELETE '.count($studentIds).' STUDENT'.(count($studentIds) === 1 ? '' : 'S');

        if (! hash_equals($expectedConfirm, (string) $validated['confirm_text'])) {
            return back()->withErrors([
                'confirm_text' => 'Confirmation text does not match. Type "'.$expectedConfirm.'" exactly to proceed.',
            ])->withInput();
        }

        $deletedStudentIds = [];
        $failures = [];

        foreach (BulkChunk::of($studentIds) as $chunk) {
            $students = Student::withTrashed()->whereIn('id', $chunk)->get();

            if ($students->isEmpty()) {
                continue;
            }

            $eligibleStudents = $students->reject(function (Student $student) use (&$failures): bool {
                if ($student->hasFinancialRecords()) {
                    $failures[] = $student->full_name.' (immutable financial records exist)';

                    return true;
                }

                return false;
            });

            if ($eligibleStudents->isEmpty()) {
                continue;
            }

            $chunkStudentIds = $eligibleStudents->pluck('id')->all();
            /** @var list<array{disk: string|null, path: string}> $filesToDelete */
            $filesToDelete = [];

            try {
                DB::transaction(function () use ($eligibleStudents, $chunkStudentIds, &$filesToDelete): void {
                    $docLocationIds = $eligibleStudents->pluck('document_location_id')->filter()->unique()->values()->all();
                    $parentInfoIds = $eligibleStudents->pluck('student_parent_info')->filter()->unique()->values()->all();
                    $educationInfoIds = $eligibleStudents->pluck('student_education_id')->filter()->unique()->values()->all();
                    $contactInfoIds = $eligibleStudents->pluck('student_contact_id')->filter()->unique()->values()->all();
                    $personalInfoIds = $eligibleStudents->pluck('student_personal_id')->filter()->unique()->values()->all();

                    // Collect non-empty file references before rows are deleted so storage
                    // cleanup can execute only after the transaction commits.
                    if (Schema::hasTable('student_document_records')) {
                        $docRecords = StudentDocumentRecord::query()
                            ->whereIn('student_id', $chunkStudentIds)
                            ->get(['disk', 'file_path']);

                        foreach ($docRecords as $record) {
                            if (is_string($record->file_path) && mb_trim($record->file_path) !== '') {
                                $filesToDelete[] = [
                                    'disk' => is_string($record->disk) && mb_trim($record->disk) !== '' ? mb_trim($record->disk) : null,
                                    'path' => mb_trim($record->file_path),
                                ];
                            }
                        }
                    }

                    $resources = Resource::query()
                        ->where('resourceable_type', Student::class)
                        ->whereIn('resourceable_id', $chunkStudentIds)
                        ->get(['disk', 'file_path']);

                    foreach ($resources as $resource) {
                        if (is_string($resource->file_path) && mb_trim($resource->file_path) !== '') {
                            $filesToDelete[] = [
                                'disk' => is_string($resource->disk) && mb_trim($resource->disk) !== '' ? mb_trim($resource->disk) : null,
                                'path' => mb_trim($resource->file_path),
                            ];
                        }
                    }

                    SubjectEnrollment::query()->whereIn('student_id', $chunkStudentIds)->forceDelete();
                    StudentClearance::query()->whereIn('student_id', $chunkStudentIds)->forceDelete();
                    ClassEnrollment::query()->whereIn('student_id', $chunkStudentIds)->forceDelete();
                    StudentTuition::query()->whereIn('student_id', $chunkStudentIds)->forceDelete();
                    StudentTransaction::query()->whereIn('student_id', $chunkStudentIds)->forceDelete();

                    StudentEnrollment::whereIn('student_id', $chunkStudentIds)->forceDelete();
                    StudentStatusRecord::whereIn('student_id', $chunkStudentIds)->forceDelete();

                    if (Schema::hasTable('class_attendance_records')) {
                        ClassAttendanceRecord::whereIn('student_id', $chunkStudentIds)->delete();
                    }

                    if (Schema::hasTable('student_document_records')) {
                        StudentDocumentRecord::query()->whereIn('student_id', $chunkStudentIds)->delete();
                    }

                    if (Schema::hasTable('student_holds')) {
                        StudentHold::query()->whereIn('student_id', $chunkStudentIds)->delete();
                    }

                    Resource::query()
                        ->where('resourceable_type', Student::class)
                        ->whereIn('resourceable_id', $chunkStudentIds)
                        ->delete();

                    $learnersNumbers = $eligibleStudents->pluck('lrn')->filter()->unique()->values();

                    if ($learnersNumbers->isNotEmpty() && Schema::hasTable('shs_students')) {
                        ShsStudent::whereIn('student_lrn', $learnersNumbers)->forceDelete();
                    }

                    Student::withTrashed()->whereIn('id', $chunkStudentIds)->forceDelete();

                    if ($docLocationIds !== []) {
                        $orphanedDocLocations = DocumentLocation::query()
                            ->whereIn('id', $docLocationIds)
                            ->whereNotExists(function ($sub): void {
                                $sub->selectRaw('1')
                                    ->from('students')
                                    ->whereColumn('students.document_location_id', 'document_locations.id');
                            })
                            ->get(['id', ...DocumentLocation::DOCUMENT_FIELDS]);

                        foreach ($orphanedDocLocations as $docLocation) {
                            foreach (DocumentLocation::DOCUMENT_FIELDS as $field) {
                                $path = $docLocation->getAttribute($field);
                                if (is_string($path) && mb_trim($path) !== '') {
                                    $filesToDelete[] = [
                                        'disk' => null,
                                        'path' => mb_trim($path),
                                    ];
                                }
                            }
                        }

                        if ($orphanedDocLocations->isNotEmpty()) {
                            DocumentLocation::query()
                                ->whereIn('id', $orphanedDocLocations->pluck('id')->all())
                                ->delete();
                        }
                    }

                    if ($parentInfoIds !== [] && Schema::hasTable('student_parents_info')) {
                        DB::table('student_parents_info')
                            ->whereIn('id', $parentInfoIds)
                            ->whereNotExists(function ($sub): void {
                                $sub->selectRaw('1')
                                    ->from('students')
                                    ->whereColumn('students.student_parent_info', 'student_parents_info.id');
                            })
                            ->delete();
                    }

                    if ($educationInfoIds !== [] && Schema::hasTable('student_education_info')) {
                        DB::table('student_education_info')
                            ->whereIn('id', $educationInfoIds)
                            ->whereNotExists(function ($sub): void {
                                $sub->selectRaw('1')
                                    ->from('students')
                                    ->whereColumn('students.student_education_id', 'student_education_info.id');
                            })
                            ->delete();
                    }

                    if ($contactInfoIds !== [] && Schema::hasTable('student_contacts')) {
                        DB::table('student_contacts')
                            ->whereIn('id', $contactInfoIds)
                            ->whereNotExists(function ($sub): void {
                                $sub->selectRaw('1')
                                    ->from('students')
                                    ->whereColumn('students.student_contact_id', 'student_contacts.id');
                            })
                            ->delete();
                    }

                    if ($personalInfoIds !== [] && Schema::hasTable('students_personal_info')) {
                        DB::table('students_personal_info')
                            ->whereIn('id', $personalInfoIds)
                            ->whereNotExists(function ($sub): void {
                                $sub->selectRaw('1')
                                    ->from('students')
                                    ->whereColumn('students.student_personal_id', 'students_personal_info.id');
                            })
                            ->delete();
                    }
                });

                foreach ($chunkStudentIds as $deletedId) {
                    $deletedStudentIds[] = $deletedId;
                }

                $fileDeletionFailures = $this->deleteCollectedStudentFiles($filesToDelete);
                foreach ($fileDeletionFailures as $fileFailure) {
                    $failures[] = $fileFailure;
                }
            } catch (Exception $e) {
                foreach ($eligibleStudents as $student) {
                    $failures[] = $student->full_name.' ('.$e->getMessage().')';
                }
            }
        }

        $deletedCount = count($deletedStudentIds);

        if ($deletedCount > 0) {
            activity('students')
                ->causedBy($request->user())
                ->event('students.bulk_force_deleted')
                ->withProperties([
                    'count' => $deletedCount,
                    'student_ids' => $deletedStudentIds,
                ])
                ->log("Permanently deleted {$deletedCount} student record(s).");
        }

        $message = "Permanently deleted {$deletedCount} student(s).";

        if ($failures !== []) {
            $message .= ' '.count($failures).' failed: '.implode('; ', $failures);

            return back()->with('error', $message);
        }

        return redirect()
            ->route('administrators.students.index')
            ->with('success', $message);
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->authorizeStudentAccess(['Restore:Student', 'RestoreAny:Student'], [UserRole::Admin, UserRole::Registrar]);

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $students = Student::onlyTrashed()->whereIn('id', $validated['student_ids'])->get();
        $restored = 0;

        foreach ($students as $student) {
            $student->restore();
            $restored++;
        }

        if ($restored > 0) {
            activity('students')
                ->causedBy($request->user())
                ->event('students.bulk_restored')
                ->withProperties(['count' => $restored, 'student_ids' => $students->pluck('id')->all()])
                ->log("Restored {$restored} trashed student record(s).");
        }

        return back()->with('success', "Restored {$restored} student(s).");
    }

    public function bulkPromote(Request $request, CurriculumCapabilityResolver $capabilityResolver): RedirectResponse
    {
        $this->authorizeStudentAccess('Update:Student');

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'target_year_level' => ['nullable', 'integer', 'min:1', 'max:99'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        $configService = app(StudentConfigurationService::class);
        $students = Student::query()->whereIn('id', $validated['student_ids'])->get();
        $targetCourse = isset($validated['course_id'])
            ? Course::query()->with('department')->find($validated['course_id'])
            : null;

        if ($targetCourse instanceof Course) {
            foreach ($students as $student) {
                if ($student->student_type === StudentType::TESDA && ! $capabilityResolver->isTesdaCourse($targetCourse)) {
                    return back()->withErrors([
                        'course_id' => 'Select a TESDA qualification for a TESDA student.',
                    ])->withInput();
                }

                if ($student->student_type === StudentType::College && ! $capabilityResolver->isCollegeCourse($targetCourse)) {
                    return back()->withErrors([
                        'course_id' => 'Select a college program for a college student.',
                    ])->withInput();
                }
            }
        }

        $updatedStudentIds = [];
        $skippedInvalidYearLevelCount = 0;

        DB::transaction(function () use ($students, $validated, $configService, &$updatedStudentIds, &$skippedInvalidYearLevelCount): void {
            foreach ($students as $student) {
                $updates = [];
                $levels = $configService->get($student->school_id)['year_levels'][$student->student_type?->value ?? 'college'] ?? [];
                $values = array_values(array_map(fn (array $item): int => (int) $item['value'], $levels));

                if (isset($validated['target_year_level'])) {
                    $targetYearLevel = (int) $validated['target_year_level'];
                    if (in_array($targetYearLevel, $values, true)) {
                        $updates['academic_year'] = $targetYearLevel;
                    } else {
                        $skippedInvalidYearLevelCount++;
                    }
                } else {
                    $currentIdx = array_search((int) $student->academic_year, $values, true);
                    if ($currentIdx !== false && isset($values[$currentIdx + 1])) {
                        $updates['academic_year'] = $values[$currentIdx + 1];
                    }
                }

                if (isset($validated['course_id']) && $student->student_type !== StudentType::SeniorHighSchool) {
                    $updates['course_id'] = (int) $validated['course_id'];
                }

                if ($updates !== []) {
                    $student->update($updates);
                    $updatedStudentIds[] = $student->id;
                }
            }
        });

        $updated = count($updatedStudentIds);

        if ($updated === 0 && $skippedInvalidYearLevelCount > 0) {
            return back()->withErrors([
                'target_year_level' => 'The selected year level is not configured for the selected student(s).',
            ])->withInput();
        }

        if ($updated > 0) {
            activity('students')
                ->causedBy($request->user())
                ->event('students.bulk_promoted')
                ->withProperties(['count' => $updated, 'student_ids' => $updatedStudentIds])
                ->log("Updated academic placement for {$updated} student(s).");
        }

        $message = "Updated placement for {$updated} student(s).";
        if ($skippedInvalidYearLevelCount > 0) {
            $message .= " Skipped year-level update for {$skippedInvalidYearLevelCount} student(s) with incompatible configured levels.";
        }

        return back()->with('success', $message);
    }

    public function validateBatchImport(Request $request, CurriculumCapabilityResolver $capabilityResolver): JsonResponse
    {
        $this->authorizeStudentAccess('Create:Student');

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:250'],
            'rows.*' => ['required', 'array'],
        ]);

        $schoolId = app(TenantContext::class)->getCurrentSchoolId();
        $configService = app(StudentConfigurationService::class);
        $config = $configService->get($schoolId);
        $policy = app(IdentifierGenerator::class)->studentPolicy(schoolId: $schoolId);
        $courses = Course::query()->with('department:id,code')->get(['id', 'code', 'title', 'department_id', 'curriculum_kind']);
        $coursesByCode = $courses->keyBy(fn (Course $c): string => mb_strtolower($c->code));
        $coursesById = $courses->keyBy('id');
        $strands = ShsStrand::query()->get(['id', 'strand_name']);
        $strandsByName = $strands->keyBy(fn (ShsStrand $s): string => mb_strtolower($s->strand_name));
        $strandsById = $strands->keyBy('id');

        $previewRows = [];
        $validCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;

        foreach ($validated['rows'] as $index => $rawRow) {
            $rowResult = $this->validateAndNormalizeBatchImportRow(
                rawRow: $rawRow,
                config: $config,
                policy: $policy,
                schoolId: $schoolId,
                coursesByCode: $coursesByCode,
                coursesById: $coursesById,
                strandsByName: $strandsByName,
                strandsById: $strandsById,
                configService: $configService,
                capabilityResolver: $capabilityResolver,
                isCommit: false,
            );

            $errors = $rowResult['errors'];
            $warnings = [];
            $normalized = $rowResult['normalized'];

            // Duplicate check
            $duplicateMatch = null;
            if ($normalized['first_name'] !== '' && $normalized['last_name'] !== '' && $normalized['birth_date'] !== null) {
                $firstName = $normalized['first_name'];
                $lastName = $normalized['last_name'];
                $birthDate = $normalized['birth_date'];
                $email = $normalized['email'] ?? '';
                $lrn = $normalized['lrn'] ?? '';

                $duplicateMatch = Student::query()
                    ->where(function (Builder $q) use ($firstName, $lastName, $birthDate, $email, $lrn): void {
                        $q->where(function (Builder $nameQ) use ($firstName, $lastName, $birthDate): void {
                            $nameQ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)])
                                ->whereDate('birth_date', $birthDate);
                        });
                        if ($email !== '') {
                            $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                        }
                        if ($lrn !== '') {
                            $q->orWhere('lrn', $lrn);
                        }
                    })
                    ->first(['id', 'student_id', 'first_name', 'last_name']);
            }

            if ($duplicateMatch) {
                $duplicateCount++;
                $warnings[] = "Possible duplicate of {$duplicateMatch->full_name} ({$duplicateMatch->student_id}).";
            }

            $isValid = $errors === [];
            if ($isValid) {
                $validCount++;
            } else {
                $errorCount++;
            }

            $previewRows[] = [
                'row_number' => $index + 1,
                'valid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings,
                'normalized' => $normalized,
            ];
        }

        return response()->json([
            'summary' => [
                'total' => count($previewRows),
                'valid' => $validCount,
                'errors' => $errorCount,
                'duplicates' => $duplicateCount,
            ],
            'rows' => $previewRows,
        ]);
    }

    public function commitBatchImport(Request $request, CurriculumCapabilityResolver $capabilityResolver): JsonResponse
    {
        $this->authorizeStudentAccess('Create:Student');

        // Validate only the batch envelope up front so each row can be validated
        // independently against current school rules without rejecting valid siblings.
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:250'],
            'provision_portal_accounts' => ['nullable', 'boolean'],
        ]);

        $schoolId = app(TenantContext::class)->getCurrentSchoolId();
        $identifierGenerator = app(IdentifierGenerator::class);
        $configuration = app(StudentConfigurationService::class);
        $configuration->forget($schoolId);
        $config = $configuration->get($schoolId);
        $policy = $identifierGenerator->studentPolicy(schoolId: $schoolId);
        $courses = Course::query()->with('department:id,code')->get(['id', 'code', 'title', 'department_id', 'curriculum_kind']);
        $coursesByCode = $courses->keyBy(fn (Course $c): string => mb_strtolower($c->code));
        $coursesById = $courses->keyBy('id');
        $strands = ShsStrand::query()->get(['id', 'strand_name']);
        $strandsByName = $strands->keyBy(fn (ShsStrand $s): string => mb_strtolower($s->strand_name));
        $strandsById = $strands->keyBy('id');

        $created = [];
        $failed = [];
        $activationFailed = [];

        foreach ($validated['rows'] as $index => $rawRow) {
            if (! is_array($rawRow)) {
                $failed[] = [
                    'row_number' => $index + 1,
                    'name' => '',
                    'error' => 'Row payload must be an object.',
                    'errors' => ['Row payload must be an object.'],
                ];

                continue;
            }

            $rowResult = $this->validateAndNormalizeBatchImportRow(
                rawRow: $rawRow,
                config: $config,
                policy: $policy,
                schoolId: $schoolId,
                coursesByCode: $coursesByCode,
                coursesById: $coursesById,
                strandsByName: $strandsByName,
                strandsById: $strandsById,
                configService: $configuration,
                capabilityResolver: $capabilityResolver,
                isCommit: true,
            );

            $row = $rowResult['normalized'];
            $rowName = mb_trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));

            if ($rowResult['errors'] !== []) {
                $failed[] = [
                    'row_number' => $index + 1,
                    'name' => $rowName,
                    'error' => implode(' ', $rowResult['errors']),
                    'errors' => $rowResult['errors'],
                ];

                continue;
            }

            try {
                $student = DB::transaction(function () use ($row, $identifierGenerator, $configuration, $policy, $schoolId): Student {
                    $identifierGenerator->sequenceFor(IdentifierGenerator::Student, lock: true, schoolId: $schoolId);
                    $studentType = StudentType::from($row['student_type']);
                    $usesExternal = $configuration->usesExternal($studentType->value, $schoolId);
                    $mode = filled($row['student_id'] ?? null) || ($policy['assignment_mode'] ?? 'automatic') === 'manual' ? 'manual' : 'automatic';
                    $status = StudentStatus::tryFrom((string) ($row['status'] ?? StudentStatus::Applicant->value)) ?? StudentStatus::Applicant;
                    $birthDate = Carbon::parse($row['birth_date']);

                    // Backend replay/duplicate safeguard: reject rows whose normalized identity already exists.
                    $existingDuplicate = Student::query()
                        ->where(function (Builder $q) use ($row, $birthDate): void {
                            $q->where(function (Builder $nameQ) use ($row, $birthDate): void {
                                $nameQ->whereRaw('LOWER(first_name) = ?', [mb_strtolower((string) $row['first_name'])])
                                    ->whereRaw('LOWER(last_name) = ?', [mb_strtolower((string) $row['last_name'])])
                                    ->whereDate('birth_date', $birthDate->toDateString());
                            });

                            if (filled($row['email'] ?? null)) {
                                $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower((string) $row['email'])]);
                            }

                            if (filled($row['lrn'] ?? null)) {
                                $q->orWhere('lrn', (string) $row['lrn']);
                            }

                            if (filled($row['student_id'] ?? null)) {
                                $q->orWhere('student_id', (string) $row['student_id']);
                            }
                        })
                        ->lockForUpdate()
                        ->first(['id', 'student_id', 'first_name', 'last_name']);

                    if ($existingDuplicate !== null) {
                        throw ValidationException::withMessages([
                            'row' => "Already imported or duplicate of {$existingDuplicate->full_name} (ID: {$existingDuplicate->student_id}).",
                        ]);
                    }

                    $student = new Student;
                    $student->fill([
                        'student_type' => $studentType->value,
                        'first_name' => $row['first_name'],
                        'middle_name' => $row['middle_name'],
                        'last_name' => $row['last_name'],
                        'gender' => $row['gender'],
                        'birth_date' => $birthDate->toDateString(),
                        'age' => $birthDate->age,
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                        'academic_year' => (int) $row['academic_year'],
                        'status' => $status->value,
                        'scholarship_type' => ScholarshipType::None->value,
                        'employment_status' => EmploymentStatus::NotApplicable->value,
                    ]);

                    $student->lrn = $row['lrn'];
                    $student->student_id = $usesExternal
                        ? $identifierGenerator->claimExternalIdentifier($studentType, (string) $row['lrn'], $schoolId)
                        : $identifierGenerator->assign($studentType, $row['student_id'], $mode, $schoolId);

                    if ($studentType === StudentType::SeniorHighSchool) {
                        $student->shs_strand_id = $row['shs_strand_id'];
                    } else {
                        $student->course_id = $row['course_id'];
                    }

                    $student->save();
                    $this->syncCurrentStudentStatus($student, $status);

                    return $student;
                });
            } catch (Throwable $e) {
                $failed[] = [
                    'row_number' => $index + 1,
                    'name' => $rowName,
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            // Record the saved student immediately after persistence commits so a later
            // portal activation error never causes the saved row to be classified as failed.
            $created[] = [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
            ];

            if ((bool) ($validated['provision_portal_accounts'] ?? false) && filled($student->email)) {
                try {
                    $portalRequest = Request::create('', 'POST', ['mode' => 'auto', 'send_reset_link' => true]);
                    $portalRequest->headers->set('Accept', 'application/json');
                    $portalResponse = $this->managePortalAccount($portalRequest, $student);

                    if ($portalResponse instanceof JsonResponse && $portalResponse->getStatusCode() >= 400) {
                        $payload = $portalResponse->getData(true);
                        $activationFailed[] = [
                            'row_number' => $index + 1,
                            'id' => $student->id,
                            'student_id' => $student->student_id,
                            'full_name' => $student->full_name,
                            'email' => $student->email,
                            'error' => is_array($payload) && is_string($payload['message'] ?? null)
                                ? $payload['message']
                                : 'Portal account could not be provisioned.',
                        ];
                    }
                } catch (Throwable $activationException) {
                    Log::warning('Batch import student saved, but portal activation dispatch failed.', [
                        'student_id' => $student->id,
                        'row_number' => $index + 1,
                        'error' => $activationException->getMessage(),
                    ]);
                    $activationFailed[] = [
                        'row_number' => $index + 1,
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'full_name' => $student->full_name,
                        'email' => $student->email,
                        'error' => $activationException->getMessage(),
                    ];
                }
            }
        }

        if ($created !== []) {
            activity('students')
                ->causedBy($request->user())
                ->event('students.batch_imported')
                ->withProperties([
                    'created_count' => count($created),
                    'failed_count' => count($failed),
                    'activation_failed_count' => count($activationFailed),
                ])
                ->log('Batch imported '.count($created).' student record(s).');
        }

        $message = 'Imported '.count($created).' student(s)';
        $suffixParts = [];
        if ($failed !== []) {
            $suffixParts[] = count($failed).' failed';
        }
        if ($activationFailed !== []) {
            $suffixParts[] = count($activationFailed).' portal activation(s) failed';
        }
        $message .= $suffixParts !== [] ? ' ('.implode(', ', $suffixParts).').' : '.';

        return response()->json([
            'message' => $message,
            'created_count' => count($created),
            'failed_count' => count($failed),
            'activation_failed_count' => count($activationFailed),
            'created' => $created,
            'failed' => $failed,
            'activation_failed' => $activationFailed,
        ], $created !== [] ? 201 : 422);
    }

    public function removeSubject(Student $student, SubjectEnrollment $subjectEnrollment): RedirectResponse
    {
        if ($subjectEnrollment->student_id !== $student->id) {
            abort(403);
        }

        $subjectEnrollment->delete();

        return back()->with('success', 'Subject removed successfully.');
    }

    /**
     * Soft delete a student (applicant).
     */
    public function destroy(Student $student): RedirectResponse
    {
        $this->authorizeStudentAccess(['Delete:Student', 'DeleteAny:Student'], [UserRole::Admin, UserRole::Registrar]);

        $studentName = $student->full_name;

        $student->delete();

        return back()->with('success', "Student \"{$studentName}\" has been deleted.");
    }

    /**
     * Restore a soft-deleted student.
     */
    public function restore(int $student): RedirectResponse
    {
        $this->authorizeStudentAccess(['Restore:Student', 'RestoreAny:Student'], [UserRole::Admin, UserRole::Registrar]);

        $studentModel = Student::withTrashed()->findOrFail($student);

        if (! $studentModel->trashed()) {
            return back()->with('warning', "Student \"{$studentModel->full_name}\" is not deleted.");
        }

        $studentName = $studentModel->full_name;

        $studentModel->restore();

        return back()->with('success', "Student \"{$studentName}\" has been restored.");
    }

    /**
     * Permanently delete a student and all related records.
     */
    public function forceDestroy(Request $request, int $student): RedirectResponse
    {
        // Intentionally permits the portal Admin role alongside explicit ForceDelete grants
        // (matching BulkForceDeleteStudentRequest::authorize), while restricting Registrar
        // and other staff roles unless they hold ForceDelete:Student or ForceDeleteAny:Student.
        $this->authorizeStudentAccess(['ForceDelete:Student', 'ForceDeleteAny:Student'], [UserRole::Admin]);

        $studentModel = Student::withTrashed()->findOrFail($student);

        $studentName = $studentModel->full_name;

        if ($studentModel->hasFinancialRecords()) {
            return back()->with('error', "Student \"{$studentName}\" cannot be permanently deleted because financial ledgers, transactions, or signed documents exist. Retain financial records for audit compliance.");
        }

        try {
            DB::transaction(function () use ($studentModel): void {
                $studentId = $studentModel->id;

                $studentModel->subjectEnrolled()->forceDelete();
                $studentModel->clearances()->forceDelete();
                $studentModel->classEnrollments()->forceDelete();
                $studentModel->StudentTuition()->forceDelete();
                $studentModel->StudentTransactions()->forceDelete();

                StudentEnrollment::where('student_id', $studentId)->forceDelete();
                StudentStatusRecord::where('student_id', $studentId)->forceDelete();

                if (Schema::hasTable('class_attendance_records')) {
                    ClassAttendanceRecord::where('student_id', $studentId)->delete();
                }

                if ($studentModel->lrn && Schema::hasTable('shs_students')) {
                    ShsStudent::where('student_lrn', $studentModel->lrn)->forceDelete();
                }

                $studentModel->forceDelete();
            });

            return redirect()
                ->route('administrators.students.index')
                ->with('success', "Student \"{$studentName}\" has been permanently deleted.");
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete student: '.$e->getMessage());
        }
    }

    /**
     * Persist optional 1x1 ID photo, signature, and fixed document uploads submitted alongside Student create/edit forms.
     *
     * Catches per-slot upload failures so already-saved student records and committed documents
     * are preserved, returning only the failed slots for retry.
     *
     * @return array{uploaded_slots: list<string>, failed_slots: array<string, string>}
     */
    private function persistStudentAssets(Request $request, Student $student): array
    {
        $uploadedSlots = [];
        $failedSlots = [];

        if ($request->hasFile('picture_1x1')) {
            try {
                /** @var UploadedFile $photo */
                $photo = $request->file('picture_1x1');
                $privateDisk = (string) config('filesystems.private_disk', 'private');
                $path = $photo->store("students/{$student->id}/documents", $privateDisk);

                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('Unable to store ID photo.');
                }

                $student->loadMissing('DocumentLocation');
                if (! $student->DocumentLocation) {
                    $docLocation = DocumentLocation::create(['picture_1x1' => $path]);
                    $student->forceFill(['document_location_id' => $docLocation->id])->save();
                    $student->setRelation('DocumentLocation', $docLocation);
                } else {
                    $oldPath = $student->DocumentLocation->picture_1x1;
                    if (is_string($oldPath) && $oldPath !== '' && ! filter_var($oldPath, FILTER_VALIDATE_URL) && ! str_starts_with($oldPath, '/')) {
                        foreach (['private', config('filesystems.default'), 'public'] as $disk) {
                            if (is_string($disk) && Storage::disk($disk)->exists($oldPath)) {
                                Storage::disk($disk)->delete($oldPath);
                            }
                        }
                    }
                    $student->DocumentLocation->update(['picture_1x1' => $path]);
                }

                $uploadedSlots[] = 'picture_1x1';
            } catch (Throwable $e) {
                Log::warning('Failed to persist student picture_1x1 asset.', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
                $failedSlots['picture_1x1'] = 'Failed to upload ID photo. Please retry.';
            }
        }

        if ($request->hasFile('signature')) {
            try {
                /** @var UploadedFile $signature */
                $signature = $request->file('signature');
                $privateDisk = (string) config('filesystems.private_disk', 'private');
                $newPath = $signature->store("students/{$student->id}/signatures", $privateDisk);

                if (! is_string($newPath) || $newPath === '') {
                    throw new RuntimeException('Unable to store signature.');
                }

                $this->deleteStoredSignature($student->signature_path);
                $student->forceFill(['signature_path' => $newPath])->save();
                $uploadedSlots[] = 'signature';
            } catch (Throwable $e) {
                Log::warning('Failed to persist student signature asset.', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
                $failedSlots['signature'] = 'Failed to upload signature. Please retry.';
            }
        }

        $documentFiles = $request->file('documents', []);
        if (is_array($documentFiles) && $documentFiles !== []) {
            $documentService = app(StudentDocumentService::class);
            /** @var User|null $actor */
            $actor = Auth::user();
            foreach ($documentFiles as $docKey => $uploadedFile) {
                if (! is_string($docKey) || ! in_array($docKey, DocumentLocation::DOCUMENT_FIELDS, true) || ! $uploadedFile instanceof UploadedFile) {
                    continue;
                }

                try {
                    // Reload/unset DocumentLocation across iterations so a newly created
                    // DocumentLocation row is reused by all subsequent document slots.
                    $student->unsetRelation('DocumentLocation');
                    $documentService->uploadFixed($student, $docKey, $uploadedFile, $actor, autoVerify: true);
                    $student->load('DocumentLocation');
                    $uploadedSlots[] = $docKey;
                } catch (Throwable $e) {
                    Log::warning('Failed to persist fixed student document slot.', [
                        'student_id' => $student->id,
                        'document_key' => $docKey,
                        'error' => $e->getMessage(),
                    ]);
                    $failedSlots[$docKey] = 'Failed to upload document. Please retry this slot.';
                }
            }
        }

        return [
            'uploaded_slots' => $uploadedSlots,
            'failed_slots' => $failedSlots,
        ];
    }

    /**
     * Apply the shared "Needs Doc Review" predicate matching StudentDocumentService::rowSummaryFor()
     * effective-status logic, including legacy DocumentLocation files with no StudentDocumentRecord.
     *
     * @param  array<string, mixed>  $studentConfiguration
     */
    private function applyNeedsDocReviewFilter(
        Builder $query,
        StudentDocumentService $documentService,
        array $studentConfiguration,
        ?int $schoolId,
        bool $hasDocRecordsTable,
    ): void {
        $knownTypes = array_column(StudentType::cases(), 'value');
        $defaultType = (string) ($studentConfiguration['default_type'] ?? StudentType::College->value);
        $slotKeysByType = [];

        foreach ($knownTypes as $typeValue) {
            $slotKeys = array_values(array_filter(
                array_column($documentService->checklistFor($typeValue, $schoolId), 'key'),
                static fn (string $key): bool => in_array($key, DocumentLocation::DOCUMENT_FIELDS, true),
            ));

            if ($slotKeys !== []) {
                $slotKeysByType[$typeValue] = $slotKeys;
            }
        }

        if (! $hasDocRecordsTable && $slotKeysByType === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $outer) use ($slotKeysByType, $knownTypes, $defaultType, $hasDocRecordsTable): void {
            if ($hasDocRecordsTable) {
                $outer->whereHas('documentRecords', function (Builder $recQ): void {
                    $recQ->where('status', StudentDocumentRecord::StatusPendingReview);
                })->orWhereHas('documentRecords', function (Builder $recQ): void {
                    $recQ->where('status', StudentDocumentRecord::StatusMissing)
                        ->whereNotNull('file_path')
                        ->whereRaw("TRIM(file_path) <> ''");
                });
            }

            foreach ($slotKeysByType as $typeValue => $slotKeys) {
                $outer->orWhere(function (Builder $typeQ) use ($typeValue, $defaultType, $knownTypes, $slotKeys, $hasDocRecordsTable): void {
                    if ($typeValue === $defaultType) {
                        $typeQ->where(function (Builder $matchType) use ($typeValue, $knownTypes): void {
                            $matchType->where('students.student_type', $typeValue)
                                ->orWhereNull('students.student_type')
                                ->orWhereNotIn('students.student_type', $knownTypes);
                        });
                    } else {
                        $typeQ->where('students.student_type', $typeValue);
                    }

                    $typeQ->where(function (Builder $legacyQ) use ($slotKeys, $hasDocRecordsTable): void {
                        foreach ($slotKeys as $slotKey) {
                            $legacyQ->orWhere(function (Builder $slotQ) use ($slotKey, $hasDocRecordsTable): void {
                                $slotQ->whereHas('DocumentLocation', function (Builder $docQ) use ($slotKey): void {
                                    $docQ->whereNotNull($slotKey)
                                        ->whereRaw("TRIM({$slotKey}) <> ''");
                                });

                                if ($hasDocRecordsTable) {
                                    $slotQ->whereDoesntHave('documentRecords', function (Builder $recQ) use ($slotKey): void {
                                        $recQ->where('document_key', $slotKey)
                                            ->where('status', '!=', StudentDocumentRecord::StatusMissing);
                                    });
                                }
                            });
                        }
                    });
                });
            }
        });
    }

    /**
     * Apply the shared per-student-type "Missing Required Docs" predicate using
     * StudentDocumentService checklists and compliant status rules.
     *
     * @param  array<string, mixed>  $studentConfiguration
     */
    private function applyMissingRequiredDocsFilter(
        Builder $query,
        StudentDocumentService $documentService,
        array $studentConfiguration,
        ?int $schoolId,
        bool $hasDocRecordsTable,
    ): void {
        $knownTypes = array_column(StudentType::cases(), 'value');
        $defaultType = (string) ($studentConfiguration['default_type'] ?? StudentType::College->value);
        $requiredKeysByType = [];

        foreach ($knownTypes as $typeValue) {
            $requiredKeys = collect($documentService->checklistFor($typeValue, $schoolId))
                ->where('required', true)
                ->pluck('key')
                ->filter(static fn (mixed $key): bool => is_string($key) && in_array($key, DocumentLocation::DOCUMENT_FIELDS, true))
                ->values()
                ->all();

            if ($requiredKeys !== []) {
                $requiredKeysByType[$typeValue] = $requiredKeys;
            }
        }

        if ($requiredKeysByType === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $outer) use ($requiredKeysByType, $knownTypes, $defaultType, $hasDocRecordsTable): void {
            foreach ($requiredKeysByType as $typeValue => $requiredKeys) {
                $outer->orWhere(function (Builder $typeQ) use ($typeValue, $defaultType, $knownTypes, $requiredKeys, $hasDocRecordsTable): void {
                    if ($typeValue === $defaultType) {
                        $typeQ->where(function (Builder $matchType) use ($typeValue, $knownTypes): void {
                            $matchType->where('students.student_type', $typeValue)
                                ->orWhereNull('students.student_type')
                                ->orWhereNotIn('students.student_type', $knownTypes);
                        });
                    } else {
                        $typeQ->where('students.student_type', $typeValue);
                    }

                    if ($hasDocRecordsTable) {
                        $typeQ->where(function (Builder $missingSlot) use ($requiredKeys): void {
                            foreach ($requiredKeys as $key) {
                                $missingSlot->orWhereDoesntHave('documentRecords', function (Builder $recQ) use ($key): void {
                                    $recQ->where('document_key', $key)
                                        ->whereIn('status', StudentDocumentRecord::COMPLIANT_STATUSES);
                                });
                            }
                        });
                    } else {
                        $typeQ->where(function (Builder $legacyMissing) use ($requiredKeys): void {
                            $legacyMissing->whereNull('students.document_location_id')
                                ->orWhereHas('DocumentLocation', function (Builder $docQ) use ($requiredKeys): void {
                                    $docQ->where(function (Builder $missing) use ($requiredKeys): void {
                                        foreach ($requiredKeys as $key) {
                                            $missing->orWhereNull($key)
                                                ->orWhereRaw("TRIM({$key}) = ''");
                                        }
                                    });
                                });
                        });
                    }
                });
            }
        });
    }

    /**
     * Delete storage files collected prior to a committed bulk force-delete chunk.
     *
     * @param  list<array{disk: string|null, path: string}>  $files
     * @return list<string>
     */
    private function deleteCollectedStudentFiles(array $files): array
    {
        $failures = [];
        $seen = [];
        $candidateDisks = array_values(array_unique(array_filter([
            (string) config('filesystems.private_disk', 'private'),
            'private',
            is_string(config('filesystems.default')) ? (string) config('filesystems.default') : null,
            'public',
        ])));

        foreach ($files as $entry) {
            $path = $entry['path'];
            $recordedDisk = $entry['disk'];

            if (
                $path === ''
                || str_contains($path, '..')
                || filter_var($path, FILTER_VALIDATE_URL)
                || str_starts_with($path, '/')
            ) {
                continue;
            }

            $disksToCheck = $recordedDisk !== null ? [$recordedDisk] : $candidateDisks;

            foreach ($disksToCheck as $disk) {
                $key = $disk.'|'.$path;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                try {
                    if (Storage::disk($disk)->exists($path)) {
                        $deleted = Storage::disk($disk)->delete($path);
                        if (! $deleted) {
                            $failures[] = "File cleanup failed for {$path} on disk {$disk}";
                            Log::warning('Failed to delete student file after bulk permanent deletion.', [
                                'disk' => $disk,
                                'path' => $path,
                            ]);
                        }
                    }
                } catch (Throwable $e) {
                    $failures[] = "File cleanup failed for {$path} on disk {$disk} ({$e->getMessage()})";
                    Log::warning('Exception while deleting student file after bulk permanent deletion.', [
                        'disk' => $disk,
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $failures;
    }

    /**
     * Shared per-row normalization and validation used by both batch import preview and commit.
     *
     * @param  array<string, mixed>  $rawRow
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $policy
     * @param  Collection<string, Course>  $coursesByCode
     * @param  Collection<int, Course>  $coursesById
     * @param  Collection<string, ShsStrand>  $strandsByName
     * @param  Collection<int, ShsStrand>  $strandsById
     * @return array{errors: list<string>, normalized: array<string, mixed>}
     */
    private function validateAndNormalizeBatchImportRow(
        array $rawRow,
        array $config,
        array $policy,
        ?int $schoolId,
        Collection $coursesByCode,
        Collection $coursesById,
        Collection $strandsByName,
        Collection $strandsById,
        StudentConfigurationService $configService,
        CurriculumCapabilityResolver $capabilityResolver,
        bool $isCommit,
    ): array {
        $errors = [];

        $cellKeys = [
            'student_type',
            'first_name',
            'middle_name',
            'last_name',
            'gender',
            'birth_date',
            'email',
            'phone',
            'lrn',
            'student_id',
            'program',
            'course_code',
            'strand',
            'course_id',
            'shs_strand_id',
            'year_level',
            'academic_year',
            'status',
        ];

        /** @var array<string, string|null> $cells */
        $cells = [];
        foreach ($cellKeys as $cellKey) {
            if (! array_key_exists($cellKey, $rawRow) || $rawRow[$cellKey] === null) {
                $cells[$cellKey] = null;

                continue;
            }

            if (! is_scalar($rawRow[$cellKey])) {
                $errors[] = "Field '{$cellKey}' must be a scalar value.";
                $cells[$cellKey] = null;

                continue;
            }

            $cells[$cellKey] = mb_trim((string) $rawRow[$cellKey]);
        }

        $typeRaw = $cells['student_type'];
        $type = $typeRaw !== null && $typeRaw !== ''
            ? mb_strtolower($typeRaw)
            : ($isCommit ? '' : mb_strtolower((string) ($config['default_type'] ?? StudentType::College->value)));

        if ($type === '' || StudentType::tryFrom($type) === null || ! in_array($type, $config['enabled_types'], true)) {
            $errors[] = $type === ''
                ? 'Student type is required.'
                : "Unsupported student type '{$type}' for this school.";
        }

        $firstName = mb_trim(strip_tags($cells['first_name'] ?? ''));
        $lastName = mb_trim(strip_tags($cells['last_name'] ?? ''));
        $middleName = mb_trim(strip_tags($cells['middle_name'] ?? ''));
        $genderRaw = $cells['gender'];
        $gender = $genderRaw !== null
            ? mb_strtolower($genderRaw)
            : ($isCommit ? '' : mb_strtolower((string) ($config['defaults']['gender'] ?: 'male')));
        $birthDateRaw = $cells['birth_date'] ?? '';
        $email = $cells['email'] ?? '';
        $phone = $cells['phone'] ?? '';
        $lrn = $cells['lrn'] ?? '';
        $manualId = $cells['student_id'] ?? '';
        $programInput = $cells['program'] ?? $cells['course_code'] ?? $cells['strand'] ?? '';
        $courseIdRaw = $cells['course_id'] ?? '';
        $shsStrandIdRaw = $cells['shs_strand_id'] ?? '';
        $yearLevelRaw = $cells['year_level'] ?? $cells['academic_year'] ?? '';
        $statusRaw = $cells['status'];
        $status = $statusRaw !== null && $statusRaw !== ''
            ? mb_strtolower($statusRaw)
            : StudentStatus::Applicant->value;

        if ($firstName === '') {
            $errors[] = 'First name is required.';
        } elseif (mb_strlen($firstName) > 100) {
            $errors[] = 'First name must not exceed 100 characters.';
        }

        if ($lastName === '') {
            $errors[] = 'Last name is required.';
        } elseif (mb_strlen($lastName) > 100) {
            $errors[] = 'Last name must not exceed 100 characters.';
        }

        if ($middleName !== '' && mb_strlen($middleName) > 100) {
            $errors[] = 'Middle name must not exceed 100 characters.';
        }

        if (! in_array($gender, ['male', 'female', 'other', 'prefer_not_to_say'], true)) {
            $errors[] = 'Gender must be male, female, other, or prefer_not_to_say.';
        }

        $birthDate = null;
        if ($birthDateRaw === '') {
            $errors[] = 'Birth date is required (YYYY-MM-DD).';
        } else {
            try {
                $parsed = Carbon::parse($birthDateRaw);
                if (! $parsed->lt(Carbon::today())) {
                    $errors[] = 'Birth date must be before today.';
                } else {
                    $birthDate = $parsed->toDateString();
                }
            } catch (Throwable) {
                $errors[] = 'Invalid birth date format.';
            }
        }

        if ($email !== '') {
            if (mb_strlen($email) > 255) {
                $errors[] = 'Email must not exceed 255 characters.';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            }
        }

        $emailRequired = (bool) (($config['fields']['email']['visible'] ?? true) && ($config['fields']['email']['required'] ?? false));
        if ($emailRequired && $email === '') {
            $errors[] = 'Email is required by school configuration.';
        }

        if ($phone !== '' && mb_strlen($phone) > 30) {
            $errors[] = 'Phone must not exceed 30 characters.';
        }

        $phoneRequired = (bool) (($config['fields']['phone']['visible'] ?? true) && ($config['fields']['phone']['required'] ?? false));
        if ($phoneRequired && $phone === '') {
            $errors[] = 'Phone is required by school configuration.';
        }

        $external = $configService->externalFor($type, $schoolId);
        if ($external['enabled']) {
            $externalLabel = $external['label'] ?: 'External ID';
            if (($external['required'] || $external['use_as_student_id']) && $lrn === '') {
                $errors[] = $externalLabel.' is required.';
            } elseif ($lrn !== '') {
                $minLen = (int) ($external['min_length'] ?? 1);
                $maxLen = min(32, (int) ($external['max_length'] ?? 32));
                if (mb_strlen($lrn) < $minLen || mb_strlen($lrn) > $maxLen) {
                    $errors[] = "{$externalLabel} must be between {$minLen} and {$maxLen} characters.";
                } elseif (($external['format'] ?? 'text') === 'digits' && ! preg_match('/\A[0-9]+\z/', $lrn)) {
                    $errors[] = "{$externalLabel} must contain digits only.";
                } elseif (($external['format'] ?? 'text') === 'alphanumeric' && ! preg_match('/\A[A-Za-z0-9]+\z/', $lrn)) {
                    $errors[] = "{$externalLabel} must be alphanumeric.";
                }
            }
        } elseif ($lrn !== '' && mb_strlen($lrn) > 32) {
            $errors[] = 'LRN must not exceed 32 characters.';
        }

        if ($manualId !== '' && mb_strlen($manualId) > 64) {
            $errors[] = 'Student ID must not exceed 64 characters.';
        }

        if (($policy['assignment_mode'] ?? 'automatic') === 'manual' && ! ($external['enabled'] && $external['use_as_student_id']) && $manualId === '') {
            $errors[] = ($policy['display_label'] ?: 'Student ID').' is required in manual ID mode.';
        }

        $courseId = null;
        $shsStrandId = null;
        $resolvedProgramLabel = $programInput;

        if ($type === StudentType::SeniorHighSchool->value) {
            $strand = null;
            if ($shsStrandIdRaw !== '') {
                if (preg_match('/\A[1-9][0-9]*\z/', $shsStrandIdRaw)) {
                    $strand = $strandsById->get((int) $shsStrandIdRaw);
                }
                if (! $strand) {
                    $errors[] = "SHS strand ID '{$shsStrandIdRaw}' not found.";
                }
            } elseif ($programInput !== '') {
                $strand = $strandsByName->get(mb_strtolower($programInput));
                if (! $strand) {
                    $errors[] = "SHS strand '{$programInput}' not found.";
                }
            } else {
                $defaultStrandId = $config['defaults']['shs_strand_id'] ?? null;
                if (is_numeric($defaultStrandId) && (int) $defaultStrandId > 0) {
                    $strand = $strandsById->get((int) $defaultStrandId);
                }
                if (! $strand) {
                    $errors[] = 'SHS strand is required.';
                }
            }

            if ($strand instanceof ShsStrand) {
                $shsStrandId = $strand->id;
                $resolvedProgramLabel = $strand->strand_name;
            }
        } elseif ($type !== '' && StudentType::tryFrom($type) !== null) {
            $course = null;
            if ($courseIdRaw !== '') {
                if (preg_match('/\A[1-9][0-9]*\z/', $courseIdRaw)) {
                    $course = $coursesById->get((int) $courseIdRaw);
                }
                if (! $course) {
                    $errors[] = "Program/course ID '{$courseIdRaw}' not found.";
                }
            } elseif ($programInput !== '') {
                $course = $coursesByCode->get(mb_strtolower($programInput));
                if (! $course) {
                    $errors[] = "Program/course code '{$programInput}' not found.";
                }
            } else {
                $defaultCourseId = $config['defaults']['course_id'] ?? null;
                if (is_numeric($defaultCourseId) && (int) $defaultCourseId > 0) {
                    $course = $coursesById->get((int) $defaultCourseId);
                }
                if (! $course) {
                    $errors[] = 'Program/course code is required.';
                }
            }

            if ($course instanceof Course) {
                $courseId = $course->id;
                $resolvedProgramLabel = $course->code;

                if ($type === StudentType::TESDA->value && ! $capabilityResolver->isTesdaCourse($course)) {
                    $errors[] = "Course '{$course->code}' is not a TESDA qualification.";
                } elseif ($type === StudentType::College->value && ! $capabilityResolver->isCollegeCourse($course)) {
                    $errors[] = "Course '{$course->code}' is not a college program.";
                }
            }
        }

        $configuredLevelItems = is_array($config['year_levels'][$type] ?? null) ? $config['year_levels'][$type] : [];
        $configuredLevels = array_values(array_map(
            static fn (array $item): int => (int) ($item['value'] ?? 0),
            $configuredLevelItems,
        ));
        $yearLevel = $this->parseImportYearLevel(
            raw: $yearLevelRaw,
            configuredLevels: $configuredLevels,
            configuredLevelItems: $configuredLevelItems,
            allowBlankDefault: ! $isCommit,
        );

        if ($yearLevel === null || ! in_array($yearLevel, $configuredLevels, true)) {
            $errors[] = $yearLevelRaw === ''
                ? "Year/grade level is required for {$type}."
                : "Year/grade level '{$yearLevelRaw}' is not configured for {$type}.";
        }

        if (StudentStatus::tryFrom($status) === null) {
            $errors[] = "Invalid student status '{$status}'.";
        }

        return [
            'errors' => $errors,
            'normalized' => [
                'student_type' => $type,
                'student_id' => $manualId !== '' ? $manualId : null,
                'lrn' => $lrn !== '' ? $lrn : null,
                'first_name' => $firstName,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'last_name' => $lastName,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'course_id' => $courseId,
                'shs_strand_id' => $shsStrandId,
                'program_label' => $resolvedProgramLabel,
                'academic_year' => $yearLevel,
                'status' => $status,
            ],
        ];
    }

    /**
     * Parse an imported year/grade level strictly from supported formats without stripping
     * signs or arbitrary non-digits (so values such as "-1" are rejected rather than coerced to 1).
     *
     * @param  list<int>  $configuredLevels
     * @param  list<array<string, mixed>>  $configuredLevelItems
     */
    private function parseImportYearLevel(
        string $raw,
        array $configuredLevels,
        array $configuredLevelItems,
        bool $allowBlankDefault = true,
    ): ?int {
        $trimmed = mb_trim($raw);

        if ($trimmed === '') {
            if (! $allowBlankDefault) {
                return null;
            }

            return isset($configuredLevels[0]) ? $configuredLevels[0] : null;
        }

        // Exact positive integer (e.g. "1", "11")
        if (preg_match('/\A[1-9][0-9]*\z/', $trimmed)) {
            return (int) $trimmed;
        }

        // Exact match against any configured label for this student type (case-insensitive)
        $lower = mb_strtolower($trimmed);
        foreach ($configuredLevelItems as $item) {
            $label = mb_strtolower(mb_trim((string) ($item['label'] ?? '')));
            if ($label !== '' && $label === $lower) {
                return (int) ($item['value'] ?? 0);
            }
        }

        // Supported ordinal format (e.g. "1st", "2nd Year", "3rd yr")
        if (preg_match('/\A([1-9][0-9]*)(?:st|nd|rd|th)(?:\s+(?:year|yr))?\z/i', $trimmed, $matches)) {
            return (int) $matches[1];
        }

        // Supported labeled format (e.g. "Grade 11", "Year 2", "Level 3", "Training Level 1")
        if (preg_match('/\A(?:grade|year|yr|level|training\s+level)\s+([1-9][0-9]*)\z/i', $trimmed, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param  string|array<int, string>  $permissions
     * @param  array<int, UserRole|string>  $allowedRoles
     */
    private function authorizeStudentAccess(
        string|array $permissions,
        array $allowedRoles = [UserRole::Admin, UserRole::Registrar, UserRole::AssistantRegistrar],
    ): void {
        /** @var User|null $user */
        $user = Auth::user();
        $this->abortUnlessUserHasAnyPermission($user, $permissions, $allowedRoles);
    }

    /**
     * Resolve the current-term enrollment for a student, prioritizing primary key matches
     * and non-trashed active enrollments to prevent collisions with public student numbers.
     */
    private function findCurrentEnrollmentForStudent(
        Student $student,
        string $currentSchoolYear,
        int $currentSemester,
        bool $includeTrashed = false
    ): ?StudentEnrollment {
        $schoolYearWithSpaces = $currentSchoolYear;
        $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);
        $schoolYearVariants = array_values(array_unique(array_filter([$schoolYearWithSpaces, $schoolYearNoSpaces])));

        $baseQuery = fn () => $includeTrashed ? StudentEnrollment::withTrashed() : StudentEnrollment::query();

        // 1. Canonical query: match on the primary key student_id foreign key
        $enrollment = $baseQuery()
            ->where('student_id', (string) $student->id)
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $currentSemester)
            ->latest('id')
            ->first();

        // 2. Fallback: match through SubjectEnrollment on student's primary key
        if (! $enrollment) {
            $fallbackEnrollmentId = SubjectEnrollment::query()
                ->where('student_id', (int) $student->id)
                ->whereIn('school_year', $schoolYearVariants)
                ->where('semester', $currentSemester)
                ->whereNotNull('enrollment_id')
                ->latest('id')
                ->value('enrollment_id');

            if ($fallbackEnrollmentId) {
                $enrollment = $baseQuery()->find($fallbackEnrollmentId);
            }
        }

        // 3. Fallback: match on legacy public student_id string, only if distinct from primary key
        // and strictly verify ownership
        if (! $enrollment && $student->student_id !== null && (string) $student->student_id !== (string) $student->id) {
            $legacyEnrollment = $baseQuery()
                ->where('student_id', (string) $student->student_id)
                ->whereIn('school_year', $schoolYearVariants)
                ->where('semester', $currentSemester)
                ->latest('id')
                ->first();

            if ($legacyEnrollment && (
                (int) $legacyEnrollment->student?->id === (int) $student->id ||
                $legacyEnrollment->student?->student_id === $student->student_id
            )) {
                $enrollment = $legacyEnrollment;
            }
        }

        return $enrollment;
    }

    /**
     * @return array<string, float>
     */
    private function tuitionAdjustmentSnapshot(StudentTuition $tuition): array
    {
        return [
            'total_lectures' => (float) $tuition->total_lectures,
            'total_laboratory' => (float) $tuition->total_laboratory,
            'total_miscelaneous_fees' => (float) $tuition->total_miscelaneous_fees,
            'discount' => (float) $tuition->discount,
            'downpayment' => (float) $tuition->downpayment,
            'total_tuition' => (float) $tuition->total_tuition,
            'overall_tuition' => (float) $tuition->overall_tuition,
            'total_balance' => (float) $tuition->total_balance,
        ];
    }

    /**
     * Notify the student (in-app when a portal account is linked, and always by
     * email) that their Statement of Account has been adjusted.
     *
     * @param  array<string, float>  $before
     * @param  array<string, float>  $after
     */
    private function notifyStatementOfAccountAdjustment(
        Student $student,
        StudentTuition $tuition,
        array $before,
        array $after,
        ?User $actor,
    ): void {
        $notification = new StatementOfAccountAdjustedNotification(
            studentId: (int) $student->id,
            studentName: (string) ($student->full_name ?? $student->name ?? $student->first_name ?? 'Student'),
            schoolYear: (string) $tuition->school_year,
            semester: (int) $tuition->semester,
            before: $before,
            after: $after,
            adjustmentNote: $tuition->adjustment_note,
            changedByUserId: $actor?->id,
            changedByName: $actor?->name,
        );

        $portalUser = $this->resolveStudentPortalUser($student);

        if ($portalUser instanceof User) {
            // In-app notification plus email through the portal account.
            $portalUser->notify($notification);

            return;
        }

        if (is_string($student->email) && mb_trim($student->email) !== '') {
            Notification::route('mail', $student->email)->notify($notification);
        }
    }

    /**
     * Resolve the portal account linked to a student record using the same
     * matching rules as other student notification services.
     */
    private function resolveStudentPortalUser(Student $student): ?User
    {
        $email = is_string($student->email) ? mb_strtolower(mb_trim($student->email)) : null;

        return User::query()
            ->whereIn('role', [
                UserRole::Student->value,
                UserRole::GraduateStudent->value,
                UserRole::ShsStudent->value,
            ])
            ->where(function ($query) use ($student, $email): void {
                if ($student->user_id !== null) {
                    $query->orWhere('id', (int) $student->user_id);
                }

                if ($email !== null && $email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }

                $query->orWhere('record_id', (string) $student->id);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncStudentRelations(Student $student, array $validated): void
    {
        $studentContactId = $this->upsertRelatedRecord(
            'student_contacts',
            $student->student_contact_id,
            $this->studentContactAttributes($student, $validated),
        );

        if ($studentContactId !== null) {
            $student->student_contact_id = $studentContactId;
        }

        $studentParentInfoId = $this->upsertRelatedRecord(
            'student_parents_info',
            $student->student_parent_info,
            $this->studentParentInfoAttributes($validated),
        );

        if ($studentParentInfoId !== null) {
            $student->student_parent_info = $studentParentInfoId;
        }

        $studentEducationInfoId = $this->upsertRelatedRecord(
            'student_education_info',
            $student->student_education_id,
            $this->studentEducationInfoAttributes($validated),
        );

        if ($studentEducationInfoId !== null) {
            $student->student_education_id = $studentEducationInfoId;
        }

        $studentPersonalInfoId = $this->upsertRelatedRecord(
            'students_personal_info',
            $student->student_personal_id,
            $this->studentPersonalInfoAttributes($validated),
        );

        if ($studentPersonalInfoId !== null) {
            $student->student_personal_id = $studentPersonalInfoId;
        }
    }

    private function syncCurrentStudentStatus(Student $student, StudentStatus $status): void
    {
        $generalSettingsService = app(GeneralSettingsService::class);

        StudentStatusRecord::updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_year' => $generalSettingsService->getCurrentSchoolYearString(),
                'semester' => $generalSettingsService->getCurrentSemester(),
            ],
            [
                'status' => $status->value,
                'school_id' => $student->school_id,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertRelatedRecord(string $table, ?int $id, array $attributes): ?int
    {
        $attributes = $this->existingColumnAttributes($table, $attributes);

        if ($id === null) {
            return $this->insertRelatedRecord($table, $attributes);
        }

        // Only write what actually changed, so editing an unrelated field
        // (e.g. year level) does not rewrite all four related rows.
        $attributes = $this->changedAttributes($table, $id, $attributes);

        if ($attributes !== [] && $this->hasColumn($table, 'updated_at')) {
            $attributes['updated_at'] = now();
        }

        if ($attributes !== []) {
            DB::table($table)
                ->where('id', $id)
                ->update($attributes);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertRelatedRecord(string $table, array $attributes): ?int
    {
        $attributes = $this->withoutBlankValues($attributes);

        if ($attributes === []) {
            return null;
        }

        if ($this->hasColumn($table, 'created_at')) {
            $attributes['created_at'] = now();
        }

        if ($this->hasColumn($table, 'updated_at')) {
            $attributes['updated_at'] = now();
        }

        return (int) DB::table($table)->insertGetId($attributes);
    }

    /**
     * Return only the attributes whose value differs from the persisted row.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function changedAttributes(string $table, int $id, array $attributes): array
    {
        if ($attributes === []) {
            return [];
        }

        $current = DB::table($table)->where('id', $id)->first();

        if ($current === null) {
            return $attributes;
        }

        $current = (array) $current;
        $changed = [];

        foreach ($attributes as $column => $value) {
            if ($this->attributeChanged($current, (string) $column, $value)) {
                $changed[(string) $column] = $value;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $current
     */
    private function attributeChanged(array $current, string $column, mixed $value): bool
    {
        if (! array_key_exists($column, $current)) {
            return true;
        }

        $existing = $current[$column];

        if ($existing === null || $value === null) {
            return $existing !== $value;
        }

        if (is_bool($existing) || is_bool($value)) {
            return (bool) $existing !== (bool) $value;
        }

        // Drivers may hand back numerics as strings, so compare numerically
        // first and fall back to a string comparison for anything else.
        if (is_numeric($existing) && is_numeric($value)) {
            return (string) $existing !== (string) $value && (float) $existing !== (float) $value;
        }

        return (string) $existing !== (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function existingColumnAttributes(string $table, array $attributes): array
    {
        $columns = $this->columnListing($table);
        $filtered = [];

        foreach ($attributes as $key => $value) {
            if (! in_array(mb_strtolower((string) $key), $columns, true)) {
                continue;
            }

            $filtered[(string) $key] = $value;
        }

        return $filtered;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array(mb_strtolower($column), $this->columnListing($table), true);
    }

    /**
     * @return array<int, string>
     */
    private function columnListing(string $table): array
    {
        return $this->columnListings[$table] ??= array_map(
            static fn (string $column): string => mb_strtolower($column),
            Schema::getColumnListing($table)
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withoutBlankValues(array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentContactsPayload(array $validated): array
    {
        $parentDetails = $this->withoutBlankValues([
            'father_name' => $validated['fathers_name'] ?? null,
            'father_occupation' => $validated['father_occupation'] ?? null,
            'father_contact' => $validated['father_contact'] ?? null,
            'father_email' => $validated['father_email'] ?? null,
            'mother_name' => $validated['mothers_name'] ?? null,
            'mother_occupation' => $validated['mother_occupation'] ?? null,
            'mother_contact' => $validated['mother_contact'] ?? null,
            'mother_email' => $validated['mother_email'] ?? null,
            'guardian_name' => $validated['guardian_name'] ?? ($validated['emergency_contact_name'] ?? null),
            'guardian_relationship' => $validated['guardian_relationship'] ?? ($validated['emergency_contact_relationship'] ?? null),
            'guardian_contact' => $validated['guardian_contact'] ?? ($validated['emergency_contact_phone'] ?? null),
            'guardian_email' => $validated['guardian_email'] ?? null,
            'family_address' => $validated['family_address'] ?? ($validated['emergency_contact_address'] ?? null),
        ]);

        $educationDetails = $this->withoutBlankValues([
            'elementary_school' => $validated['elementary_school'] ?? null,
            'elementary_year_graduated' => $validated['elementary_graduate_year'] ?? null,
            'high_school' => $validated['junior_high_school_name'] ?? null,
            'high_school_year_graduated' => $validated['junior_high_graduation_year'] ?? null,
            'senior_high_school' => $validated['senior_high_name'] ?? null,
            'senior_high_year_graduated' => $validated['senior_high_graduate_year'] ?? null,
            'college_school' => $validated['college_school'] ?? null,
            'college_course' => $validated['college_course'] ?? null,
            'college_year_graduated' => $validated['college_year_graduated'] ?? null,
            'vocational_school' => $validated['vocational_school'] ?? null,
            'vocational_course' => $validated['vocational_course'] ?? null,
            'vocational_year_graduated' => $validated['vocational_year_graduated'] ?? null,
        ]);

        $personalDetails = $this->withoutBlankValues([
            'birthplace' => $validated['birthplace'] ?? null,
            'citizenship' => $validated['citizenship'] ?? ($validated['nationality'] ?? null),
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'current_address' => $validated['current_address'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
        ]);

        return $this->withoutBlankValues([
            'personal_contact' => $validated['personal_contact'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'facebook' => $validated['facebook_contact'] ?? null,
            'twitter' => $validated['twitter'] ?? null,
            'instagram' => $validated['instagram'] ?? null,
            'linkedin' => $validated['linkedin'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'emergency_contact_address' => $validated['emergency_contact_address'] ?? null,
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
            'parents' => $parentDetails !== [] ? $parentDetails : null,
            'education' => $educationDetails !== [] ? $educationDetails : null,
            'personal_info' => $personalDetails !== [] ? $personalDetails : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentContactAttributes(Student $student, array $validated): array
    {
        return [
            'student_id' => $student->id,
            'personal_contact' => ($validated['personal_contact'] ?? null) ?: ($validated['phone'] ?? null),
            'facebook_contact' => $validated['facebook_contact'] ?? null,
            'facebook' => $validated['facebook_contact'] ?? null,
            'twitter' => $validated['twitter'] ?? null,
            'instagram' => $validated['instagram'] ?? null,
            'linkedin' => $validated['linkedin'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'emergency_contact_address' => $validated['emergency_contact_address'] ?? null,
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentParentInfoAttributes(array $validated): array
    {
        return [
            'fathers_name' => $validated['fathers_name'] ?? null,
            'father_name' => $validated['fathers_name'] ?? null,
            'father_occupation' => $validated['father_occupation'] ?? null,
            'father_contact' => $validated['father_contact'] ?? null,
            'father_email' => $validated['father_email'] ?? null,
            'mothers_name' => $validated['mothers_name'] ?? null,
            'mother_name' => $validated['mothers_name'] ?? null,
            'mother_occupation' => $validated['mother_occupation'] ?? null,
            'mother_contact' => $validated['mother_contact'] ?? null,
            'mother_email' => $validated['mother_email'] ?? null,
            'guardian_name' => $validated['guardian_name'] ?? ($validated['emergency_contact_name'] ?? null),
            'guardian_relationship' => $validated['guardian_relationship'] ?? ($validated['emergency_contact_relationship'] ?? null),
            'guardian_contact' => $validated['guardian_contact'] ?? ($validated['emergency_contact_phone'] ?? null),
            'guardian_email' => $validated['guardian_email'] ?? null,
            'family_address' => $validated['family_address'] ?? ($validated['emergency_contact_address'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentEducationInfoAttributes(array $validated): array
    {
        return [
            'elementary_school' => $validated['elementary_school'] ?? null,
            'elementary_graduate_year' => $validated['elementary_graduate_year'] ?? null,
            'elementary_year_graduated' => $validated['elementary_graduate_year'] ?? null,
            'elementary_school_address' => $validated['elementary_school_address'] ?? null,
            'junior_high_school_name' => $validated['junior_high_school_name'] ?? null,
            'high_school' => $validated['junior_high_school_name'] ?? null,
            'junior_high_graduation_year' => $validated['junior_high_graduation_year'] ?? null,
            'high_school_year_graduated' => $validated['junior_high_graduation_year'] ?? null,
            'junior_high_school_address' => $validated['junior_high_school_address'] ?? null,
            'senior_high_name' => $validated['senior_high_name'] ?? null,
            'senior_high_school' => $validated['senior_high_name'] ?? null,
            'senior_high_graduate_year' => $validated['senior_high_graduate_year'] ?? null,
            'senior_high_year_graduated' => $validated['senior_high_graduate_year'] ?? null,
            'senior_high_address' => $validated['senior_high_address'] ?? null,
            'college_school' => $validated['college_school'] ?? null,
            'college_course' => $validated['college_course'] ?? null,
            'college_year_graduated' => $validated['college_year_graduated'] ?? null,
            'vocational_school' => $validated['vocational_school'] ?? null,
            'vocational_course' => $validated['vocational_course'] ?? null,
            'vocational_year_graduated' => $validated['vocational_year_graduated'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentPersonalInfoAttributes(array $validated): array
    {
        $citizenship = $validated['citizenship'] ?? ($validated['nationality'] ?? null);

        return [
            'birthplace' => $validated['birthplace'] ?? null,
            'place_of_birth' => $validated['birthplace'] ?? null,
            'civil_status' => $validated['civil_status'] ?? null,
            'citizenship' => $citizenship,
            'religion' => $validated['religion'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'current_adress' => $validated['current_address'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, brackets: array<int, array{value: string, label: string}>}>
     */
    private function getIncomeModeOptions(): array
    {
        $currency = app(SiteSettings::class)->getCurrency();
        $currencySymbol = match ($currency) {
            'PHP' => '₱',
            'USD' => '$',
            default => $currency,
        };

        return collect(config('income_brackets.modes', []))
            ->map(function (array $modeConfig, string $modeKey) use ($currencySymbol): array {
                $brackets = collect($modeConfig['brackets'] ?? [])
                    ->map(fn (array $bracket, string $key): array => [
                        'value' => $key,
                        'label' => str_replace('{symbol}', $currencySymbol, (string) ($bracket['label'] ?? '')),
                    ])
                    ->values()
                    ->all();

                return [
                    'value' => $modeKey,
                    'label' => (string) ($modeConfig['label'] ?? ucfirst($modeKey)),
                    'brackets' => $brackets,
                ];
            })
            ->values()
            ->all();
    }

    private function getFormOptions(CurriculumCapabilityResolver $capabilityResolver, ?Student $student = null, bool $includeSubjects = true): array
    {
        $schoolId = $student?->school_id ?? app(TenantContext::class)->getCurrentSchoolId();
        $configService = app(StudentConfigurationService::class);
        $configuration = $configService->get($schoolId);
        $schoolProfile = $configService->schoolProfile($schoolId);
        $documentService = app(StudentDocumentService::class);
        $enabledTypes = $configuration['enabled_types'];
        if ($student?->student_type !== null) {
            $enabledTypes[] = $student->student_type->value;
        }

        $documentChecklists = [];
        foreach (array_unique($enabledTypes) as $typeValue) {
            $documentChecklists[$typeValue] = $documentService->checklistFor((string) $typeValue, $schoolId);
        }

        return [
            'student_configuration' => $configuration,
            'school_profile' => $schoolProfile,
            'document_checklists' => $documentChecklists,
            'configuration_scope' => StudentConfigurationService::scope($schoolId),
            'identifier_policy' => app(IdentifierGenerator::class)->studentPolicy(schoolId: $schoolId),
            'types' => collect(StudentType::cases())->filter(fn ($type): bool => in_array($type->value, $enabledTypes, true))->map(fn ($type): array => ['value' => $type->value, 'label' => $configuration['type_labels'][$type->value]])->values()->all(),
            'statuses' => collect(StudentStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'scholarship_types' => collect(ScholarshipType::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'employment_statuses' => collect(EmploymentStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'attrition_categories' => collect(AttritionCategory::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'courses' => Course::with('department:id,code,name,is_active')
                ->get(['id', 'code', 'title', 'is_active', 'department_id', 'curriculum_kind', 'qualification_level', 'duration_hours', 'tesda_program_type', 'duration_years', 'internship_hours', 'bundled_qualifications'])
                ->sortBy([['is_active', 'desc'], ['code', 'asc']])
                ->groupBy(function (Course $course) use ($capabilityResolver): string {
                    if ($capabilityResolver->isTesdaCourse($course)) {
                        return 'TESDA';
                    }

                    return $course->department?->code ?? 'UNASSIGNED';
                })
                ->sortKeys()
                ->map(function ($courses, string $departmentCode) use ($capabilityResolver): array {
                    $department = $courses->first()->department;
                    $isTesda = $capabilityResolver->isTesdaCourse($courses->first());
                    $label = $isTesda
                        ? 'TESDA — Technical Qualifications'
                        : ($department
                        ? "{$department->code} — {$department->name}".($department->is_active ? '' : ' (Inactive)')
                        : 'Unassigned');

                    return [
                        'label' => $label,
                        'pathway' => $isTesda ? 'tesda_qualification' : 'program',
                        'items' => $courses->map(fn (Course $c): array => [
                            'value' => $c->id,
                            'label' => $c->code.' - '.$c->title.($c->is_active ? '' : ' (Inactive)'),
                            'is_active' => $c->is_active,
                            'curriculum_kind' => $capabilityResolver->kindForCourse($c),
                            'qualification_level' => $c->qualification_level,
                            'duration_hours' => $c->duration_hours,
                            'tesda_program_type' => $c->tesda_program_type,
                            'duration_years' => $c->duration_years,
                            'internship_hours' => $c->internship_hours,
                            'bundled_qualifications' => $c->bundled_qualifications,
                        ])->values()->all(),
                    ];
                })
                ->values()
                ->all(),
            'shs_strands' => ShsStrand::all(['id', 'strand_name'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->strand_name])->all(),
            'religions' => Student::query()
                ->whereNotNull('religion')
                ->where('religion', '!=', '')
                ->distinct()
                ->orderBy('religion')
                ->pluck('religion')
                ->map(fn (string $religion): string => mb_trim($religion))
                ->filter()
                ->unique()
                ->values()
                ->map(fn (string $religion): array => [
                    'value' => $religion,
                    'label' => $religion,
                ])
                ->all(),
            'regions' => $this->getPhilippineRegions(),
            'income_modes' => $this->getIncomeModeOptions(),
            'default_income_mode' => (string) config('income_brackets.default_mode', 'annual'),
            'subjects' => $includeSubjects
                ? Subject::query()
                    ->select(['id', 'code', 'title', 'units'])
                    ->orderBy('code')
                    ->toBase()
                    ->get()
                    ->map(fn (object $s): array => [
                        'value' => (int) $s->id,
                        'label' => "{$s->code} - {$s->title} ({$s->units} units)",
                    ])
                    ->all()
                : [],
        ];
    }

    private function resolveStudentSignatureUrl(Student $student): ?string
    {
        $path = $student->signature_path;

        if (! is_string($path) || mb_trim($path) === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, '/')) {
            return $path;
        }

        return route('administrators.students.signature.show', $student->id);
    }

    private function deleteStoredSignature(?string $path): void
    {
        if (
            ! is_string($path)
            || $path === ''
            || filter_var($path, FILTER_VALIDATE_URL)
            || str_starts_with($path, '/')
        ) {
            return;
        }

        $candidateDisks = array_values(array_unique(array_filter([
            (string) config('filesystems.private_disk', 'private'),
            is_string(config('filesystems.default')) ? (string) config('filesystems.default') : null,
            'public',
        ])));

        foreach ($candidateDisks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    private function resolveStoredFileUrl(?string $path): ?string
    {
        if (! is_string($path) || mb_trim($path) === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, '/')) {
            return $path;
        }

        $disk = config('filesystems.default');

        if (! is_string($disk)) {
            return null;
        }

        return Storage::disk($disk)->url($path);
    }

    private function getPhilippineRegions(): array
    {
        return [
            ['value' => 'NCR', 'label' => 'National Capital Region (NCR)'],
            ['value' => 'CAR', 'label' => 'Cordillera Administrative Region (CAR)'],
            ['value' => 'Region I', 'label' => 'Region I - Ilocos Region'],
            ['value' => 'Region II', 'label' => 'Region II - Cagayan Valley'],
            ['value' => 'Region III', 'label' => 'Region III - Central Luzon'],
            ['value' => 'Region IV-A', 'label' => 'Region IV-A - CALABARZON'],
            ['value' => 'Region IV-B', 'label' => 'Region IV-B - MIMAROPA'],
            ['value' => 'Region V', 'label' => 'Region V - Bicol Region'],
            ['value' => 'Region VI', 'label' => 'Region VI - Western Visayas'],
            ['value' => 'Region VII', 'label' => 'Region VII - Central Visayas'],
            ['value' => 'Region VIII', 'label' => 'Region VIII - Eastern Visayas'],
            ['value' => 'Region IX', 'label' => 'Region IX - Zamboanga Peninsula'],
            ['value' => 'Region X', 'label' => 'Region X - Northern Mindanao'],
            ['value' => 'Region XI', 'label' => 'Region XI - Davao Region'],
            ['value' => 'Region XII', 'label' => 'Region XII - SOCCSKSARGEN'],
            ['value' => 'Region XIII', 'label' => 'Region XIII - Caraga'],
            ['value' => 'BARMM', 'label' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)'],
        ];
    }

    private function getUserProps(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }
}
