<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Enrollment\EnrollmentCancellationService;
use App\Enrollment\EnrollmentDiscountCalculation;
use App\Enrollment\EnrollmentFeeCalculator;
use App\Enrollment\EnrollmentFeePolicy;
use App\Enrollment\EnrollmentPolicyResolver;
use App\Enrollment\EnrollmentTransitionEngine;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Enums\ApplicantCancellationReason;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Exceptions\AssessmentExportLimitReached;
use App\Exports\EnrollmentReportExport;
use App\Features\DynamicEnrollmentPolicies;
use App\Http\Controllers\Api\V1\ApiPagination;
use App\Http\Requests\Administrators\CancelApplicantRequest;
use App\Http\Requests\Administrators\GenerateBulkAssessmentsRequest;
use App\Http\Requests\Administrators\SaveEnrollmentRequest;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Jobs\GenerateEnrollmentReportPreviewPdfJob;
use App\Jobs\SendClassChangeNotificationJob;
use App\Models\AdditionalFee;
use App\Models\ApplicantCancellation;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentClassWaitlist;
use App\Models\EnrollmentDiscount;
use App\Models\EnrollmentLoadChange;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentRequirement;
use App\Models\EnrollmentTransferLog;
use App\Models\GeneralSetting;
use App\Models\PaymentAllocation;
use App\Models\Resource;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ApplicantApprovedForRequirements;
use App\Services\ApplicantCancellationService;
use App\Services\ApplicantDirectoryService;
use App\Services\AssessmentExportPayloadService;
use App\Services\AssessmentFormDataService;
use App\Services\ClassScheduleChangeNotificationService;
use App\Services\CurriculumCapabilityResolver;
use App\Services\EnrollmentBillingService;
use App\Services\EnrollmentLoadChangeService;
use App\Services\EnrollmentPeriodService;
use App\Services\EnrollmentPipelineService;
use App\Services\EnrollmentService;
use App\Services\GeneralSettingsService;
use App\Services\PdfGenerationService;
use App\Services\QueueAssessmentExportService;
use App\Services\StudentChecklistService;
use App\Services\StudentDirectoryQuery;
use App\Services\StudentHoldService;
use App\Services\TenantContext;
use App\Services\TuitionAdjustmentRecalculationService;
use App\Settings\SiteSettings;
use App\Support\ScheduleDelivery;
use Closure;
use Exception;
use FPDF;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AdministratorEnrollmentManagementController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService,
        private readonly EnrollmentPipelineService $enrollmentPipelineService,
        private readonly EnrollmentBillingService $enrollmentBillingService,
        private readonly ClassScheduleChangeNotificationService $classScheduleChangeNotificationService,
        private readonly EnrollmentWorkflowCoordinator $workflowCoordinator,
        private readonly TuitionAdjustmentRecalculationService $tuitionAdjustmentRecalculation,
        private readonly EnrollmentTransitionEngine $transitionEngine,
        private readonly EnrollmentCancellationService $enrollmentCancellationService,
        private readonly EnrollmentFeeCalculator $feeCalculator,
        private readonly EnrollmentFeePolicy $feePolicy,
        private readonly ApplicantDirectoryService $applicantDirectory,
        private readonly ApplicantCancellationService $applicantCancellation,
        private readonly EnrollmentPeriodService $enrollmentPeriodService,
        private readonly StudentHoldService $studentHoldService,
    ) {}

    public function index(GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $resolvedPipeline = $this->resolveActivePipelinePayload($user);
        $workflowSetupRequired = ! $this->enrollmentPipelineService->hasWorkflowSetup();
        if ($workflowSetupRequired) {
            $scheme = request()->getScheme();
            $adminHost = (string) config('app.admin_host');
            $filamentBaseUrl = sprintf('%s://%s/admin/student-enrollments', $scheme, $adminHost);

            return Inertia::render('administrators/enrollments/index', [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->getFilamentAvatarUrl(),
                    'role' => $user->role?->getLabel() ?? 'Administrator',
                ],
                'filament' => [
                    'student_enrollments' => [
                        'index_url' => $filamentBaseUrl,
                        'create_url' => $filamentBaseUrl.'/create',
                    ],
                ],
                'applicantsCount' => 0,
                'enrollments' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'next_page_url' => null,
                    'prev_page_url' => null,
                    'from' => 0,
                    'to' => 0,
                ],
                'analytics' => [
                    'current_semester_count' => 0,
                    'current_school_year_count' => 0,
                    'previous_semester_count' => 0,
                    'by_department' => [],
                    'by_year_level' => [],
                    'trashed_count' => 0,
                    'active_count' => 0,
                    'by_status' => [],
                    'unpaid_balance_total' => 0.0,
                    'unpaid_count' => 0,
                    'in_pipeline_count' => 0,
                    'completed_count' => 0,
                ],
                'board_enrollments' => [],
                'permissions' => $this->enrollmentIndexPermissions($user),
                'pipeline_meta' => $resolvedPipeline['pipeline_meta'],
                'enrollment_period' => $this->enrollmentPeriodService->describe(),
                'flash' => session('flash'),
                'filters' => [
                    'search' => null,
                    'per_page' => 10,
                    'status_filter' => 'all',
                    'department_filter' => 'all',
                    'year_level_filter' => 'all',
                    'course_filter' => 'all',
                    'payment_filter' => 'all',
                    'currentSemester' => $settingsService->getCurrentSemester(),
                    'currentSchoolYear' => $settingsService->getCurrentSchoolYearStart(),
                    'systemSemester' => $settingsService->getSystemDefaultSemester(),
                    'systemSchoolYear' => $settingsService->getSystemDefaultSchoolYearStart(),
                    'availableSemesters' => $settingsService->getAvailableSemesters(),
                    'availableSchoolYears' => $settingsService->getAvailableSchoolYears(),
                ],
                'enrollment_pipeline' => $resolvedPipeline['enrollment_pipeline'],
                'enrollment_stats' => $this->enrollmentPipelineService->getStatsConfiguration(),
                'assessment_export_options' => [
                    'student_limits' => config('assessment-exports.student_limit_options'),
                ],
                'workflow_setup_required' => true,
            ]);
        }

        // Get scoped settings
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYearStart = $settingsService->getCurrentSchoolYearStart();
        $currentSchoolYearString = $settingsService->getCurrentSchoolYearString();

        // Calculate Analytics
        $previousSemester = $currentSemester === 1 ? 2 : 1;
        $previousSchoolYearStart = $currentSemester === 1 ? $currentSchoolYearStart - 1 : $currentSchoolYearStart;
        $previousSchoolYearString = $previousSchoolYearStart.' - '.($previousSchoolYearStart + 1);

        $pendingStatus = $this->enrollmentPipelineService->getPendingStatus();

        // Cancelled or rejected enrollments keep their last reporting status, so enrolment
        // counts have to exclude the abandoned outcomes explicitly.
        $excludingAbandoned = static fn ($query) => $query->where(function ($outcomes): void {
            $outcomes->whereNull('terminal_outcome')
                ->orWhereNotIn('terminal_outcome', StudentDirectoryQuery::ABANDONED_TERMINAL_OUTCOMES);
        });

        $enrolledThisSemester = fn () => $excludingAbandoned(StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('semester', $currentSemester)
            ->where('status', '!=', $pendingStatus))
            ->count();

        $enrolledThisSchoolYear = fn () => $excludingAbandoned(StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('status', '!=', $pendingStatus))
            ->count();

        $enrolledPreviousSemester = fn () => $excludingAbandoned(StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $previousSchoolYearString)
            ->where('semester', $previousSemester)
            ->where('status', '!=', $pendingStatus))
            ->count();

        $enrolledByDepartment = function () use ($excludingAbandoned, $currentSchoolYearString, $currentSemester, $pendingStatus): Collection {
            $query = StudentEnrollment::query()
                ->withTrashed()
                ->where('student_enrollment.school_year', $currentSchoolYearString)
                ->where('student_enrollment.semester', $currentSemester)
                ->where('student_enrollment.status', '!=', $pendingStatus);

            return $excludingAbandoned($query)
                ->join('courses', 'student_enrollment.course_id', '=', 'courses.id')
                ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                ->selectRaw('TRIM(departments.code) as department, count(*) as count')
                ->groupByRaw('TRIM(departments.code)')
                ->get();
        };

        $enrolledByYearLevel = fn () => $excludingAbandoned(StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('semester', $currentSemester)
            ->where('status', '!=', $pendingStatus))
            ->selectRaw('academic_year as year_level, count(*) as count')
            ->groupBy('academic_year')
            ->get();

        $trashedCount = fn () => StudentEnrollment::query()
            ->onlyTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('semester', $currentSemester)
            ->count();

        $activeCount = fn () => StudentEnrollment::query()
            ->where('school_year', $currentSchoolYearString)
            ->where('semester', $currentSemester)
            ->count();

        // Get enrollment status breakdown
        $enrollmentByStatus = fn () => StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('semester', $currentSemester)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        $applicantsCount = fn (): int => Student::query()
            ->withTrashed()
            ->where('status', StudentStatus::Applicant)
            ->count();

        $completionStatuses = collect($resolvedPipeline['enrollment_pipeline']['steps'] ?? [])
            ->filter(fn (array $step): bool => (bool) ($step['is_completion'] ?? false))
            ->pluck('status')
            ->merge([
                $resolvedPipeline['enrollment_pipeline']['cashier_verified_status'] ?? null,
                'Enrolled',
                'Completed',
            ])
            ->filter(fn (mixed $status): bool => is_string($status) && $status !== '')
            ->unique()
            ->values()
            ->all();

        $unpaidSummary = function () use ($excludingAbandoned, $currentSchoolYearString, $currentSemester): array {
            $row = $excludingAbandoned(StudentEnrollment::query()
                ->where('student_enrollment.school_year', $currentSchoolYearString)
                ->where('student_enrollment.semester', $currentSemester))
                ->join('student_tuition', 'student_tuition.enrollment_id', '=', 'student_enrollment.id')
                ->whereNull('student_tuition.deleted_at')
                ->where('student_tuition.total_balance', '>', 0)
                ->selectRaw('COUNT(*) as unpaid_count, COALESCE(SUM(student_tuition.total_balance), 0) as unpaid_total')
                ->first();

            return [
                'unpaid_count' => (int) ($row->unpaid_count ?? 0),
                'unpaid_balance_total' => (float) ($row->unpaid_total ?? 0),
            ];
        };

        $pipelineCounts = function () use ($excludingAbandoned, $currentSchoolYearString, $currentSemester, $completionStatuses): array {
            $base = $excludingAbandoned(StudentEnrollment::query()
                ->where('school_year', $currentSchoolYearString)
                ->where('semester', $currentSemester));

            $completedCount = (clone $base)->whereIn('status', $completionStatuses)->count();
            $inPipelineCount = (clone $base)->where(function ($query) use ($completionStatuses): void {
                $query->whereNull('status')->orWhereNotIn('status', $completionStatuses);
            })->count();

            return [
                'completed_count' => $completedCount,
                'in_pipeline_count' => $inPipelineCount,
            ];
        };

        $search = request('search');
        $statusFilter = request('status_filter', 'all');
        $departmentFilter = request('department_filter', 'all');
        $yearLevelFilter = request('year_level_filter', 'all');
        $courseFilter = request('course_filter', 'all');
        $paymentFilter = request('payment_filter', 'all');
        $sort = request('sort', 'created_at');
        $direction = request('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $perPageInput = request('per_page', ApiPagination::defaultPerPage());
        $perPage = ApiPagination::perPage($perPageInput);

        $buildFilteredQuery = function (bool $includeStatusFilter = true) use (
            $currentSchoolYearString,
            $currentSemester,
            $statusFilter,
            $departmentFilter,
            $yearLevelFilter,
            $courseFilter,
            $paymentFilter,
            $search
        ): Builder {
            return StudentEnrollment::query()
                ->withTrashed()
                ->where('student_enrollment.school_year', $currentSchoolYearString)
                ->where('student_enrollment.semester', $currentSemester)
                ->when($includeStatusFilter && $statusFilter && $statusFilter !== 'all', function ($query) use ($statusFilter) {
                    if ($statusFilter === 'active') {
                        $query->whereNull('student_enrollment.deleted_at');
                    } elseif ($statusFilter === 'trashed') {
                        $query->onlyTrashed();
                    } elseif ($statusFilter === 'cancelled') {
                        // Cancellation is a terminal outcome, not a reporting status.
                        $query->where('student_enrollment.terminal_outcome', EnrollmentCancellationService::OUTCOME);
                    } else {
                        $query->where('student_enrollment.status', $statusFilter);
                    }
                })
                ->when($departmentFilter && $departmentFilter !== 'all', function ($query) use ($departmentFilter) {
                    $query->whereHas('course.department', fn ($q) => $q->where('code', $departmentFilter));
                })
                ->when($yearLevelFilter && $yearLevelFilter !== 'all', function ($query) use ($yearLevelFilter) {
                    $query->where('student_enrollment.academic_year', (int) $yearLevelFilter);
                })
                ->when($courseFilter && $courseFilter !== 'all', function ($query) use ($courseFilter) {
                    $query->where('student_enrollment.course_id', (int) $courseFilter);
                })
                ->when($paymentFilter === 'unpaid', function ($query) {
                    $query->whereHas('studentTuition', fn ($q) => $q->where('total_balance', '>', 0));
                })
                ->when($paymentFilter === 'paid', function ($query) {
                    $query->where(function ($q): void {
                        $q->whereDoesntHave('studentTuition')
                            ->orWhereHas('studentTuition', fn ($tq) => $tq->where('total_balance', '<=', 0));
                    });
                })
                ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search) {
                    $searchTerm = mb_trim($search);
                    $like = "%{$searchTerm}%";
                    $query->where(function ($q) use ($like) {
                        $q->whereHas('student', function ($sq) use ($like) {
                            $sq->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('student_id', 'like', $like)
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                                ->orWhereRaw("CONCAT(last_name, ', ', first_name) LIKE ?", [$like]);
                        })
                            ->orWhereHas('course', fn ($sq) => $sq->where('code', 'like', $like)->orWhere('title', 'like', $like))
                            ->orWhere('student_enrollment.status', 'like', $like);
                    });
                })
                ->with(['student.Course', 'course.department', 'studentTuition', 'policySnapshot'])
                ->withCount('subjectsEnrolled');
        };

        $enrollments = function () use ($buildFilteredQuery, $sort, $direction, $perPage, $user) {
            $paginator = $buildFilteredQuery(true)
                ->when($sort === 'student_name', function ($query) use ($direction) {
                    $query->orderBy(
                        Student::select('last_name')
                            ->whereColumn('students.id', 'student_enrollment.student_id')
                            ->limit(1),
                        $direction
                    );
                }, function ($query) use ($sort, $direction) {
                    if ($sort === 'tuition') {
                        $query->orderBy(
                            StudentTuition::select('overall_tuition')
                                ->whereColumn('student_tuition.enrollment_id', 'student_enrollment.id')
                                ->limit(1),
                            $direction
                        );
                    } elseif ($sort === 'created_at') {
                        $query->orderBy('student_enrollment.created_at', $direction)
                            ->orderBy('student_enrollment.id', $direction);
                    } else {
                        $query->orderByDesc('student_enrollment.created_at')
                            ->orderByDesc('student_enrollment.id');
                    }
                })
                ->paginate($perPage, ['*'], 'page', (int) request('page', 1))
                ->withQueryString();

            $pageHoldSummaries = $this->studentHoldService->summariesForStudents(
                $paginator->getCollection()->pluck('student_id')->filter()->map(fn ($id): int => (int) $id)->values()->all(),
            );
            $paginator->setCollection(
                $paginator->getCollection()->map(
                    fn (StudentEnrollment $enrollment): array => $this->serializeEnrollmentRow($enrollment, $user, $pageHoldSummaries[(int) $enrollment->student_id] ?? null),
                ),
            );

            return $paginator;
        };

        $boardEnrollments = function () use ($buildFilteredQuery, $user): array {
            $rows = $buildFilteredQuery(false)
                ->whereNull('student_enrollment.deleted_at')
                ->where(function ($outcomes): void {
                    $outcomes->whereNull('student_enrollment.terminal_outcome')
                        ->orWhereNotIn('student_enrollment.terminal_outcome', StudentDirectoryQuery::ABANDONED_TERMINAL_OUTCOMES);
                })
                ->orderByDesc('student_enrollment.created_at')
                ->orderByDesc('student_enrollment.id')
                ->limit(150)
                ->get();

            $boardHoldSummaries = $this->studentHoldService->summariesForStudents(
                $rows->pluck('student_id')->filter()->map(fn ($id): int => (int) $id)->values()->all(),
            );

            return $rows
                ->map(fn (StudentEnrollment $enrollment): array => $this->serializeEnrollmentRow($enrollment, $user, $boardHoldSummaries[(int) $enrollment->student_id] ?? null))
                ->values()
                ->all();
        };

        $scheme = request()->getScheme();
        $adminHost = (string) config('app.admin_host');
        $filamentBaseUrl = sprintf('%s://%s/admin/student-enrollments', $scheme, $adminHost);

        return Inertia::render('administrators/enrollments/index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'filament' => [
                'student_enrollments' => [
                    'index_url' => $filamentBaseUrl,
                    'create_url' => $filamentBaseUrl.'/create',
                ],
            ],
            'applicantsCount' => $applicantsCount,
            'enrollments' => $enrollments,
            'board_enrollments' => $boardEnrollments,
            'permissions' => $this->enrollmentIndexPermissions($user),
            'pipeline_meta' => $resolvedPipeline['pipeline_meta'],
            'analytics' => function () use (
                $enrolledThisSemester,
                $enrolledThisSchoolYear,
                $enrolledPreviousSemester,
                $enrolledByDepartment,
                $enrolledByYearLevel,
                $trashedCount,
                $activeCount,
                $enrollmentByStatus,
                $unpaidSummary,
                $pipelineCounts
            ): array {
                $unpaid = $unpaidSummary();
                $pipeline = $pipelineCounts();

                return [
                    'current_semester_count' => $enrolledThisSemester instanceof Closure ? $enrolledThisSemester() : $enrolledThisSemester,
                    'current_school_year_count' => $enrolledThisSchoolYear instanceof Closure ? $enrolledThisSchoolYear() : $enrolledThisSchoolYear,
                    'previous_semester_count' => $enrolledPreviousSemester instanceof Closure ? $enrolledPreviousSemester() : $enrolledPreviousSemester,
                    'by_department' => $enrolledByDepartment instanceof Closure ? $enrolledByDepartment() : $enrolledByDepartment,
                    'by_year_level' => $enrolledByYearLevel instanceof Closure ? $enrolledByYearLevel() : $enrolledByYearLevel,
                    'trashed_count' => $trashedCount instanceof Closure ? $trashedCount() : $trashedCount,
                    'active_count' => $activeCount instanceof Closure ? $activeCount() : $activeCount,
                    'by_status' => $enrollmentByStatus instanceof Closure ? $enrollmentByStatus() : $enrollmentByStatus,
                    'unpaid_balance_total' => $unpaid['unpaid_balance_total'],
                    'unpaid_count' => $unpaid['unpaid_count'],
                    'in_pipeline_count' => $pipeline['in_pipeline_count'],
                    'completed_count' => $pipeline['completed_count'],
                ];
            },
            'flash' => session('flash'),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'page' => (int) request('page', 1),
                'status_filter' => $statusFilter,
                'department_filter' => $departmentFilter,
                'year_level_filter' => $yearLevelFilter,
                'course_filter' => $courseFilter,
                'payment_filter' => $paymentFilter,
                'currentSemester' => $currentSemester,
                'currentSchoolYear' => $currentSchoolYearStart,
                'systemSemester' => $settingsService->getSystemDefaultSemester(),
                'systemSchoolYear' => $settingsService->getSystemDefaultSchoolYearStart(),
                'availableSemesters' => $settingsService->getAvailableSemesters(),
                'availableSchoolYears' => $settingsService->getAvailableSchoolYears(),
            ],
            'available_courses' => fn () => Course::query()
                ->orderBy('code')
                ->get(['id', 'code', 'title'])
                ->map(fn (Course $c): array => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'title' => $c->title,
                ])
                ->values(),
            'enrollment_pipeline' => $resolvedPipeline['enrollment_pipeline'],
            'enrollment_period' => $this->enrollmentPeriodService->describe($currentSchoolYearString, $currentSemester),
            'enrollment_stats' => $this->enrollmentPipelineService->getStatsConfiguration(),
            'assessment_export_options' => [
                'student_limits' => config('assessment-exports.student_limit_options'),
            ],
            'workflow_setup_required' => false,
        ]);
    }

    public function applicants(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $filters = $this->applicantDirectory->normalizeFilters($request->query());

        $applicants = $this->applicantDirectory->paginate($filters);

        return Inertia::render('administrators/enrollments/applicants', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'applicants' => $applicants,
            'filters' => Arr::except($filters, ['page']),
            'stats' => $this->applicantDirectory->stats($filters),
            'stage_counts' => $this->applicantDirectory->stageCounts($filters),
            'stage_meta' => $this->applicantDirectory->stageMeta(),
            'filter_options' => $this->applicantDirectory->filterOptions(),
            'cancellation_reasons' => $this->applicantCancellation->reasons(),
            'enrollment_pipeline' => $this->resolveActivePipelinePayload($user)['enrollment_pipeline'],
            'flash' => session('flash'),
        ]);
    }

    /**
     * Full applicant dossier backing the review drawer.
     */
    public function applicant(Request $request, Student $student): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        abort_if(! in_array($student->status, [StudentStatus::Applicant, StudentStatus::Rejected], true), 404);

        return response()->json($this->applicantDirectory->detail($student));
    }

    /**
     * The email that would be sent for a given reason, without sending it.
     *
     * The registrar sees the finished message in the dialog before confirming,
     * so nothing reaches a student that was not read first.
     */
    public function previewApplicantCancellation(Request $request, Student $student): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $reason = ApplicantCancellationReason::tryFrom((string) $request->query('reason', ''));

        if (! $reason instanceof ApplicantCancellationReason) {
            abort(422, 'Select a valid reason.');
        }

        abort_if($student->status !== StudentStatus::Applicant, 404);

        return response()->json($this->applicantCancellation->preview($student, $reason, $request->query('note')));
    }

    /**
     * Close an application and notify the applicant.
     */
    public function cancelApplicant(
        CancelApplicantRequest $request,
        Student $student,
        ApplicantCancellationService $applicantCancellation,
    ): RedirectResponse {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $reason = ApplicantCancellationReason::from($request->validated('reason'));

        try {
            $cancellation = $applicantCancellation->cancel(
                student: $student,
                actor: $user,
                reason: $reason,
                note: $request->validated('note'),
                sendEmail: $request->boolean('send_email', true),
            );
        } catch (RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        $notified = $cancellation->email_sent_at !== null;

        return back()->with('flash', [
            'success' => $notified
                ? sprintf('Application cancelled and %s was notified by email.', $student->full_name)
                : sprintf('Application cancelled for %s. No email was sent.', $student->full_name),
        ]);
    }

    /**
     * Reverse a cancellation and return the application to the queue.
     */
    public function revertApplicantCancellation(
        Request $request,
        Student $student,
        ApplicantCancellationService $applicantCancellation,
    ): RedirectResponse {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $cancellation = $applicantCancellation->activeFor($student);

        if (! $cancellation instanceof ApplicantCancellation) {
            return back()->with('flash', ['error' => 'There is no active cancellation to reverse for this applicant.']);
        }

        try {
            $applicantCancellation->revert($cancellation, $user, $request->input('reason'));
        } catch (RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        return back()->with('flash', [
            'success' => sprintf('The cancellation for %s was reversed and the application is back in the queue.', $student->full_name),
        ]);
    }

    /**
     * Stream a requirement document that an applicant uploaded online.
     */
    public function applicantDocument(Request $request, Student $student, EnrollmentRequirement $requirement): StreamedResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        abort_if(! in_array($student->status, [StudentStatus::Applicant, StudentStatus::Rejected], true), 404);

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        abort_if(! $enrollment instanceof StudentEnrollment, 404);
        abort_unless((int) $requirement->student_enrollment_id === (int) $enrollment->id, 404);

        $path = (string) ($requirement->evidence_path ?? '');
        abort_if($path === '', 404);

        abort_unless(Storage::disk('private')->exists($path), 404);

        $filename = basename($path);

        return Storage::disk('private')->download($path, $filename);
    }

    public function notifyApplicantApproval(Student $student): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($student->status !== StudentStatus::Applicant) {
            return back()->with('flash', ['error' => 'Only applicant records can be notified.']);
        }

        if ($student->trashed()) {
            return back()->with('flash', ['error' => 'Deleted applicant records cannot be notified.']);
        }

        if (empty($student->email)) {
            return back()->with('flash', ['error' => 'This applicant does not have an email address.']);
        }

        $student->loadMissing('Course.department');
        $student->notify(new ApplicantApprovedForRequirements(
            senderName: $user->name,
            senderRole: $user->role?->getLabel() ?? 'Administrator'
        ));

        return back()->with('flash', [
            'success' => sprintf('Approval email queued for %s.', $student->full_name),
        ]);
    }

    /**
     * Lightweight dossier backing the slide-over inspection sheet on /administrators/enrollments.
     */
    public function summary(int $enrollment): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $record = StudentEnrollment::withTrashed()
            ->with([
                'student.Course',
                'course.department',
                'subjectsEnrolled.subject',
                'studentTuition',
                'additionalFees',
                'resources',
                'requirements',
                'policySnapshot',
                'workflowEvents' => fn ($query) => $query->latest()->limit(12),
            ])
            ->findOrFail($enrollment);

        $enrollmentTransactions = $record->enrollmentTransactions()
            ->with('transaction')
            ->latest()
            ->limit(8)
            ->get();

        $activeClassEnrollments = $record->student
            ? $record->student->classEnrollments()
                ->with(['class'])
                ->where('status', true)
                ->whereHas('class', function ($query) use ($record): void {
                    $query->where('school_year', $record->school_year)
                        ->where('semester', $record->semester);
                })
                ->get()
            : collect();

        $enrolledSubjectCodes = $activeClassEnrollments
            ->pluck('class.subject_code')
            ->filter()
            ->flatMap(fn ($code): array => array_map(trim(...), explode(',', (string) $code)))
            ->map(fn (string $code): string => mb_strtolower($code))
            ->unique()
            ->all();

        $missingClassesCount = $record->subjectsEnrolled
            ->filter(function ($se) use ($enrolledSubjectCodes): bool {
                $code = $se->subject?->code;

                return is_string($code) && $code !== '' && ! in_array(mb_strtolower(mb_trim($code)), $enrolledSubjectCodes, true);
            })
            ->count();

        $tuitionSummary = $record->studentTuition
            ? $this->enrollmentBillingService->toSummaryArray(
                $record->studentTuition,
                (float) $record->additionalFees->sum('amount')
            )
            : null;

        $workflow = $this->workflowView($record, $user);
        $isPolicyRuntime = $record->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1 && $record->policySnapshot !== null;
        $policyTransitions = $isPolicyRuntime && ! $record->trashed() && $record->terminal_outcome !== EnrollmentCancellationService::OUTCOME
            ? $this->resolvePolicyRowTransitions($record, $user)
            : [];

        if ($isPolicyRuntime) {
            $projected = $this->enrollmentPipelineService->getConfigurationFor($record);
            $steps = collect($projected['steps'] ?? [])->map(fn (array $step): array => [
                ...$step,
                'is_completion' => ($projected['completion_step_key'] ?? null) === ($step['key'] ?? null),
            ])->values()->all();
            $primaryTransition = $policyTransitions[0] ?? null;
            $nextStep = $primaryTransition ? [
                'key' => $primaryTransition['to'],
                'status' => $primaryTransition['to_status'],
                'label' => $primaryTransition['label'],
                'action_type' => $primaryTransition['action_type'],
                'transition_key' => $primaryTransition['key'],
                'requires_reason' => $primaryTransition['requires_reason'],
            ] : null;
        } else {
            $nextStep = $this->enrollmentPipelineService->getNextStep($record->status);
            $steps = $this->enrollmentPipelineService->getSteps();
        }
        $indexPermissions = $this->enrollmentIndexPermissions($user);

        // Assemble a concise chronological timeline for the ReUI Timeline component.
        $timeline = collect();

        if ($record->created_at !== null) {
            $timeline->push([
                'id' => 'created-'.$record->id,
                'type' => 'created',
                'title' => 'Enrollment record created',
                'description' => sprintf(
                    '%s · %s (Sem %s)',
                    $record->course?->code ?? 'Program unassigned',
                    $record->school_year ?? 'Current term',
                    (string) ($record->semester ?? '—')
                ),
                'timestamp' => $record->created_at->toISOString(),
                'date_label' => $record->created_at->format('M j, Y · g:i A'),
                'tone' => 'info',
            ]);
        }

        foreach ($record->workflowEvents as $event) {
            $toLabel = $event->status ?: ($event->to_step_key ? Str::headline((string) $event->to_step_key) : 'Workflow updated');
            $timeline->push([
                'id' => 'wf-'.$event->id,
                'type' => 'workflow',
                'title' => $event->terminal_outcome === 'cancelled'
                    ? 'Enrollment cancelled'
                    : sprintf('Moved to %s', $toLabel),
                'description' => $event->reason ?: ($event->from_step_key ? sprintf('Transitioned from %s', Str::headline((string) $event->from_step_key)) : 'Pipeline step recorded'),
                'timestamp' => $event->created_at?->toISOString(),
                'date_label' => $event->created_at?->format('M j, Y · g:i A') ?? '—',
                'tone' => $event->terminal_outcome === 'cancelled' ? 'destructive' : 'success',
            ]);
        }

        foreach ($enrollmentTransactions as $studentTx) {
            $tx = $studentTx->transaction;
            if (! $tx) {
                continue;
            }
            $txAmount = (float) ($studentTx->amount ?? $tx->raw_total_amount ?? $tx->total_amount ?? 0);
            $timeline->push([
                'id' => 'tx-'.$tx->id,
                'type' => 'payment',
                'title' => sprintf('Payment recorded (%s)', $tx->invoicenumber ?: $tx->transaction_number ?: '#'.$tx->id),
                'description' => sprintf('Amount: %s · %s', number_format($txAmount, 2), $tx->description ?: ($tx->status ?? 'Settled')),
                'timestamp' => $tx->created_at?->toISOString(),
                'date_label' => $tx->created_at?->format('M j, Y · g:i A') ?? '—',
                'tone' => 'success',
            ]);
        }

        if ($record->deleted_at !== null) {
            $timeline->push([
                'id' => 'deleted-'.$record->id,
                'type' => 'deleted',
                'title' => 'Moved to trash',
                'description' => 'Soft-deleted enrollment record (can be restored).',
                'timestamp' => $record->deleted_at->toISOString(),
                'date_label' => $record->deleted_at->format('M j, Y · g:i A'),
                'tone' => 'destructive',
            ]);
        }

        $sortedTimeline = $timeline
            ->sortByDesc(fn (array $item): string => (string) ($item['timestamp'] ?? ''))
            ->values()
            ->take(12)
            ->all();

        $totalUnits = $record->subjectsEnrolled->sum(fn ($se): float => (float) ($se->subject?->units ?? 0));

        return response()->json([
            'enrollment' => [
                'id' => $record->id,
                'student_id' => $record->student_id,
                'status' => $record->status ?? 'N/A',
                'terminal_outcome' => $record->terminal_outcome,
                'workflow_runtime' => $record->workflow_runtime,
                'current_step_key' => $record->current_step_key,
                'allows_no_receipt_transition' => $record->allowsConfiguredNoReceiptTransition(),
                'school_year' => $record->school_year,
                'semester' => $record->semester,
                'academic_year' => $record->academic_year,
                'course_code' => $record->course?->code,
                'course_title' => $record->course?->title,
                'department' => $record->course?->department?->code,
                'remarks' => $record->remarks,
                'created_at' => $record->created_at?->toISOString(),
                'deleted_at' => $record->deleted_at?->toISOString(),
                'is_trashed' => $record->trashed(),
                'show_url' => route('administrators.enrollments.show', $record->id),
                'edit_url' => route('administrators.enrollments.edit', $record->id),
                'assessment_download_url' => route('assessment.download', ['record' => $record->id]),
                'assessment_preview_url' => route('administrators.enrollments.assessment-preview', $record->id),
            ],
            'student' => [
                'id' => $record->student?->id,
                'student_number' => $record->student?->student_id,
                'full_name' => $record->student?->full_name ?? 'Unknown Student',
                'email' => $record->student?->email,
                'phone' => $record->student?->phone,
                'course_code' => $record->student?->Course?->code,
                'student_url' => $record->student?->id ? route('administrators.students.show', $record->student->id) : null,
            ],
            'workflow' => [
                ...$workflow,
                'engine' => $isPolicyRuntime ? 'policy_v1' : 'legacy',
                'steps' => $steps,
                'next_step' => $nextStep,
                'allowed_transitions' => $policyTransitions,
            ],
            'permissions' => [
                ...$indexPermissions,
                'can_advance_pipeline' => ! $record->trashed()
                    && $record->terminal_outcome !== EnrollmentCancellationService::OUTCOME
                    && ($isPolicyRuntime
                        ? count($policyTransitions) > 0
                        : $this->enrollmentPipelineService->canUserAdvanceToNextStep($user, $record->status)),
                'can_cancel' => (bool) ($workflow['can_cancel'] ?? false),
                'can_review_requirements' => $user->can('Update:StudentEnrollment') || $user->hasRole('super_admin'),
            ],
            'subjects' => $record->subjectsEnrolled->map(fn ($se): array => [
                'id' => $se->id,
                'code' => $se->subject?->code ?? 'Unknown',
                'title' => $se->subject?->title ?? 'Untitled subject',
                'units' => (float) ($se->subject?->units ?? 0),
                'lecture_fee' => (float) ($se->lecture ?? 0),
                'lab_fee' => (float) ($se->laboratory ?? 0),
            ])->values()->all(),
            'total_units' => $totalUnits,
            'class_enrollments_count' => $activeClassEnrollments->count(),
            'missing_classes_count' => $missingClassesCount,
            'tuition' => $tuitionSummary,
            'transactions' => $enrollmentTransactions->map(function ($studentTransaction): ?array {
                $tx = $studentTransaction->transaction;
                if (! $tx) {
                    return null;
                }

                return [
                    'id' => $tx->id,
                    'invoicenumber' => $tx->invoicenumber ?: $tx->transaction_number,
                    'description' => $tx->description,
                    'status' => $tx->status,
                    'amount' => (float) ($studentTransaction->amount ?? $tx->raw_total_amount ?? $tx->total_amount ?? 0),
                    'created_at' => $tx->created_at?->toISOString(),
                ];
            })->filter()->values()->all(),
            'requirements' => $record->requirements->map(fn ($requirement): array => [
                'id' => $requirement->id,
                'key' => $requirement->requirement_key,
                'label' => $requirement->label,
                'status' => $requirement->status,
                'required' => (bool) $requirement->is_required,
                'verified_at' => $requirement->verified_at?->toISOString(),
                'waived_at' => $requirement->waived_at?->toISOString(),
            ])->values()->all(),
            'holds' => $record->student_id
                ? $this->studentHoldService
                    ->allHoldsForStudent((int) $record->student_id)
                    ->map(fn ($hold): array => $this->studentHoldService->serializeHold($hold))
                    ->values()
                    ->all()
                : [],
            'hold_offices' => $this->studentHoldService->officeOptions(),
            'resources_count' => $record->resources->count(),
            'timeline' => $sortedTimeline,
        ]);
    }

    /**
     * Perform authorized bulk workflow operations on selected enrollment records.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:advance,resend_assessment,delete,restore'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $action = $validated['action'];
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        $records = StudentEnrollment::withTrashed()
            ->with(['policySnapshot'])
            ->whereIn('id', $ids)
            ->get();

        $processed = 0;
        $skipped = 0;

        foreach ($records as $record) {
            try {
                if ($action === 'advance') {
                    if ($record->trashed() || $record->terminal_outcome === EnrollmentCancellationService::OUTCOME) {
                        $skipped++;

                        continue;
                    }

                    if ($record->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1 && $record->policySnapshot !== null) {
                        $transitions = $this->resolvePolicyRowTransitions($record, $user);
                        $eligible = array_values(array_filter(
                            $transitions,
                            fn (array $transition): bool => ! $transition['needs_payment'] && ! $transition['requires_reason']
                        ));

                        if (count($eligible) !== 1) {
                            $skipped++;

                            continue;
                        }

                        $this->workflowCoordinator->transition($record, $user, $eligible[0]['key']);
                        $processed++;

                        continue;
                    }

                    $nextStep = $this->enrollmentPipelineService->getNextStep($record->status);
                    if (
                        $nextStep === null
                        || ($nextStep['action_type'] ?? 'standard') === 'cashier_verification'
                        || ! $this->enrollmentPipelineService->canUserAdvanceToNextStep($user, $record->status)
                    ) {
                        $skipped++;

                        continue;
                    }

                    if (($nextStep['action_type'] ?? 'standard') === 'department_verification') {
                        $this->workflowCoordinator->verifyAcademic($record, $user);
                    } else {
                        $this->workflowCoordinator->transition($record, $user, null);
                    }
                    $processed++;
                } elseif ($action === 'resend_assessment') {
                    if ($record->trashed()) {
                        $skipped++;

                        continue;
                    }

                    $result = $this->enrollmentService->resendAssessmentNotification($record);
                    if ($result['success']) {
                        $processed++;
                    } else {
                        $skipped++;
                    }
                } elseif ($action === 'delete') {
                    if ($record->trashed()) {
                        $skipped++;

                        continue;
                    }

                    $record->delete();
                    $processed++;
                } elseif ($action === 'restore') {
                    if (! $record->trashed()) {
                        $skipped++;

                        continue;
                    }

                    $record->restore();
                    $processed++;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        if ($processed === 0) {
            return back()->with('flash', [
                'error' => match ($action) {
                    'advance' => 'No selected enrollments could be advanced automatically (cashier steps require receipt entry).',
                    'resend_assessment' => 'No selected enrollments were eligible for assessment emails.',
                    'delete' => 'No active enrollments were deleted.',
                    'restore' => 'No deleted enrollments were restored.',
                },
            ]);
        }

        $verb = match ($action) {
            'advance' => 'advanced to the next stage',
            'resend_assessment' => 'queued for assessment email delivery',
            'delete' => 'moved to trash',
            'restore' => 'restored',
        };

        $message = sprintf(
            '%d enrollment%s %s%s.',
            $processed,
            $processed === 1 ? '' : 's',
            $verb,
            $skipped > 0 ? sprintf(' (%d skipped)', $skipped) : ''
        );

        return back()->with('flash', ['success' => $message]);
    }

    public function show(
        Request $request,
        StudentEnrollment $enrollment,
        GeneralSettingsService $settingsService,
        CurriculumCapabilityResolver $capabilityResolver,
        StudentChecklistService $checklistService,
        EnrollmentLoadChangeService $loadChangeService,
    ): Response {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canViewEnrollment($user, $enrollment), 403);

        $enrollment->load([
            'student.Course.department',
            'student.studentContactsInfo',
            'student.personalInfo',
            'course.department',
            'subjectsEnrolled.subject',
            'subjectsEnrolled.class.schedules.room',
            'subjectsEnrolled.class.Faculty',
            'studentTuition',
            'additionalFees',
            'resources',
            'requirements',
            'policySnapshot',
            'workflowEvents',
            'latestInvoice',
            'financialDocuments',
        ]);

        $schedulingPolicy = $settingsService->getSchedulingConfig();
        $availableSemesters = $settingsService->getAvailableSemesters();
        $semesterLabel = $availableSemesters[$enrollment->semester] ?? match ((int) $enrollment->semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            3 => 'Summer',
            default => "Semester {$enrollment->semester}",
        };

        $enrollmentTransactions = $enrollment->enrollmentTransactions()->with('transaction')->get();

        // Active Class Enrollments (eager-loaded with PascalCase Subject/Faculty to prevent N+1)
        $rawActiveClassEnrollments = $enrollment->student
            ? $enrollment->student->classEnrollments()
                ->with(['class.Subject', 'class.Faculty', 'class.schedules.room'])
                ->where('status', true)
                ->whereHas('class', function ($query) use ($enrollment): void {
                    $query->forAcademicPeriod((string) $enrollment->school_year, (int) $enrollment->semester);
                })
                ->get()
            : collect();

        $activeClassEnrollments = $rawActiveClassEnrollments->map(function ($ce) use ($schedulingPolicy): array {
            $class = $ce->class;
            $classDeliveryMode = ScheduleDelivery::canonicalize($class->delivery_mode);

            $structuredSchedules = $class->schedules->map(function ($s) use ($classDeliveryMode, $schedulingPolicy): array {
                $mode = ScheduleDelivery::canonicalize($s->delivery_mode ?? $classDeliveryMode);

                return [
                    'id' => $s->id,
                    'day' => $s->day_of_week,
                    'start_time' => $s->start_time instanceof \Carbon\Carbon ? $s->start_time->format('H:i') : (string) $s->start_time,
                    'end_time' => $s->end_time instanceof \Carbon\Carbon ? $s->end_time->format('H:i') : (string) $s->end_time,
                    'room' => ScheduleDelivery::isOnline($mode) ? ScheduleDelivery::shortLabel($mode) : $s->room?->name,
                    'delivery_mode' => $mode,
                    'checks_time_conflicts' => ScheduleDelivery::checksStudentCohortConflicts($mode, $schedulingPolicy),
                ];
            })->values()->all();

            return [
                'id' => $ce->id,
                'class_id' => $ce->class_id,
                'class_url' => route('administrators.classes.show', $ce->class_id, false),
                'subject_code' => $class->subject_code,
                'subject_title' => $class->subject_title,
                'section' => $class->section,
                'faculty' => $class->Faculty?->full_name ?? 'TBA',
                'delivery_mode' => $classDeliveryMode,
                'schedule' => $class->schedules->map(fn ($s): string => $s->day_of_week.' '.$s->time_range)->implode(', ') ?: 'TBA',
                'schedules' => $structuredSchedules,
                'room' => $class->schedules
                    ->map(function ($s) use ($classDeliveryMode): ?string {
                        $mode = ScheduleDelivery::canonicalize($s->delivery_mode ?? $classDeliveryMode);

                        return ScheduleDelivery::isOnline($mode)
                            ? ScheduleDelivery::shortLabel($mode)
                            : $s->room?->name;
                    })
                    ->filter()
                    ->unique()
                    ->implode(', ') ?: 'TBA',
                'grades' => [
                    'prelim' => $ce->prelim_grade,
                    'midterm' => $ce->midterm_grade,
                    'finals' => $ce->finals_grade,
                    'average' => $ce->total_average,
                ],
                'status' => $ce->status,
            ];
        })->values();

        // Academic Checklist / Advising context
        $checklistBySubjectId = [];
        $checklistSummary = null;
        if ($enrollment->student instanceof Student) {
            try {
                $analysis = $checklistService->analyze($enrollment->student);
                $summary = $analysis['academic_summary'] ?? null;
                if (is_array($summary)) {
                    $checklistSummary = [
                        'progress_percentage' => (float) ($summary['progress_percentage'] ?? 0),
                        'failed_retakes_count' => (int) ($summary['failed_subjects_count'] ?? 0),
                        'completed_subjects_count' => (int) ($summary['completed_subjects_count'] ?? 0),
                        'total_subjects_count' => (int) ($summary['total_subjects'] ?? 0),
                    ];
                }
                foreach (($analysis['curriculum_by_year'] ?? []) as $yearGroup) {
                    foreach (($yearGroup['semesters'] ?? []) as $semGroup) {
                        foreach (($semGroup['subjects'] ?? []) as $item) {
                            $sid = (int) ($item['id'] ?? $item['subject_id'] ?? 0);
                            if ($sid > 0) {
                                $checklistBySubjectId[$sid] = $item;
                            }
                        }
                    }
                }
            } catch (Throwable) {
                // Non-fatal: continue without checklist enrichment if curriculum metadata is incomplete.
            }
        }

        // Map active class enrollments by class_id and subject code for fast subject enrichment
        $classEnrollmentsByClassId = $activeClassEnrollments->keyBy('class_id');
        $enrolledSubjectCodes = $activeClassEnrollments->pluck('subject_code')
            ->map(fn ($code): array => array_map(trim(...), explode(',', (string) $code)))
            ->flatten()
            ->map(fn (string $code): string => mb_strtolower($code))
            ->filter()
            ->unique()
            ->all();

        // Batch-load candidate classes once for all missing subjects (eliminates N+1)
        $unmatchedSubjectEnrollments = $enrollment->subjectsEnrolled->filter(function (SubjectEnrollment $se) use ($enrolledSubjectCodes): bool {
            $subject = $se->subject;
            if (! $subject instanceof Subject) {
                return false;
            }

            return ! in_array(mb_strtolower(mb_trim((string) $subject->code)), $enrolledSubjectCodes, true);
        });

        $termCandidateClasses = $unmatchedSubjectEnrollments->isNotEmpty()
            ? Classes::query()
                ->forAcademicPeriod((string) $enrollment->school_year, (int) $enrollment->semester)
                ->with(['Subject', 'Faculty', 'schedules.room'])
                ->withCount(['class_enrollments' => fn ($query) => $query->where('status', true)])
                ->get()
            : collect();

        $missingClasses = collect();
        foreach ($unmatchedSubjectEnrollments as $subjectEnrollment) {
            $subject = $subjectEnrollment->subject;
            if (! $subject instanceof Subject) {
                continue;
            }

            $subjectCodeLower = mb_strtolower(mb_trim((string) $subject->code));
            $availableClasses = $termCandidateClasses
                ->filter(function (Classes $class) use ($subject, $subjectCodeLower): bool {
                    $courseCodes = is_array($class->course_codes) ? array_map(strval(...), $class->course_codes) : [];
                    if ($subject->course_id !== null && $courseCodes !== [] && ! in_array((string) $subject->course_id, $courseCodes, true)) {
                        return false;
                    }

                    $subjectIds = is_array($class->subject_ids) ? array_map(intval(...), $class->subject_ids) : [];
                    if (in_array((int) $subject->id, $subjectIds, true)) {
                        return true;
                    }

                    $classCodeLower = mb_strtolower(mb_trim((string) $class->subject_code));

                    return $classCodeLower === $subjectCodeLower || str_contains($classCodeLower, $subjectCodeLower);
                })
                ->map(fn (Classes $class): array => [
                    'subject_enrollment_id' => $subjectEnrollment->id,
                    'subject_id' => $subject->id,
                    'class_id' => $class->id,
                    'subject_code' => $subject->code,
                    'subject_title' => $class->subject_title,
                    'section' => $class->section,
                    'faculty' => $class->Faculty?->full_name ?? 'TBA',
                    'schedule' => $class->schedules->map(fn ($s): string => $s->day_of_week.' '.$s->time_range)->implode(', ') ?: 'TBA',
                    'available_slots' => ($class->maximum_slots ?: 0) - ($class->class_enrollments_count ?? 0),
                    'max_slots' => $class->maximum_slots ?: 0,
                    'enrolled_count' => (int) ($class->class_enrollments_count ?? 0),
                    'is_full' => ($class->maximum_slots ?: 0) > 0 && ($class->class_enrollments_count ?? 0) >= $class->maximum_slots,
                ])
                ->values();

            if ($availableClasses->isEmpty()) {
                $missingClasses->push([
                    'subject_enrollment_id' => $subjectEnrollment->id,
                    'subject_id' => $subject->id,
                    'subject_code' => $subject->code,
                    'subject_title' => $subject->title,
                    'enrollment_status' => 'No Class Offering',
                    'class_id' => null,
                ]);
            } else {
                foreach ($availableClasses as $ac) {
                    $missingClasses->push($ac);
                }
            }
        }

        // Waitlist entries for this enrollment
        $waitlists = EnrollmentClassWaitlist::query()
            ->with(['subject:id,code,title', 'preferredClass:id,section,subject_code'])
            ->where('student_enrollment_id', $enrollment->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EnrollmentClassWaitlist $wl): array => [
                'id' => $wl->id,
                'subject_id' => $wl->subject_id,
                'subject_enrollment_id' => $wl->subject_enrollment_id,
                'subject_code' => $wl->subject?->code,
                'subject_title' => $wl->subject?->title,
                'preferred_class_id' => $wl->preferred_class_id,
                'preferred_section' => $wl->preferredClass?->section,
                'status' => $wl->status,
                'position' => $wl->position,
                'notes' => $wl->notes,
                'created_at' => $wl->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        $waitlistBySubjectId = collect($waitlists)
            ->where('status', EnrollmentClassWaitlist::StatusWaiting)
            ->keyBy('subject_id');

        // Build enriched subjects_enrolled array
        $subjectsEnrolledPayload = $enrollment->subjectsEnrolled->map(function (SubjectEnrollment $se) use (
            $classEnrollmentsByClassId,
            $activeClassEnrollments,
            $checklistBySubjectId,
            $waitlistBySubjectId,
            $schedulingPolicy
        ): array {
            $subject = $se->subject;
            $matchedActive = $se->class_id ? $classEnrollmentsByClassId->get($se->class_id) : null;
            if (! $matchedActive && $subject) {
                $codeLower = mb_strtolower(mb_trim((string) $subject->code));
                $matchedActive = $activeClassEnrollments->first(function (array $ce) use ($codeLower): bool {
                    $codes = array_map(static fn (string $c): string => mb_strtolower(mb_trim($c)), explode(',', (string) ($ce['subject_code'] ?? '')));

                    return in_array($codeLower, $codes, true);
                });
            }

            $schedules = $matchedActive['schedules'] ?? [];
            if ($schedules === [] && $se->class instanceof Classes) {
                $classDeliveryMode = ScheduleDelivery::canonicalize($se->class->delivery_mode);
                $schedules = $se->class->schedules->map(function ($s) use ($classDeliveryMode, $schedulingPolicy): array {
                    $mode = ScheduleDelivery::canonicalize($s->delivery_mode ?? $classDeliveryMode);

                    return [
                        'id' => $s->id,
                        'day' => $s->day_of_week,
                        'start_time' => $s->start_time instanceof \Carbon\Carbon ? $s->start_time->format('H:i') : (string) $s->start_time,
                        'end_time' => $s->end_time instanceof \Carbon\Carbon ? $s->end_time->format('H:i') : (string) $s->end_time,
                        'room' => ScheduleDelivery::isOnline($mode) ? ScheduleDelivery::shortLabel($mode) : $s->room?->name,
                        'delivery_mode' => $mode,
                        'checks_time_conflicts' => ScheduleDelivery::checksStudentCohortConflicts($mode, $schedulingPolicy),
                    ];
                })->values()->all();
            }

            $checklistItem = $se->subject_id ? ($checklistBySubjectId[(int) $se->subject_id] ?? null) : null;
            $checklistStatus = (string) ($checklistItem['status'] ?? '');
            $waitlist = $se->subject_id ? $waitlistBySubjectId->get((int) $se->subject_id) : null;

            $lecUnits = (int) ($se->enrolled_lecture_units ?? $subject?->lecture ?? 0);
            $labUnits = (int) ($se->enrolled_laboratory_units ?? $subject?->laboratory ?? 0);
            $resolvedClassId = $se->class_id ?? ($matchedActive['class_id'] ?? null);
            $grades = $matchedActive['grades'] ?? null;
            $hasGrades = $se->grade !== null
                || ($grades !== null && (($grades['prelim'] ?? null) !== null || ($grades['midterm'] ?? null) !== null || ($grades['finals'] ?? null) !== null || ($grades['average'] ?? null) !== null));

            return [
                'id' => $se->id,
                'subject_id' => $se->subject_id,
                'class_id' => $resolvedClassId,
                'class_url' => $resolvedClassId ? route('administrators.classes.show', $resolvedClassId, false) : null,
                'subject_code' => $subject?->code ?? $se->external_subject_code ?? 'Unknown',
                'subject_title' => $subject?->title ?? $se->external_subject_title ?? 'Unknown',
                'units' => (float) ($subject?->units ?? ($lecUnits + $labUnits)),
                'lecture_units' => $lecUnits,
                'laboratory_units' => $labUnits,
                'lecture_fee' => $se->lecture_fee !== null ? (float) $se->lecture_fee : null,
                'lab_fee' => $se->laboratory_fee !== null ? (float) $se->laboratory_fee : null,
                'is_modular' => (bool) $se->is_modular,
                'exclude_from_tuition' => (bool) $se->exclude_from_tuition,
                'section' => $se->section ?? ($matchedActive['section'] ?? $se->class?->section),
                'faculty' => $matchedActive['faculty'] ?? ($se->class?->Faculty?->full_name ?? 'TBA'),
                'schedule' => $matchedActive['schedule'] ?? ($se->class ? ($se->class->schedules->map(fn ($s): string => $s->day_of_week.' '.$s->time_range)->implode(', ') ?: 'TBA') : 'Unassigned'),
                'room' => $matchedActive['room'] ?? 'TBA',
                'schedules' => $schedules,
                'grades' => $grades,
                'grade' => $se->grade,
                'has_grades' => $hasGrades,
                'is_failed_retake' => $checklistStatus === 'failed',
                'already_passed' => in_array($checklistStatus, ['passed', 'credited'], true),
                'prerequisite_satisfied' => (bool) ($checklistItem['prerequisite_satisfied'] ?? true),
                'missing_prerequisite' => $checklistItem['missing_prerequisite'] ?? null,
                'recommended' => (bool) ($checklistItem['is_recommended'] ?? false),
                'waitlist' => $waitlist,
            ];
        })->values();

        // Tuition summary + finance lock/revision metadata
        $tuitionSummary = $enrollment->studentTuition
            ? $this->enrollmentBillingService->toSummaryArray(
                $enrollment->studentTuition,
                (float) $enrollment->additionalFees->sum('amount')
            )
            : null;
        if ($tuitionSummary !== null && $enrollment->studentTuition instanceof StudentTuition) {
            $discountRecord = $enrollment->studentTuition->discount_id
                ? EnrollmentDiscount::query()->find($enrollment->studentTuition->discount_id)
                : null;
            $tuitionSummary = [
                ...$tuitionSummary,
                'active_revision_id' => $enrollment->studentTuition->active_revision_id,
                'needs_finance_review' => (bool) $enrollment->studentTuition->needs_finance_review,
                'credit' => (float) ($enrollment->studentTuition->credit ?? 0),
                'assessment_adjustment' => (float) ($enrollment->studentTuition->assessment_adjustment ?? 0),
                'discount_id' => $enrollment->studentTuition->discount_id,
                'discount_name' => $discountRecord?->name,
            ];
        }

        $pendingScheduleChangeNotifications = $this->classScheduleChangeNotificationService
            ->pendingNotificationsForEnrollment($enrollment);
        $workflow = $this->workflowView($enrollment, $user);

        // Policy-aware pipeline configuration & completion flags
        $isPolicyRuntime = $enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1 && $enrollment->policySnapshot !== null;
        if ($isPolicyRuntime) {
            $projected = $this->enrollmentPipelineService->getConfigurationFor($enrollment);
            $pipelineSteps = collect($projected['steps'] ?? [])->map(fn (array $step): array => [
                ...$step,
                'is_completion' => ($projected['completion_step_key'] ?? null) === ($step['key'] ?? null),
            ])->values()->all();
            $completionStepKey = $projected['completion_step_key'] ?? null;
            $isCompleted = $enrollment->terminal_outcome === 'completed'
                || ($completionStepKey !== null && $enrollment->current_step_key === $completionStepKey);
            $pipelineConfig = [
                ...$projected,
                'steps' => $pipelineSteps,
                'status_options' => $this->enrollmentPipelineService->getStatusOptions(),
                'status_classes' => $this->enrollmentPipelineService->getStatusColorClasses(),
                'next_step' => isset($workflow['allowed_transitions'][0]) ? [
                    'key' => $workflow['allowed_transitions'][0]['to'],
                    'status' => $workflow['allowed_transitions'][0]['to_status'],
                    'label' => $workflow['allowed_transitions'][0]['label'],
                    'action_type' => $workflow['allowed_transitions'][0]['action_type'] ?? null,
                ] : null,
            ];
            $availableReopenSteps = collect($pipelineSteps)
                ->filter(fn (array $step): bool => ! ($step['is_completion'] ?? false))
                ->map(fn (array $step): array => [
                    'key' => (string) ($step['key'] ?? ''),
                    'label' => (string) ($step['label'] ?? $step['status'] ?? ''),
                    'status' => (string) ($step['status'] ?? ''),
                ])
                ->filter(fn (array $s): bool => $s['key'] !== '')
                ->values()
                ->all();
        } else {
            $completionStep = $this->enrollmentPipelineService->getCompletionStep();
            $isCompleted = $enrollment->terminal_outcome !== EnrollmentCancellationService::OUTCOME
                && $completionStep !== null
                && $enrollment->status === ($completionStep['status'] ?? null);
            $pipelineConfig = [
                ...$this->enrollmentPipelineService->getConfiguration(),
                'steps' => $this->enrollmentPipelineService->getSteps(),
                'status_options' => $this->enrollmentPipelineService->getStatusOptions(),
                'status_classes' => $this->enrollmentPipelineService->getStatusColorClasses(),
                'next_step' => $this->enrollmentPipelineService->getNextStep($enrollment->status),
            ];
            $availableReopenSteps = [];
        }

        $isTerminalLocked = $this->isTerminalLocked($enrollment);
        $canPrintAssessment = ! $isTerminalLocked && ! $enrollment->trashed();
        $canResendAssessment = $isCompleted && ! $isTerminalLocked && ! $enrollment->trashed();

        // Holds, Load Changes, Transfers & Unified Timeline + Edit History
        $holds = $enrollment->student_id
            ? $this->studentHoldService
                ->allHoldsForStudent((int) $enrollment->student_id)
                ->map(fn ($hold): array => $this->studentHoldService->serializeHold($hold))
                ->values()
                ->all()
            : [];

        $loadChanges = EnrollmentLoadChange::query()
            ->with(['requestedBy:id,name', 'approvedBy:id,name', 'rejectedBy:id,name', 'addedClass:id,section,subject_code'])
            ->where('student_enrollment_id', $enrollment->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $loadChangesSerialized = $loadChanges->map(fn (EnrollmentLoadChange $item): array => [
            ...$loadChangeService->serialize($item),
            'slip_url' => route('administrators.enrollments.load-changes.slip', [
                'enrollment' => $enrollment->id,
                'loadChange' => $item->id,
            ], false),
        ])->values()->all();

        $transfers = EnrollmentTransferLog::query()
            ->with(['fromCourse:id,code,title', 'toCourse:id,code,title', 'performedBy:id,name'])
            ->where('student_enrollment_id', $enrollment->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (EnrollmentTransferLog $log): array => [
                'id' => $log->id,
                'transfer_type' => $log->transfer_type,
                'from_course' => $log->fromCourse?->code,
                'to_course' => $log->toCourse?->code,
                'from_section' => $log->from_section,
                'to_section' => $log->to_section,
                'reassigned_classes_count' => $log->reassigned_classes_count,
                'credited_subjects_count' => $log->credited_subjects_count,
                'fee_delta' => (float) ($log->fee_delta ?? 0),
                'reason' => $log->reason,
                'performed_by' => $log->performedBy?->name ?? 'System',
                'created_at' => $log->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        $enrollmentActivities = Activity::query()
            ->with('causer:id,name')
            ->where('subject_type', StudentEnrollment::class)
            ->where('subject_id', $enrollment->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $editHistory = $enrollmentActivities->map(function (Activity $activity): array {
            $props = $activity->properties?->toArray() ?? [];
            if ($props === []) {
                $props = $activity->attribute_changes?->toArray() ?? [];
            }

            return [
                'id' => $activity->id,
                'event' => (string) ($activity->event ?? 'updated'),
                'description' => (string) ($activity->description ?? ''),
                'causer_name' => $activity->causer?->name ?? 'System',
                'created_at' => $activity->created_at?->toISOString(),
                'changes' => $props,
            ];
        })->values()->all();

        // Unified chronological timeline
        $timeline = collect();
        if ($enrollment->created_at !== null) {
            $timeline->push([
                'id' => 'created-'.$enrollment->id,
                'type' => 'created',
                'title' => 'Enrollment record created',
                'description' => sprintf(
                    '%s · %s (%s)',
                    $enrollment->course?->code ?? $enrollment->student?->Course?->code ?? 'Program unassigned',
                    $enrollment->school_year ?? 'Current term',
                    $semesterLabel
                ),
                'actor' => null,
                'timestamp' => $enrollment->created_at->toISOString(),
                'date_label' => $enrollment->created_at->format('M j, Y · g:i A'),
                'tone' => 'info',
            ]);
        }
        foreach ($enrollment->workflowEvents as $event) {
            $toLabel = $event->status ?: ($event->to_step_key ? Str::headline((string) $event->to_step_key) : 'Workflow updated');
            $timeline->push([
                'id' => 'wf-'.$event->id,
                'type' => 'workflow',
                'title' => $event->terminal_outcome === 'cancelled'
                    ? 'Enrollment cancelled'
                    : sprintf('Moved to %s', $toLabel),
                'description' => $event->reason ?: ($event->from_step_key ? sprintf('Transitioned from %s', Str::headline((string) $event->from_step_key)) : 'Pipeline step recorded'),
                'actor' => null,
                'timestamp' => $event->created_at?->toISOString(),
                'date_label' => $event->created_at?->format('M j, Y · g:i A') ?? '—',
                'tone' => $event->terminal_outcome === 'cancelled' ? 'destructive' : 'success',
            ]);
        }
        foreach ($enrollmentActivities as $activity) {
            $timeline->push([
                'id' => 'act-'.$activity->id,
                'type' => 'audit',
                'title' => Str::headline((string) ($activity->event ?? 'enrollment_updated')),
                'description' => (string) ($activity->description ?? 'Enrollment updated'),
                'actor' => $activity->causer?->name ?? 'System',
                'timestamp' => $activity->created_at?->toISOString(),
                'date_label' => $activity->created_at?->format('M j, Y · g:i A') ?? '—',
                'tone' => str_contains((string) $activity->event, 'override') || str_contains((string) $activity->event, 'overload') ? 'warning' : 'info',
            ]);
        }
        foreach ($loadChanges as $lc) {
            $timeline->push([
                'id' => 'col-'.$lc->id,
                'type' => 'load_change',
                'title' => sprintf('Change of Load %s (%s)', $lc->reference_number, Str::headline($lc->status)),
                'description' => $lc->reason ?: sprintf('%s subject load adjustment', Str::headline($lc->change_type)),
                'actor' => $lc->approvedBy?->name ?? $lc->requestedBy?->name ?? 'System',
                'timestamp' => ($lc->approved_at ?? $lc->created_at)?->toISOString(),
                'date_label' => ($lc->approved_at ?? $lc->created_at)?->format('M j, Y · g:i A') ?? '—',
                'tone' => $lc->status === EnrollmentLoadChange::StatusRejected ? 'destructive' : ($lc->status === EnrollmentLoadChange::StatusPending ? 'warning' : 'success'),
            ]);
        }
        foreach ($enrollmentTransactions as $studentTx) {
            $tx = $studentTx->transaction;
            if (! $tx) {
                continue;
            }
            $txAmount = (float) ($studentTx->amount ?? $tx->raw_total_amount ?? $tx->total_amount ?? 0);
            $timeline->push([
                'id' => 'tx-'.$tx->id,
                'type' => 'payment',
                'title' => sprintf('Payment recorded (%s)', $tx->invoicenumber ?: $tx->transaction_number ?: '#'.$tx->id),
                'description' => sprintf('Amount: %s · %s', number_format($txAmount, 2), $tx->description ?: ($tx->status ?? 'Settled')),
                'actor' => null,
                'timestamp' => $tx->created_at?->toISOString(),
                'date_label' => $tx->created_at?->format('M j, Y · g:i A') ?? '—',
                'tone' => 'success',
            ]);
        }
        if ($enrollment->deleted_at !== null) {
            $timeline->push([
                'id' => 'deleted-'.$enrollment->id,
                'type' => 'deleted',
                'title' => 'Moved to trash',
                'description' => 'Soft-deleted enrollment record (can be restored).',
                'actor' => null,
                'timestamp' => $enrollment->deleted_at->toISOString(),
                'date_label' => $enrollment->deleted_at->format('M j, Y · g:i A'),
                'tone' => 'destructive',
            ]);
        }

        $sortedTimeline = $timeline
            ->sortByDesc(fn (array $item): string => (string) ($item['timestamp'] ?? ''))
            ->values()
            ->take(30)
            ->all();

        // Queue navigation (same school year & semester) + sanitized return_to
        $rawReturnTo = (string) $request->query('return_to', '');
        $returnTo = ($rawReturnTo !== '' && str_starts_with($rawReturnTo, '/') && ! str_starts_with($rawReturnTo, '//'))
            ? $rawReturnTo
            : null;

        $prevId = StudentEnrollment::query()
            ->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)
            ->where('id', '>', $enrollment->id)
            ->orderBy('id')
            ->value('id');

        $nextId = StudentEnrollment::query()
            ->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)
            ->where('id', '<', $enrollment->id)
            ->orderByDesc('id')
            ->value('id');

        // Resolve course metadata from the enrollment's own course (falling back to student's current course)
        $course = $enrollment->course ?? $enrollment->student?->Course;
        $curriculumKind = $course ? $capabilityResolver->kindForCourse($course) : null;

        // Safe minimal user projection
        $userSummary = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->getFilamentAvatarUrl(),
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];

        $canManageEnrollment = $this->canManageEnrollment($user, 'update', $enrollment);
        $canManageTuition = $this->canManageTuitionFees($user);

        return Inertia::render('administrators/enrollments/show', [
            'user' => $userSummary,
            'enrollment' => [
                'id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id ?? $enrollment->student?->course_id,
                'course_code' => $course?->code,
                'course_title' => $course?->title,
                'department' => $course?->department?->code,
                'curriculum_kind' => $curriculumKind,
                'qualification_level' => $course?->qualification_level,
                'duration_hours' => $course?->duration_hours,
                'tesda_program_type' => $course?->tesda_program_type,
                'status' => $enrollment->status,
                'workflow_runtime' => $enrollment->workflow_runtime,
                'allows_no_receipt_transition' => $enrollment->allowsConfiguredNoReceiptTransition(),
                'workflow' => [
                    ...$workflow,
                    'is_completed' => $isCompleted,
                    'is_terminal_locked' => $isTerminalLocked,
                    'can_print_assessment' => $canPrintAssessment,
                    'can_resend_assessment' => $canResendAssessment,
                    'available_reopen_steps' => $availableReopenSteps,
                ],
                'policy_snapshot_name' => data_get($enrollment->policySnapshot?->configuration, 'meta.name'),
                'policy_snapshot_version' => $enrollment->policySnapshot?->version,
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'semester_label' => $semesterLabel,
                'academic_year' => $enrollment->academic_year,
                'remarks' => $enrollment->remarks,
                'submission_channel' => $enrollment->submission_channel,
                'created_at' => $enrollment->created_at?->toISOString(),
                'updated_at' => $enrollment->updated_at?->toISOString(),
                'deleted_at' => $enrollment->deleted_at?->toISOString(),
                'is_trashed' => $enrollment->trashed(),
                'student' => [
                    'id' => $enrollment->student?->id,
                    'full_name' => $enrollment->student?->full_name ?? 'Unknown Student',
                    'email' => $enrollment->student?->email,
                    'phone' => $enrollment->student?->phone ?? $enrollment->student?->studentContactsInfo?->personal_contact,
                    'address' => $enrollment->student?->personalInfo?->current_adress ?? $enrollment->student?->address,
                    'emergency_contact_name' => $enrollment->student?->studentContactsInfo?->emergency_contact_name,
                    'emergency_contact_phone' => $enrollment->student?->studentContactsInfo?->emergency_contact_phone,
                    'student_id' => $enrollment->student?->student_id,
                    'course_code' => $course?->code,
                    'scholarship_type' => $enrollment->student?->scholarship_type?->value,
                    'scholarship_label' => $enrollment->student?->scholarship_type?->getLabel(),
                    'student_url' => $enrollment->student ? route('administrators.students.show', $enrollment->student->id) : null,
                    'documents_url' => $enrollment->student ? route('administrators.students.documents.index', $enrollment->student->id, false) : null,
                ],
                'subjects_enrolled' => $subjectsEnrolledPayload,
                'class_enrollments' => $activeClassEnrollments,
                'missing_classes' => $missingClasses->values(),
                'tuition' => $tuitionSummary,
                'additional_fees' => $enrollment->additionalFees->map(fn (AdditionalFee $fee): array => [
                    'id' => $fee->id,
                    'fee_name' => $fee->fee_name,
                    'amount' => (float) $fee->amount,
                    'is_separate_transaction' => (bool) $fee->is_separate_transaction,
                    'transaction_number' => $fee->transaction_number,
                ])->values(),
                'transactions' => $enrollmentTransactions->map(function ($studentTransaction): ?array {
                    $tx = $studentTransaction->transaction;
                    if (! $tx) {
                        return null;
                    }

                    return [
                        'id' => $tx->id,
                        'transaction_number' => $tx->transaction_number,
                        'invoicenumber' => $tx->invoicenumber,
                        'description' => $tx->description,
                        'status' => $tx->status,
                        'total_amount' => (float) $tx->total_amount,
                        'amount' => (float) ($studentTransaction->amount ?? $tx->raw_total_amount ?? $tx->total_amount ?? 0),
                        'transaction_date' => $tx->transaction_date,
                        'created_at' => $tx->created_at?->toDateTimeString(),
                        'receipt_url' => route('administrators.finance.payments.show', $tx->id, false),
                    ];
                })->filter()->values(),
                'latest_invoice' => $enrollment->latestInvoice ? [
                    'id' => $enrollment->latestInvoice->id,
                    'document_number' => $enrollment->latestInvoice->document_number ?? null,
                    'status' => $enrollment->latestInvoice->status ?? null,
                    'sent_at' => $enrollment->latestInvoice->sent_at?->toISOString(),
                    'download_url' => route('administrators.finance.documents.download', $enrollment->latestInvoice->id, false),
                ] : null,
                'financial_documents' => $enrollment->financialDocuments->map(fn ($doc): array => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type ?? 'document',
                    'document_number' => $doc->document_number ?? null,
                    'status' => $doc->status ?? null,
                    'created_at' => $doc->created_at?->toISOString(),
                    'download_url' => route('administrators.finance.documents.download', $doc->id, false),
                ])->values(),
                'resources' => $enrollment->resources->map(fn (Resource $res): array => [
                    'id' => $res->id,
                    'type' => $res->type,
                    'file_name' => $res->file_name,
                    'file_size' => $res->file_size,
                    'created_at' => $res->created_at?->toDateTimeString(),
                    'download_url' => route('assessment.download', ['record' => $enrollment->id, 'resource' => $res->id], false),
                ])->values(),
                'requirements' => $enrollment->requirements->map(fn ($requirement): array => [
                    'id' => $requirement->id,
                    'key' => $requirement->requirement_key,
                    'label' => $requirement->label,
                    'description' => $requirement->description,
                    'status' => $requirement->status,
                    'required' => (bool) $requirement->is_required,
                    'enforcement_step' => $requirement->enforcement_step_key,
                    'evidence_path' => $requirement->evidence_path,
                    'document_url' => filled($requirement->evidence_path)
                        ? route('administrators.enrollments.requirements.document', ['enrollment' => $enrollment->id, 'requirement' => $requirement->id], false)
                        : null,
                    'verified_at' => $requirement->verified_at?->toISOString(),
                    'waived_at' => $requirement->waived_at?->toISOString(),
                    'waiver_reason' => $requirement->waiver_reason,
                ])->values(),
                'pending_class_schedule_changes' => $this->classScheduleChangeNotificationService
                    ->previewPendingNotifications($pendingScheduleChangeNotifications),
                'holds' => $holds,
                'waitlists' => $waitlists,
                'checklist_summary' => $checklistSummary,
                'load_changes_summary' => [
                    'pending_count' => $loadChanges->where('status', EnrollmentLoadChange::StatusPending)->count(),
                    'total_count' => $loadChanges->count(),
                    'recent' => $loadChangesSerialized,
                ],
                'transfers_summary' => [
                    'total_count' => count($transfers),
                    'recent' => $transfers,
                ],
                'timeline' => $sortedTimeline,
                'edit_history' => $editHistory,
            ],
            'hold_offices' => $this->studentHoldService->officeOptions(),
            'enrollment_period' => $this->enrollmentPeriodService->describe((string) $enrollment->school_year, (int) $enrollment->semester),
            'scheduling_policy' => $schedulingPolicy,
            'scholarship_options' => array_map(
                static fn (ScholarshipType $case): array => [
                    'value' => $case->value,
                    'label' => $case->getLabel() ?? $case->value,
                ],
                ScholarshipType::cases(),
            ),
            'document_links' => [
                'assessment_preview_url' => route('administrators.enrollments.assessment-preview', $enrollment->id, false),
                'cor_pdf_url' => $enrollment->student_id ? route('administrators.registrar.documents.pdf', [
                    'template' => 'certificate_of_enrollment',
                    'variant' => 'full_certificate',
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                ], false) : null,
                'reg_form_student_url' => $enrollment->student_id ? route('administrators.registrar.documents.pdf', [
                    'template' => 'registration_form',
                    'variant' => 'student_copy',
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                ], false) : null,
                'reg_form_adviser_url' => $enrollment->student_id ? route('administrators.registrar.documents.pdf', [
                    'template' => 'registration_form',
                    'variant' => 'adviser_copy',
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                ], false) : null,
                'reg_form_receipt_url' => $enrollment->student_id ? route('administrators.registrar.documents.pdf', [
                    'template' => 'registration_form',
                    'variant' => 'receipt_copy',
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                ], false) : null,
                'grade_report_url' => $enrollment->student_id ? route('administrators.registrar.documents.pdf', [
                    'template' => 'grade_report',
                    'variant' => 'grade_slip',
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                ], false) : null,
                'soa_url' => $enrollment->student_id ? route('administrators.students.tuition.soa', $enrollment->student_id, false) : null,
                'cashier_payment_url' => $enrollment->student_id ? route('administrators.finance.payments.create', [
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                ], false) : null,
                'tuition_adjustments_url' => route('administrators.finance.tuition-adjustments.index', [
                    'enrollment' => $enrollment->id,
                ], false),
                're_enroll_url' => route('administrators.enrollments.create', [
                    'from_enrollment' => $enrollment->id,
                ], false),
                'new_enrollment_url' => route('administrators.enrollments.create', [], false),
            ],
            'queue_navigation' => [
                'prev_id' => $prevId ? (int) $prevId : null,
                'next_id' => $nextId ? (int) $nextId : null,
                'return_to' => $returnTo,
            ],
            'auth' => [
                'user' => $userSummary,
                'can_manage_enrollment' => $canManageEnrollment,
                'can_verify_head' => (
                    $user->can('verify_by_head_dept_guest::enrollment') ||
                    $user->hasRole('super_admin') ||
                    $this->enrollmentPipelineService->canUserPerformStep(
                        $user,
                        $this->enrollmentPipelineService->getStepByActionType('department_verification') ?? ['allowed_roles' => []]
                    )
                ),
                'can_verify_cashier' => (
                    $user->can('verify_by_cashier_guest::enrollment') ||
                    $user->hasRole('super_admin') ||
                    $this->enrollmentPipelineService->canUserPerformStep(
                        $user,
                        $this->enrollmentPipelineService->getStepByActionType('cashier_verification') ?? ['allowed_roles' => []]
                    )
                ),
                'is_super_admin' => $user->hasRole('super_admin'),
                'can_reopen' => $user->can('Reopen:StudentEnrollment') || $user->hasRole('super_admin'),
                'can_manage_tuition' => $canManageTuition,
                'can_advance_pipeline' => ! $enrollment->trashed()
                    && ! $isTerminalLocked
                    && ($isPolicyRuntime
                        ? count($workflow['allowed_transitions'] ?? []) > 0
                        : $this->enrollmentPipelineService->canUserAdvanceToNextStep($user, $enrollment->status)),
                'can_review_requirements' => $user->can('Update:StudentEnrollment') || $user->hasRole('super_admin') || $canManageEnrollment,
                'can_delete' => $user->can('delete', $enrollment) || $user->can('Delete:StudentEnrollment') || $canManageEnrollment,
                'can_restore' => $user->can('restore', $enrollment) || $user->can('Restore:StudentEnrollment') || $canManageEnrollment,
                'can_force_delete' => $user->can('forceDelete', $enrollment) || $user->can('ForceDelete:StudentEnrollment') || $user->hasRole('super_admin'),
                'can_export_documents' => $user->can('exportDetailed', StudentEnrollment::class) || $user->hasRole('super_admin') || $canManageEnrollment,
            ],
            'recent_deletions' => $this->getRecentDeletionsForEnrollment($enrollment),
            'flash' => session('flash'),
            'enrollment_pipeline' => $pipelineConfig,
        ]);
    }

    public function updateRemarks(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $previous = $enrollment->remarks;
        $next = isset($validated['remarks']) && mb_trim((string) $validated['remarks']) !== ''
            ? mb_trim((string) $validated['remarks'])
            : null;

        $enrollment->update(['remarks' => $next]);

        activity('enrollment')
            ->performedOn($enrollment)
            ->causedBy($user)
            ->withProperties([
                'previous_remarks' => $previous,
                'new_remarks' => $next,
            ])
            ->event('remarks_updated')
            ->log('Updated registrar enrollment remarks.');

        return back()->with('flash', ['success' => 'Enrollment remarks updated.']);
    }

    public function swapSubjectSection(
        Request $request,
        StudentEnrollment $enrollment,
        SubjectEnrollment $subjectEnrollment,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);
        abort_unless((int) $subjectEnrollment->enrollment_id === (int) $enrollment->id, 404);

        if ($this->isTerminalLocked($enrollment) || $enrollment->trashed()) {
            return back()->with('flash', ['error' => 'Cannot change sections on a cancelled, rejected, or trashed enrollment.']);
        }

        $validated = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'force_overload' => ['boolean'],
        ]);

        $newClassId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;
        $forceOverload = (bool) ($validated['force_overload'] ?? false);

        try {
            $message = DB::transaction(function () use ($enrollment, $subjectEnrollment, $newClassId, $forceOverload, $user): string {
                $previousClassId = $subjectEnrollment->class_id;
                $previousSection = $subjectEnrollment->section;
                $newSection = null;

                if ($newClassId !== null) {
                    $this->ensureClassCapacityForSubjects(
                        [['class_id' => $newClassId]],
                        (int) $enrollment->student_id,
                        $forceOverload,
                        $enrollment,
                        $user,
                    );

                    $targetClass = Classes::query()->findOrFail($newClassId);
                    $newSection = $targetClass->section;
                }

                if ($previousClassId !== null && $previousClassId !== $newClassId) {
                    ClassEnrollment::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('class_id', $previousClassId)
                        ->delete();
                }

                if ($newClassId !== null) {
                    Student::query()->whereKey($enrollment->student_id)->lockForUpdate()->first();
                    app(\App\Services\ClassEnrollmentService::class)->enrollOnceWhileLocked(
                        (int) $enrollment->student_id,
                        $newClassId,
                        ['status' => true],
                    );

                    EnrollmentClassWaitlist::query()
                        ->where('student_enrollment_id', $enrollment->id)
                        ->where(function ($query) use ($subjectEnrollment): void {
                            $query->where('subject_enrollment_id', $subjectEnrollment->id)
                                ->orWhere('subject_id', $subjectEnrollment->subject_id);
                        })
                        ->where('status', EnrollmentClassWaitlist::StatusWaiting)
                        ->update([
                            'status' => EnrollmentClassWaitlist::StatusPromoted,
                            'promoted_class_id' => $newClassId,
                            'promoted_by' => $user->id,
                            'promoted_at' => now(),
                        ]);
                }

                $subjectEnrollment->forceFill([
                    'class_id' => $newClassId,
                    'section' => $newSection,
                ])->save();

                $subjectCode = $subjectEnrollment->subject?->code ?? 'Subject';

                activity('enrollment')
                    ->performedOn($enrollment)
                    ->causedBy($user)
                    ->withProperties([
                        'subject_enrollment_id' => $subjectEnrollment->id,
                        'subject_code' => $subjectCode,
                        'from_class_id' => $previousClassId,
                        'from_section' => $previousSection,
                        'to_class_id' => $newClassId,
                        'to_section' => $newSection,
                    ])
                    ->event('section_swapped')
                    ->log($newSection !== null
                        ? "Assigned {$subjectCode} to Section {$newSection}."
                        : "Unassigned section for {$subjectCode}.");

                return $newSection !== null
                    ? "Assigned {$subjectCode} to Section {$newSection}."
                    : "Unassigned section for {$subjectCode}.";
            });

            return back()->with('flash', ['success' => $message]);
        } catch (Throwable $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        }
    }

    public function requirementDocument(StudentEnrollment $enrollment, EnrollmentRequirement $requirement): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canViewEnrollment($user, $enrollment), 403);
        abort_unless((int) $requirement->student_enrollment_id === (int) $enrollment->id, 404);

        $path = (string) ($requirement->evidence_path ?? '');
        abort_if($path === '', 404);
        abort_unless(Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->download($path, basename($path));
    }

    public function classScheduleChangesPreview(StudentEnrollment $enrollment): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canViewEnrollment($user, $enrollment), 403);

        $enrollment->loadMissing('student');

        return response()->json([
            'student' => [
                'id' => $enrollment->student->id,
                'full_name' => $enrollment->student->full_name,
                'email' => $enrollment->student->email,
            ],
            'changes' => $this->classScheduleChangeNotificationService->previewPendingNotifications(
                $this->classScheduleChangeNotificationService->pendingNotificationsForEnrollment($enrollment)
            ),
        ]);
    }

    public function notifyClassScheduleChanges(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);

        $result = $this->classScheduleChangeNotificationService->notifyPendingForEnrollment(
            $enrollment,
            $user
        );

        if ($result['count'] === 0) {
            return back()->with('flash', ['info' => 'There are no pending class schedule changes to notify for this student.']);
        }

        return back()->with('flash', [
            'success' => sprintf(
                'Queued %d class schedule change notification%s for this student.',
                $result['count'],
                $result['count'] === 1 ? '' : 's'
            ),
        ]);
    }

    public function advancePipelineStep(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $this->workflowCoordinator->transition($enrollment, $user, null);

            return back()->with('flash', ['success' => 'Advanced to the next pipeline step.']);
        } catch (Throwable $throwable) {
            return back()->with('flash', ['error' => $throwable->getMessage()]);
        }
    }

    public function verifyHeadDept(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $this->workflowCoordinator->verifyAcademic($enrollment, $user);

            return back()->with('flash', ['success' => 'Successfully verified as Head Dept.']);
        } catch (Throwable $throwable) {
            return back()->with('flash', ['error' => $throwable->getMessage()]);
        }
    }

    public function verifyCashier(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $separateFeeTransactionKeys = $enrollment->additionalFees()
            ->where('is_separate_transaction', true)
            ->orderBy('id')
            ->pluck('id')
            ->mapWithKeys(fn (int $id): array => [
                "separate_fee_{$id}_transaction" => [
                    $enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1 ? 'nullable' : 'required',
                    'string',
                    'max:255',
                ],
            ])
            ->all();
        $validated = $request->validate([
            'invoicenumber' => [
                $enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1 ? 'nullable' : 'required',
                'string',
                'max:255',
            ],
            'settlements' => ['required', 'array'],
            'settlements.registration_fee' => ['nullable', 'numeric', 'min:0'],
            'settlements.tuition_fee' => ['nullable', 'numeric', 'min:0'],
            'settlements.miscelanous_fee' => ['nullable', 'numeric', 'min:0'],
            'settlements.others' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'transition_key' => ['nullable', 'string', 'max:255'],
            ...$separateFeeTransactionKeys,
        ]);

        try {
            $this->workflowCoordinator->verifyPayment($enrollment, $user, $validated, transitionKey: $validated['transition_key'] ?? null);

            return back()->with('flash', ['success' => 'Successfully enrolled student.']);
        } catch (Throwable $throwable) {
            return back()->with('flash', ['error' => $throwable->getMessage()]);
        }
    }

    public function verifyCashierNoReceipt(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        if (! Auth::user()->hasRole('super_admin')) {
            abort(403);
        }

        $data = $request->validate([
            'remarks' => 'required|string',
            'confirm_payment' => 'required|accepted',
        ]);

        if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 403);

            try {
                $this->workflowCoordinator->verifyPayment($enrollment, $actor, [
                    'without_receipt' => true,
                    'reason' => $data['remarks'],
                    'remarks' => $data['remarks'],
                ]);

                return back()->with('flash', ['success' => 'Student enrolled through the configured no-receipt transition.']);
            } catch (Throwable $throwable) {
                return back()->with('flash', ['error' => $throwable->getMessage()]);
            }
        }

        if ($this->enrollmentService->verifyByCashierWithoutReceipt($enrollment, $data)) {
            return back()->with('flash', ['success' => 'Student enrolled without receipt.']);
        }

        return back()->with('flash', ['error' => 'Enrollment failed.']);
    }

    public function undoCancellation(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless(
            $user->can('Reopen:StudentEnrollment') || $user->hasRole('super_admin'),
            403,
        );

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->workflowCoordinator->reopen(
                $enrollment,
                $user,
                null,
                $validated['reason'],
                'undo-cancellation:'.$request->header('Idempotency-Key', (string) Str::uuid()),
            );

            return back()->with('flash', ['success' => 'Cancellation undone. Prior verification is retained where applicable; reassign released class seats separately.']);
        } catch (Throwable $throwable) {
            return back()->with('flash', ['error' => $throwable->getMessage()]);
        }
    }

    public function undoCashierVerification(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $canUndoCashier = $user->hasRole('super_admin')
            || $user->can('Reopen:StudentEnrollment')
            || $user->can('verify_by_cashier_guest::enrollment')
            || $this->enrollmentPipelineService->canUserPerformStep(
                $user,
                $this->enrollmentPipelineService->getStepByActionType('cashier_verification') ?? ['allowed_roles' => []],
            );
        abort_unless($canUndoCashier, 403);

        $reason = mb_trim((string) $request->input('reason', 'Cashier verification correction.')) ?: 'Cashier verification correction.';

        try {
            $this->workflowCoordinator->reopen($enrollment, $user, null, $reason);

            activity('enrollment')
                ->performedOn($enrollment)
                ->causedBy($user)
                ->withProperties(['reason' => $reason])
                ->event('cashier_verification_undone')
                ->log('Undid cashier payment verification.');

            return back()->with('flash', ['success' => 'Cashier verification undone.']);
        } catch (Throwable $throwable) {
            return back()->with('flash', ['error' => $throwable->getMessage()]);
        }
    }

    public function undoHeadDeptVerification(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $canUndoHead = $user->hasRole('super_admin')
            || $user->can('Reopen:StudentEnrollment')
            || $user->can('verify_by_head_dept_guest::enrollment')
            || $this->enrollmentPipelineService->canUserPerformStep(
                $user,
                $this->enrollmentPipelineService->getStepByActionType('department_verification') ?? ['allowed_roles' => []],
            );
        abort_unless($canUndoHead, 403);

        $reason = mb_trim((string) $request->input('reason', 'Academic verification correction.')) ?: 'Academic verification correction.';

        try {
            $this->workflowCoordinator->reopen($enrollment, $user, null, $reason);

            activity('enrollment')
                ->performedOn($enrollment)
                ->causedBy($user)
                ->withProperties(['reason' => $reason])
                ->event('department_verification_undone')
                ->log('Undid department verification.');

            return back()->with('flash', ['success' => 'Head Dept verification undone.']);
        } catch (Throwable $throwable) {
            return back()->with('flash', ['error' => $throwable->getMessage()]);
        }
    }

    public function enrollInClass(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);

        if ($this->isTerminalLocked($enrollment) || $enrollment->trashed()) {
            return back()->with('flash', ['error' => 'Cannot assign classes on a cancelled, rejected, or trashed enrollment.']);
        }

        $data = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'force_enrollment' => 'boolean',
        ]);

        try {
            $forceOverload = (bool) ($data['force_enrollment'] ?? false);
            $result = DB::transaction(function () use ($enrollment, $data, $forceOverload, $user): array {
                $class = Classes::query()->whereKey((int) $data['class_id'])->lockForUpdate()->firstOrFail();
                $student = $enrollment->student;

                if (! $student instanceof Student) {
                    throw new RuntimeException('Enrollment has no associated student record.');
                }

                $alreadyActive = ClassEnrollment::query()
                    ->where('class_id', $class->id)
                    ->where('student_id', $student->id)
                    ->where('status', true)
                    ->exists();

                if ($alreadyActive) {
                    return ['status' => 'warning', 'message' => 'Student is already enrolled in this class.'];
                }

                $activeSeats = ClassEnrollment::query()
                    ->where('class_id', $class->id)
                    ->where('student_id', '!=', $student->id)
                    ->where('status', true)
                    ->count();

                if (! $forceOverload && $class->maximum_slots > 0 && $activeSeats >= $class->maximum_slots) {
                    return ['status' => 'error', 'message' => 'Class is full. Use force enrollment to override.'];
                }

                if ($forceOverload && $class->maximum_slots > 0 && $activeSeats >= $class->maximum_slots) {
                    activity('enrollment')
                        ->performedOn($enrollment)
                        ->causedBy($user)
                        ->withProperties([
                            'class_id' => $class->id,
                            'section' => $class->section,
                            'enrolled_count' => $activeSeats,
                            'maximum_slots' => $class->maximum_slots,
                        ])
                        ->event('class_overload_approved')
                        ->log("Approved class seat overload into Section {$class->section}.");
                }

                Student::query()->whereKey($student->id)->lockForUpdate()->first();
                app(\App\Services\ClassEnrollmentService::class)->enrollOnceWhileLocked(
                    (int) $student->id,
                    (int) $class->id,
                    ['status' => true],
                );

                // Sync matching SubjectEnrollment class_id & section and promote waiting waitlist entries.
                $classSubjectIds = collect(is_array($class->subject_ids) ? $class->subject_ids : [])
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->all();
                $classSubjectCodes = collect(explode(',', (string) $class->subject_code))
                    ->map(fn ($code): string => mb_strtolower(mb_trim($code)))
                    ->filter()
                    ->all();

                $enrollment->loadMissing('subjectsEnrolled.subject');
                foreach ($enrollment->subjectsEnrolled as $se) {
                    $matchesById = $se->subject_id !== null && in_array((int) $se->subject_id, $classSubjectIds, true);
                    $matchesByCode = $se->subject?->code !== null
                        && in_array(mb_strtolower(mb_trim((string) $se->subject->code)), $classSubjectCodes, true);

                    if ($matchesById || $matchesByCode) {
                        $se->forceFill([
                            'class_id' => $class->id,
                            'section' => $class->section,
                        ])->saveQuietly();

                        EnrollmentClassWaitlist::query()
                            ->where('student_enrollment_id', $enrollment->id)
                            ->where(function ($query) use ($se): void {
                                $query->where('subject_enrollment_id', $se->id)
                                    ->orWhere('subject_id', $se->subject_id);
                            })
                            ->where('status', EnrollmentClassWaitlist::StatusWaiting)
                            ->update([
                                'status' => EnrollmentClassWaitlist::StatusPromoted,
                                'promoted_class_id' => $class->id,
                                'promoted_by' => $user->id,
                                'promoted_at' => now(),
                            ]);
                    }
                }

                return ['status' => 'success', 'message' => "Enrolled in {$class->subject_code} (Section {$class->section})."];
            });

            return back()->with('flash', [$result['status'] => $result['message']]);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        }
    }

    public function retryEnrollment(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);

        if ($this->isTerminalLocked($enrollment) || $enrollment->trashed()) {
            return back()->with('flash', ['error' => 'Cannot retry class enrollment on a cancelled, rejected, or trashed enrollment.']);
        }

        $force = $request->boolean('force_enrollment', false);
        $originalConfig = config('enrollment.force_enroll_when_full');

        if ($force) {
            config(['enrollment.force_enroll_when_full' => true]);
        }

        try {
            $enrollment->student->autoEnrollInClasses($enrollment->id);

            return back()->with('flash', ['success' => 'Enrollment retry process completed.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        } finally {
            if ($force) {
                config(['enrollment.force_enroll_when_full' => $originalConfig]);
            }
        }
    }

    public function resendAssessment(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);

        if ($this->isTerminalLocked($enrollment) || $enrollment->trashed()) {
            return back()->with('flash', ['error' => 'Cannot resend assessment for a cancelled, rejected, or trashed enrollment.']);
        }

        $result = $this->enrollmentService->resendAssessmentNotification($enrollment);
        if ($result['success']) {
            return back()->with('flash', ['success' => 'Assessment notification queued.']);
        }

        return back()->with('flash', ['error' => $result['message']]);
    }

    public function createAssessmentPdf(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);

        try {
            GenerateAssessmentPdfJob::dispatch($enrollment, uniqid('pdf_', true), true, true);

            return back()->with('flash', ['success' => 'Fresh assessment PDF generation queued.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate bulk assessments PDF for current semester enrollments
     */
    public function generateBulkAssessments(
        GenerateBulkAssessmentsRequest $request,
        GeneralSettingsService $settingsService,
        TenantContext $tenantContext,
        QueueAssessmentExportService $exports,
        AssessmentExportPayloadService $payloads,
    ): JsonResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validated();
        $schoolId = $tenantContext->getCurrentSchoolId();
        if ($schoolId === null || ! $tenantContext->canAccessOrganization($schoolId)) {
            abort(403, 'Select an accessible school before starting an assessment export.');
        }
        $filters = [
            'course_id' => $validated['course_id'] ?? null,
            'year_level' => $validated['year_level'] ?? null,
            'student_limit' => $validated['student_limit'] ?? null,
            'include_deleted' => (bool) $validated['include_deleted'],
            'semester' => $settingsService->getCurrentSemester(),
            'school_year' => $settingsService->getCurrentSchoolYearString(),
        ];

        try {
            $export = $exports->queue($user, $schoolId, $filters);
        } catch (AssessmentExportLimitReached $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        $payload = $payloads->make($export);

        return response()->json($payload, 202);
    }

    /**
     * Get assessment preview data for client-side rendering
     */
    public function assessmentPreviewData(StudentEnrollment $enrollment, AssessmentFormDataService $assessmentFormDataService): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canViewEnrollment($user, $enrollment), 403);

        return response()->json($assessmentFormDataService->build($enrollment));
    }

    /**
     * Show the assessment preview page (immersive, print-friendly)
     */
    public function assessmentPreview(StudentEnrollment $enrollment, AssessmentFormDataService $assessmentFormDataService): Response
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canViewEnrollment($user, $enrollment), 403);

        return Inertia::render('administrators/enrollments/assessment-preview', [
            'data' => $assessmentFormDataService->build($enrollment),
            'enrollmentId' => $enrollment->id,
        ]);
    }

    public function quickEnroll(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        if (! Auth::user()->hasRole('super_admin')) {
            abort(403);
        }
        $validated = $request->validate([
            'remarks' => 'required|string',
            'confirm_emergency' => 'required|accepted',
            'confirm_payment' => 'required|accepted',
        ]);

        try {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 403);
            if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
                $this->workflowCoordinator->quickEnroll(
                    $enrollment,
                    $actor,
                    '⚡ QUICK ENROLL: '.$validated['remarks'],
                );

                return back()->with('flash', ['success' => 'Quick enrollment completed through the authorized blueprint transition.']);
            }

            $this->workflowCoordinator->quickEnroll(
                $enrollment,
                $actor,
                '⚡ QUICK ENROLL: '.$validated['remarks'],
            );

            return back()->with('flash', ['success' => 'Quick enrollment successful.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update'), 403);

        $validated = $request->validate([
            'scholarship_type' => ['nullable', 'string', Rule::in(array_map(static fn (ScholarshipType $case): string => $case->value, ScholarshipType::cases()))],
        ]);

        $previous = $student->scholarship_type?->value;
        $next = ($validated['scholarship_type'] ?? null) === ScholarshipType::None->value
            ? ScholarshipType::None->value
            : ($validated['scholarship_type'] ?? null);

        $student->update([
            'scholarship_type' => $next,
        ]);

        activity('enrollment')
            ->performedOn($student)
            ->causedBy($user)
            ->withProperties([
                'previous_scholarship' => $previous,
                'new_scholarship' => $next,
            ])
            ->event('scholarship_updated')
            ->log('Updated student scholarship classification.');

        return redirect()->back()->with('flash', [
            'success' => 'Student scholarship status updated successfully.',
        ]);
    }

    public function updateTransaction(Request $request, StudentEnrollment $enrollment, Transaction $transaction): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageTuitionFees($user), 403);

        $studentTransaction = StudentTransaction::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('transaction_id', $transaction->id)
            ->first();

        abort_unless($studentTransaction instanceof StudentTransaction, 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'invoicenumber' => ['required', 'string', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($enrollment, $transaction, $studentTransaction, $validated, $user): void {
                $lockedTx = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                $previousInvoice = $lockedTx->invoicenumber;
                $previousAmount = (float) ($studentTransaction->amount ?? $lockedTx->raw_total_amount ?? 0);
                $newAmount = round((float) $validated['amount'], 2);

                $settlements = $lockedTx->settlements;
                if (is_string($settlements)) {
                    $settlements = json_decode($settlements, true) ?: [];
                }
                if (! is_array($settlements)) {
                    $settlements = [];
                }

                if (isset($settlements['tuition_fee'])) {
                    $settlements['tuition_fee'] = $newAmount;
                } elseif (isset($settlements['others'])) {
                    $settlements['others'] = $newAmount;
                } else {
                    $settlements['tuition_fee'] = $newAmount;
                }

                $lockedTx->update([
                    'settlements' => $settlements,
                    'invoicenumber' => $validated['invoicenumber'],
                ]);

                $studentTransaction->update([
                    'amount' => $newAmount,
                ]);

                $tuition = $enrollment->studentTuition;
                if ($tuition instanceof StudentTuition) {
                    $allocations = PaymentAllocation::query()
                        ->where('transaction_id', $lockedTx->id)
                        ->where('student_tuition_id', $tuition->id)
                        ->get();
                    if ($allocations->count() === 1) {
                        $allocations->first()->update(['amount' => $newAmount]);
                    }

                    $this->enrollmentBillingService->syncTuitionBalance($tuition->refresh());
                }

                activity('enrollment')
                    ->performedOn($enrollment)
                    ->causedBy($user)
                    ->withProperties([
                        'transaction_id' => $lockedTx->id,
                        'previous_invoice' => $previousInvoice,
                        'new_invoice' => $validated['invoicenumber'],
                        'previous_amount' => $previousAmount,
                        'new_amount' => $newAmount,
                    ])
                    ->event('transaction_updated')
                    ->log("Updated transaction #{$lockedTx->id} ({$validated['invoicenumber']}).");
            });

            return back()->with('flash', ['success' => 'Transaction updated successfully.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => 'Failed to update transaction: '.$e->getMessage()]);
        }
    }

    public function updateTuition(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_tuition_fees'), 403);

        return redirect()->route('administrators.finance.tuition-adjustments.index', [
            'enrollment' => $enrollment->getKey(),
        ]);
    }

    /**
     * Show the form for creating a new enrollment.
     */
    public function edit(
        StudentEnrollment $enrollment,
        GeneralSettingsService $settingsService,
        CurriculumCapabilityResolver $capabilityResolver
    ): Response|RedirectResponse {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        abort_unless($this->canManageEnrollment($user, 'update', $enrollment), 403);

        $enrollment->load([
            'student.Course',
            'subjectsEnrolled.subject.course',
            'subjectsEnrolled.class',
            'studentTuition',
            'additionalFees',
            'latestInvoice',
        ]);

        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        $subjectsWithClasses = $this->subjectsWithClassesForEnrollment($enrollment);

        // Resolve class_id from term-matched class_enrollments when subject_enrollment.class_id is null.
        $classEnrollments = ClassEnrollment::query()
            ->where('student_id', $enrollment->student_id)
            ->where('status', true)
            ->whereHas('class', function ($query) use ($enrollment): void {
                $query->where('school_year', $enrollment->school_year)
                    ->where('semester', $enrollment->semester);
            })
            ->with('class')
            ->get();

        /** @var array<int, int> $subjectClassMap */
        $subjectClassMap = [];
        foreach ($classEnrollments as $ce) {
            $class = $ce->class;
            if ($class && ! empty($class->subject_ids) && is_array($class->subject_ids)) {
                foreach ($class->subject_ids as $subId) {
                    $subjectClassMap[(int) $subId] = (int) $ce->class_id;
                }
            }
        }

        $tuition = $enrollment->studentTuition;
        $additionalFeesTotal = (float) $enrollment->additionalFees->sum('amount');
        $tuitionSummary = $tuition instanceof StudentTuition
            ? $this->enrollmentBillingService->toSummaryArray($tuition, $additionalFeesTotal)
            : null;
        $latestInvoice = $enrollment->latestInvoice;
        $studentCourse = $enrollment->student?->Course;

        $activeHolds = $this->studentHoldService
            ->activeHoldsForStudent((int) $enrollment->student_id)
            ->map(fn ($hold): array => $this->studentHoldService->serializeHold($hold))
            ->values()
            ->all();

        $enrollmentPeriod = $this->enrollmentPeriodService->describe(
            (string) $enrollment->school_year,
            (int) $enrollment->semester,
        );

        $waitlists = EnrollmentClassWaitlist::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('status', 'waiting')
            ->get()
            ->map(fn (EnrollmentClassWaitlist $w): array => [
                'id' => $w->id,
                'subject_id' => $w->subject_id,
                'subject_enrollment_id' => $w->subject_enrollment_id,
                'preferred_class_id' => $w->preferred_class_id,
                'position' => $w->position,
                'status' => $w->status,
                'notes' => $w->notes,
            ])
            ->values()
            ->all();

        $editHistory = Activity::query()
            ->where('subject_type', StudentEnrollment::class)
            ->where('subject_id', $enrollment->id)
            ->with('causer')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event ?? 'updated',
                'description' => (string) $activity->description,
                'causer_name' => $activity->causer?->name ?? 'System',
                'created_at' => $activity->created_at?->toIso8601String(),
                'changes' => (function () use ($activity): array {
                    $props = $activity->properties;
                    if ($props instanceof Collection && $props->isNotEmpty()) {
                        return $props->toArray();
                    }
                    if (is_array($props) && $props !== []) {
                        return $props;
                    }
                    if (is_array($activity->attribute_changes)) {
                        return $activity->attribute_changes;
                    }

                    return is_object($activity->attribute_changes) ? $activity->attribute_changes->toArray() : [];
                })(),
            ])
            ->values()
            ->all();

        $isTerminalLocked = in_array(mb_strtolower((string) $enrollment->status), ['cancelled', 'rejected'], true)
            || ($enrollment->terminal_outcome !== null
                && ! in_array(mb_strtolower((string) $enrollment->terminal_outcome), ['enrolled', 'completed'], true));

        $checklistAnalysis = $enrollment->student instanceof Student && $enrollment->student->course_id
            ? app(StudentChecklistService::class)->analyze($enrollment->student)
            : [];
        $checklistBySubjectId = collect($checklistAnalysis['checklist'] ?? [])->keyBy('id');

        return Inertia::render('administrators/enrollments/edit', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'can_manage_tuition_fees' => $this->canManageTuitionFees($user),
            'enrollment_period' => $enrollmentPeriod,
            'active_holds' => $activeHolds,
            'waitlists' => $waitlists,
            'edit_history' => $editHistory,
            'is_terminal_locked' => $isTerminalLocked,
            'recommended_unit_cap' => 24,
            'checklist_summary' => isset($checklistAnalysis['academic_summary']) ? [
                'progress_percentage' => $checklistAnalysis['academic_summary']['progress_percentage'] ?? 0,
                'failed_retakes_count' => $checklistAnalysis['academic_summary']['failed_subjects_count'] ?? 0,
                'completed_subjects_count' => $checklistAnalysis['academic_summary']['completed_subjects_count'] ?? 0,
            ] : null,
            'settings' => [
                'currentSemester' => $currentSemester,
                'currentSchoolYear' => $currentSchoolYear,
                'availableSemesters' => $settingsService->getAvailableSemesters(),
                'availableAcademicYears' => [
                    '1' => '1st Year',
                    '2' => '2nd Year',
                    '3' => '3rd Year',
                    '4' => '4th Year',
                    '5' => '5th Year',
                ],
            ],
            'billingConfiguration' => $this->feePolicy->forEnrollment($enrollment),
            'scheduling_policy' => $settingsService->getSchedulingConfig(),
            'enrollment' => [
                'id' => $enrollment->id,
                'semester' => $enrollment->semester,
                'academic_year' => $enrollment->academic_year,
                'school_year' => $enrollment->school_year,
                'status' => $enrollment->status,
                'terminal_outcome' => $enrollment->terminal_outcome,
                'remarks' => $enrollment->remarks,
                'student' => [
                    'id' => $enrollment->student->id,
                    'student_id' => $enrollment->student->student_id,
                    'full_name' => $enrollment->student->full_name,
                    'email' => $enrollment->student->email,
                    'course_id' => $enrollment->student->course_id,
                    'course_code' => $studentCourse?->code,
                    'course_name' => $studentCourse?->title,
                    'curriculum_kind' => $studentCourse ? $capabilityResolver->kindForCourse($studentCourse) : null,
                    'qualification_level' => $studentCourse?->qualification_level,
                    'duration_hours' => $studentCourse?->duration_hours,
                    'tesda_program_type' => $studentCourse?->tesda_program_type,
                    'duration_years' => $studentCourse?->duration_years,
                    'internship_hours' => $studentCourse?->internship_hours,
                    'bundled_qualifications' => $studentCourse?->bundled_qualifications,
                    'scholarship_type' => $enrollment->student->scholarship_type?->value,
                    'scholarship_label' => $enrollment->student->scholarship_type?->getLabel(),
                    'academic_year' => $enrollment->student->academic_year,
                    'formatted_academic_year' => $enrollment->student->formatted_academic_year,
                    'miscellaneous_fee' => $studentCourse?->getMiscellaneousFee() ?? 3500,
                ],
                'subjects' => $enrollment->subjectsEnrolled->map(function ($subjectEnrollment) use ($subjectClassMap, $subjectsWithClasses, $checklistBySubjectId): array {
                    $hasGrades = $subjectEnrollment->grade !== null
                        || filled($subjectEnrollment->grade_symbol)
                        || filled($subjectEnrollment->grade_outcome);
                    $checkItem = $checklistBySubjectId->get($subjectEnrollment->subject_id);

                    return [
                        'id' => $subjectEnrollment->id,
                        'subject_id' => $subjectEnrollment->subject_id,
                        'subject_code' => $subjectEnrollment->subject?->code ?? '',
                        'subject_title' => $subjectEnrollment->subject?->title ?? '',
                        'class_id' => $subjectEnrollment->class_id ?? $subjectClassMap[$subjectEnrollment->subject_id] ?? null,
                        'class_section' => $subjectEnrollment->class?->section ?? $subjectEnrollment->section,
                        'is_modular' => (bool) $subjectEnrollment->is_modular,
                        'exclude_from_tuition' => (bool) $subjectEnrollment->exclude_from_tuition,
                        'lecture_units' => $subjectEnrollment->enrolled_lecture_units ?? ($subjectEnrollment->subject?->lecture ?? 0),
                        'laboratory_units' => $subjectEnrollment->enrolled_laboratory_units ?? ($subjectEnrollment->subject?->laboratory ?? 0),
                        'lecture_fee' => $subjectEnrollment->lecture_fee ?? 0,
                        'laboratory_fee' => $subjectEnrollment->laboratory_fee ?? 0,
                        'lec_per_unit' => $subjectEnrollment->subject?->course?->lec_per_unit ?? 0,
                        'lab_per_unit' => $subjectEnrollment->subject?->course?->lab_per_unit ?? 0,
                        'has_classes' => $subjectsWithClasses->contains($subjectEnrollment->subject?->code ?? ''),
                        'has_grades' => $hasGrades,
                        'grade_summary' => $hasGrades
                            ? ($subjectEnrollment->grade_symbol ?? ($subjectEnrollment->grade !== null ? (string) $subjectEnrollment->grade : $subjectEnrollment->grade_outcome))
                            : null,
                        'prerequisite_satisfied' => is_array($checkItem) ? (bool) ($checkItem['prerequisite_satisfied'] ?? true) : true,
                        'missing_prerequisite' => is_array($checkItem) ? ($checkItem['missing_prerequisite'] ?? null) : null,
                        'already_passed' => is_array($checkItem) ? (bool) ($checkItem['is_completed'] ?? false) : false,
                        'is_failed_retake' => is_array($checkItem) ? (($checkItem['status'] ?? null) === 'Failed') : false,
                    ];
                })->values(),
                'tuition' => $tuition ? [
                    'discount' => $tuition->discount,
                    'discount_id' => $tuition->discount_id,
                    'downpayment' => $tuition->downpayment,
                    'miscellaneous_fee' => $tuition->total_miscelaneous_fees,
                    'overall_tuition' => (float) $tuition->overall_tuition,
                    'total_tuition' => (float) $tuition->total_tuition,
                    'total_balance' => (float) ($tuitionSummary['balance_due'] ?? $tuition->total_balance),
                    'total_paid' => (float) ($tuitionSummary['total_paid'] ?? 0),
                    'balance_due' => (float) ($tuitionSummary['balance_due'] ?? $tuition->total_balance),
                    'credit' => (float) ($tuitionSummary['credit'] ?? 0),
                    'payment_status' => (string) ($tuitionSummary['status'] ?? $tuition->status ?? 'unpaid'),
                    'active_revision_id' => $tuition->active_revision_id,
                    'needs_finance_review' => (bool) $tuition->needs_finance_review,
                    'assessment_adjustment' => (float) ($tuition->assessment_adjustment ?? 0),
                ] : null,
                'latest_invoice' => $latestInvoice ? [
                    'id' => $latestInvoice->id,
                    'document_number' => $latestInvoice->document_number,
                    'status' => $latestInvoice->status,
                    'issued_at' => $latestInvoice->issued_at?->toIso8601String(),
                ] : null,
                'additional_fees' => $enrollment->additionalFees->map(fn ($fee): array => [
                    'id' => $fee->id,
                    'fee_name' => $fee->fee_name,
                    'amount' => $fee->amount,
                    'is_separate_transaction' => (bool) $fee->is_separate_transaction,
                    'transaction_number' => $fee->transaction_number,
                ])->values(),
            ],
            'discounts' => $this->discountOptions(),
            'flash' => session('flash'),
        ]);
    }

    public function updateEnrollment(SaveEnrollmentRequest $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageEnrollment($actor, 'update', $enrollment), 403);

        $validated = $request->validated();
        $canManageTuitionFees = $this->canManageTuitionFees($actor);

        if (! $canManageTuitionFees) {
            $existingTuition = $enrollment->studentTuition;
            $validated['discount'] = $existingTuition?->discount ?? 0;
            $validated['discount_id'] = $existingTuition?->discount_id;
            $validated['miscellaneous_fee'] = $existingTuition?->total_miscelaneous_fees
                ?? $enrollment->course?->getMiscellaneousFee()
                ?? 3500;
            $validated['downpayment'] = $existingTuition?->downpayment ?? $enrollment->downpayment ?? 0;
            $validated['additional_fees'] = $enrollment->additionalFees
                ->map(fn (AdditionalFee $fee): array => [
                    'id' => $fee->id,
                    'fee_name' => $fee->fee_name,
                    'amount' => (float) $fee->amount,
                ])
                ->values()
                ->all();
        }

        $validated = array_merge($validated, $this->resolveDiscountSelection($validated, $enrollment));

        if ((int) $validated['student_id'] !== (int) $enrollment->student_id) {
            return back()->with('flash', ['error' => 'Student selection cannot be changed for this enrollment.']);
        }

        $isTerminalLocked = in_array(mb_strtolower((string) $enrollment->status), ['cancelled', 'rejected'], true)
            || ($enrollment->terminal_outcome !== null
                && ! in_array(mb_strtolower((string) $enrollment->terminal_outcome), ['enrolled', 'completed'], true));

        if ($isTerminalLocked) {
            return back()->with('flash', ['error' => 'Cancelled or rejected enrollments cannot be edited directly. Restore or undo cancellation first.']);
        }

        if ((int) $validated['semester'] !== (int) $enrollment->semester) {
            $duplicateInTargetSemester = StudentEnrollment::query()
                ->where('student_id', $enrollment->student_id)
                ->where('school_year', $enrollment->school_year)
                ->where('semester', (int) $validated['semester'])
                ->whereKeyNot($enrollment->id)
                ->exists();

            if ($duplicateInTargetSemester) {
                return back()->withInput()->with('flash', ['error' => 'Student already has another enrollment for the selected semester.']);
            }
        }

        try {
            /** @var array<int, array{subject_code: string, subject_title: string, old_class_id: int|null, new_class_id: int|null, old_section: string, new_section: string}> $classChanges */
            $classChanges = [];
            /** @var array<int, string> $addedSubjectCodes */
            $addedSubjectCodes = [];
            /** @var array<int, string> $removedSubjectCodes */
            $removedSubjectCodes = [];
            $previousTuitionTotal = (float) ($enrollment->studentTuition?->overall_tuition ?? 0);
            $newTuitionTotal = $previousTuitionTotal;

            DB::transaction(function () use (
                $validated,
                $enrollment,
                $actor,
                $canManageTuitionFees,
                $previousTuitionTotal,
                &$classChanges,
                &$addedSubjectCodes,
                &$removedSubjectCodes,
                &$newTuitionTotal
            ): void {
                $this->ensureClassCapacityForSubjects(
                    $validated['subjects'],
                    (int) $validated['student_id'],
                    (bool) ($validated['force_overload'] ?? false),
                    $enrollment,
                    $actor,
                );

                /** @var Collection<int|string, SubjectEnrollment> $existingSubjects */
                $existingSubjects = $enrollment->subjectsEnrolled()->with(['subject', 'class'])->get()->keyBy('subject_id');

                $incomingSubjectIdsPreview = collect($validated['subjects'])
                    ->pluck('subject_id')
                    ->map(fn (mixed $value): int => (int) $value)
                    ->all();
                $existingSubjectIdsPreview = $existingSubjects->keys()->map(fn (mixed $k): int => (int) $k)->all();
                $subjectsWereAdded = array_diff($incomingSubjectIdsPreview, $existingSubjectIdsPreview) !== [];
                $subjectsWereRemoved = array_diff($existingSubjectIdsPreview, $incomingSubjectIdsPreview) !== [];

                if ($subjectsWereAdded || $subjectsWereRemoved) {
                    $this->enrollmentPeriodService->assertAddDropAllowed(
                        overrideWindow: (bool) ($validated['override_window'] ?? false),
                        schoolYear: (string) $enrollment->school_year,
                        semester: (int) $validated['semester'],
                    );

                    if ($enrollment->student instanceof Student) {
                        $this->studentHoldService->assertNoBlockingAddDropHolds(
                            student: $enrollment->student,
                            actor: $actor,
                            overrideHolds: (bool) ($validated['override_holds'] ?? false),
                            overrideReason: isset($validated['override_holds_reason']) ? (string) $validated['override_holds_reason'] : null,
                        );
                    }
                }

                $enrollment->update([
                    'semester' => $validated['semester'],
                    'academic_year' => $validated['academic_year'],
                    'downpayment' => $validated['downpayment'] ?? 0,
                    'remarks' => array_key_exists('remarks', $validated) ? $validated['remarks'] : $enrollment->remarks,
                ]);

                $feeConfig = $this->feePolicy->forEnrollment($enrollment);

                $normalizedSubjects = $this->resolveSubjectFeesForPersistence(
                    $validated['subjects'],
                    $feeConfig,
                    $canManageTuitionFees,
                );
                if (! $canManageTuitionFees) {
                    $normalizedSubjects = array_map(
                        static function (array $subjectData) use ($existingSubjects): array {
                            $existing = $existingSubjects->get((int) $subjectData['subject_id']);

                            return [
                                ...$subjectData,
                                'exclude_from_tuition' => (bool) ($existing?->exclude_from_tuition ?? false),
                            ];
                        },
                        $normalizedSubjects,
                    );
                }

                /** @var Collection<int, array<string, mixed>> $incomingSubjects */
                $incomingSubjects = collect($normalizedSubjects);

                $incomingSubjectIds = $incomingSubjects
                    ->pluck('subject_id')
                    ->map(fn (mixed $value): int => (int) $value)
                    ->all();

                $removedSubjects = $existingSubjects->filter(
                    fn (SubjectEnrollment $subject): bool => ! in_array((int) $subject->subject_id, $incomingSubjectIds, true)
                );

                $gradedRemovals = $removedSubjects->filter(
                    fn (SubjectEnrollment $subject): bool => $subject->grade !== null
                        || filled($subject->grade_symbol)
                        || filled($subject->grade_outcome)
                );

                if ($gradedRemovals->isNotEmpty() && ! (bool) ($validated['confirm_grade_loss'] ?? false)) {
                    $codes = $gradedRemovals
                        ->map(fn (SubjectEnrollment $subject): string => (string) ($subject->subject?->code ?? "#{$subject->subject_id}"))
                        ->implode(', ');

                    throw new Exception("Cannot remove graded subject(s) [{$codes}] without explicit grade-loss confirmation.");
                }

                $classIdsToLoad = collect()
                    ->merge($incomingSubjects->pluck('class_id')->filter())
                    ->merge($existingSubjects->pluck('class_id')->filter())
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values();
                $classesById = $classIdsToLoad->isNotEmpty()
                    ? Classes::query()->whereIn('id', $classIdsToLoad)->get()->keyBy('id')
                    : collect();

                $addedSubjectModels = Subject::query()
                    ->whereIn('id', array_values(array_diff($incomingSubjectIds, $existingSubjectIdsPreview)))
                    ->get()
                    ->keyBy('id');

                foreach ($incomingSubjects as $subjectData) {
                    $subjectId = (int) $subjectData['subject_id'];
                    $incomingClassId = isset($subjectData['class_id']) && $subjectData['class_id'] !== '' ? (int) $subjectData['class_id'] : null;
                    $incomingClass = $incomingClassId ? $classesById->get($incomingClassId) : null;
                    $existingSubject = $existingSubjects->get($subjectId);

                    $excludeFromTuition = $canManageTuitionFees
                        ? (bool) ($subjectData['exclude_from_tuition'] ?? false)
                        : (bool) ($existingSubject?->exclude_from_tuition ?? false);

                    $payload = [
                        'class_id' => $incomingClassId,
                        'section' => $incomingClass instanceof Classes ? $incomingClass->section : null,
                        'is_modular' => (bool) ($subjectData['is_modular'] ?? false),
                        'exclude_from_tuition' => $excludeFromTuition,
                        'lecture_fee' => $subjectData['lecture_fee'],
                        'laboratory_fee' => $subjectData['laboratory_fee'],
                        'enrolled_lecture_units' => $subjectData['enrolled_lecture_units'],
                        'enrolled_laboratory_units' => $subjectData['enrolled_laboratory_units'],
                        'academic_year' => $validated['academic_year'],
                        'school_year' => $enrollment->school_year,
                        'semester' => $validated['semester'],
                    ];

                    if ($existingSubject instanceof SubjectEnrollment) {
                        $previousClassId = $existingSubject->class_id ? (int) $existingSubject->class_id : null;
                        $previousClass = $previousClassId ? $classesById->get($previousClassId) : null;

                        if ($previousClassId !== $incomingClassId) {
                            $subjectModel = $existingSubject->subject;
                            $classChanges[] = [
                                'subject_code' => $subjectModel?->code ?? '',
                                'subject_title' => $subjectModel?->title ?? '',
                                'old_class_id' => $previousClassId,
                                'new_class_id' => $incomingClassId,
                                'old_section' => (string) ($previousClass instanceof Classes ? $previousClass->section : ($existingSubject->section ?? '')),
                                'new_section' => (string) ($incomingClass instanceof Classes ? $incomingClass->section : ''),
                            ];
                        }

                        // Save quietly when class_id changes so SubjectEnrollment's model hook does not double-mutate ClassEnrollment.
                        $existingSubject->fill($payload);
                        if ($previousClassId !== $incomingClassId) {
                            $existingSubject->saveQuietly();
                        } else {
                            $existingSubject->save();
                        }

                        if ($previousClassId && $previousClassId !== $incomingClassId) {
                            ClassEnrollment::query()
                                ->where('student_id', $enrollment->student_id)
                                ->where('class_id', $previousClassId)
                                ->delete();
                        }

                        if ($incomingClassId) {
                            $classEnrollment = ClassEnrollment::query()
                                ->withTrashed()
                                ->firstOrNew([
                                    'student_id' => $enrollment->student_id,
                                    'class_id' => $incomingClassId,
                                ]);

                            if ($classEnrollment->trashed()) {
                                $classEnrollment->restore();
                            }

                            $classEnrollment->status = true;
                            $classEnrollment->save();

                            // Auto-promote any waiting waitlist entries for this subject now that a section is assigned.
                            EnrollmentClassWaitlist::query()
                                ->where('student_enrollment_id', $enrollment->id)
                                ->where('subject_id', $subjectId)
                                ->where('status', 'waiting')
                                ->update([
                                    'status' => 'promoted',
                                    'promoted_by' => $actor->id,
                                    'promoted_class_id' => $incomingClassId,
                                    'promoted_at' => now(),
                                ]);
                        }
                    } else {
                        $enrollment->subjectsEnrolled()->create(array_merge($payload, [
                            'subject_id' => $subjectId,
                            'student_id' => $enrollment->student_id,
                        ]));

                        $addedModel = $addedSubjectModels->get($subjectId);
                        if ($addedModel instanceof Subject) {
                            $addedSubjectCodes[] = (string) $addedModel->code;
                            $classChanges[] = [
                                'subject_code' => (string) $addedModel->code,
                                'subject_title' => (string) $addedModel->title,
                                'old_class_id' => null,
                                'new_class_id' => $incomingClassId,
                                'old_section' => 'Not enrolled',
                                'new_section' => (string) ($incomingClass instanceof Classes ? $incomingClass->section : 'Unassigned'),
                            ];
                        }

                        if ($incomingClassId) {
                            $classEnrollment = ClassEnrollment::query()
                                ->withTrashed()
                                ->firstOrNew([
                                    'student_id' => $enrollment->student_id,
                                    'class_id' => $incomingClassId,
                                ]);

                            if ($classEnrollment->trashed()) {
                                $classEnrollment->restore();
                            }

                            $classEnrollment->status = true;
                            $classEnrollment->save();
                        }
                    }
                }

                $removedSubjects->each(function (SubjectEnrollment $subject) use ($enrollment, &$removedSubjectCodes, &$classChanges): void {
                    if ($subject->subject?->code) {
                        $removedSubjectCodes[] = (string) $subject->subject->code;
                        $classChanges[] = [
                            'subject_code' => (string) $subject->subject->code,
                            'subject_title' => (string) ($subject->subject->title ?? ''),
                            'old_class_id' => $subject->class_id ? (int) $subject->class_id : null,
                            'new_class_id' => null,
                            'old_section' => (string) ($subject->class?->section ?? $subject->section ?? 'Unassigned'),
                            'new_section' => 'Removed',
                        ];
                    }

                    if ($subject->class_id) {
                        ClassEnrollment::query()
                            ->where('student_id', $enrollment->student_id)
                            ->where('class_id', $subject->class_id)
                            ->delete();
                    }

                    EnrollmentClassWaitlist::query()
                        ->where('student_enrollment_id', $enrollment->id)
                        ->where('subject_id', $subject->subject_id)
                        ->where('status', 'waiting')
                        ->update(['status' => 'cancelled']);

                    $subject->deleteQuietly();
                });

                // Reconcile additional fees non-destructively, protecting receipted / separate-transaction fees.
                $reconciledFees = $this->reconcileAdditionalFees(
                    $enrollment,
                    $validated['additional_fees'] ?? [],
                );

                $this->updateEnrollmentTuition(
                    $enrollment,
                    $normalizedSubjects,
                    $validated['discount'],
                    $validated['discount_id'],
                    (float) ($validated['miscellaneous_fee']
                        ?? $enrollment->studentTuition?->total_miscelaneous_fees
                        ?? $enrollment->course?->getMiscellaneousFee()
                        ?? 3500),
                    (float) ($validated['downpayment'] ?? 0),
                    $reconciledFees
                );

                $newTuitionTotal = (float) ($enrollment->studentTuition()->value('overall_tuition') ?? $previousTuitionTotal);

                // Persist structured revision diff audit entry so change_reason and deltas are never lost.
                $feeDelta = round($newTuitionTotal - $previousTuitionTotal, 2);
                $summaryParts = [];
                if ($addedSubjectCodes !== []) {
                    $summaryParts[] = 'Added '.implode(', ', $addedSubjectCodes);
                }
                if ($removedSubjectCodes !== []) {
                    $summaryParts[] = 'Removed '.implode(', ', $removedSubjectCodes);
                }
                if ($classChanges !== [] && $addedSubjectCodes === [] && $removedSubjectCodes === []) {
                    $summaryParts[] = sprintf('Updated %d section assignment(s)', count($classChanges));
                }
                if (abs($feeDelta) >= 0.01) {
                    $summaryParts[] = sprintf('Tuition delta %s%.2f', $feeDelta > 0 ? '+' : '', $feeDelta);
                }
                $changeReason = mb_trim((string) ($validated['change_reason'] ?? ''));
                $description = $summaryParts !== []
                    ? implode(' · ', $summaryParts)
                    : 'Updated enrollment details';
                if ($changeReason !== '') {
                    $description .= " — {$changeReason}";
                }

                activity('enrollment')
                    ->performedOn($enrollment)
                    ->causedBy($actor)
                    ->withProperties([
                        'added_subjects' => $addedSubjectCodes,
                        'removed_subjects' => $removedSubjectCodes,
                        'section_changes' => $classChanges,
                        'previous_total' => $previousTuitionTotal,
                        'new_total' => $newTuitionTotal,
                        'fee_delta' => $feeDelta,
                        'change_reason' => $changeReason !== '' ? $changeReason : null,
                        'notify_student' => (bool) ($validated['notify_student'] ?? false),
                        'override_window' => (bool) ($validated['override_window'] ?? false),
                        'override_holds' => (bool) ($validated['override_holds'] ?? false),
                    ])
                    ->event('enrollment_edited')
                    ->log($description);
            });

            // Dispatch change notification whenever requested and subjects, sections, or tuition changed
            $shouldNotify = (bool) ($validated['notify_student'] ?? false);
            $tuitionChanged = abs($newTuitionTotal - $previousTuitionTotal) >= 0.01;
            if ($shouldNotify && ($classChanges !== [] || $tuitionChanged) && $enrollment->student?->email) {
                $notificationItems = $classChanges !== []
                    ? $classChanges
                    : [[
                        'subject_code' => 'ASSESSMENT',
                        'subject_title' => 'Tuition Assessment Updated',
                        'old_class_id' => null,
                        'new_class_id' => null,
                        'old_section' => number_format($previousTuitionTotal, 2),
                        'new_section' => number_format($newTuitionTotal, 2),
                    ]];

                SendClassChangeNotificationJob::dispatch(
                    $enrollment,
                    $notificationItems,
                    $validated['change_reason'] ?? ''
                );

                Log::info('Class change notification job dispatched.', [
                    'enrollment_id' => $enrollment->id,
                    'student_email' => $enrollment->student->email,
                    'changes_count' => count($notificationItems),
                ]);
            }

            return redirect()
                ->route('administrators.enrollments.show', $enrollment->id)
                ->with('flash', ['success' => 'Enrollment updated successfully.']);
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->with('flash', ['error' => 'Failed to update enrollment: '.$e->getMessage()]);
        }
    }

    public function create(Request $request, GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        abort_unless($this->canManageEnrollment($user, 'create'), 403);

        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        $prefill = null;
        $fromEnrollmentId = $request->integer('from_enrollment');
        if ($fromEnrollmentId > 0) {
            $source = StudentEnrollment::query()
                ->with(['student.Course', 'studentTuition'])
                ->find($fromEnrollmentId);
            if ($source instanceof StudentEnrollment && $source->student instanceof Student) {
                $nextSemester = $source->semester === 1 ? 2 : 1;
                $nextAcademicYear = $source->semester >= 2
                    ? min(5, max(1, (int) ($source->academic_year ?? 1) + 1))
                    : max(1, (int) ($source->academic_year ?? 1));
                $prefill = [
                    'source_enrollment_id' => $source->id,
                    'student_id' => $source->student->id,
                    'student_name' => $source->student->full_name,
                    'student_number' => $source->student->student_id,
                    'course_code' => $source->student->Course?->code,
                    'semester' => $nextSemester,
                    'academic_year' => $nextAcademicYear,
                    'discount_id' => $source->studentTuition?->discount_id,
                ];
            }
        }

        return Inertia::render('administrators/enrollments/create', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'can_manage_tuition_fees' => $this->canManageTuitionFees($user),
            'prefill' => $prefill,
            'settings' => [
                'currentSemester' => $currentSemester,
                'currentSchoolYear' => $currentSchoolYear,
                'availableSemesters' => $settingsService->getAvailableSemesters(),
                'availableAcademicYears' => [
                    '1' => '1st Year',
                    '2' => '2nd Year',
                    '3' => '3rd Year',
                    '4' => '4th Year',
                    '5' => '5th Year',
                ],
            ],
            'billingConfiguration' => EnrollmentFeeCalculator::Defaults,
            'scheduling_policy' => $settingsService->getSchedulingConfig(),
            'enrollment_period' => $this->enrollmentPeriodService->describe($currentSchoolYear, $currentSemester),
            'discounts' => $this->discountOptions(),
            'flash' => session('flash'),
        ]);
    }

    /**
     * Store a newly created enrollment in storage.
     */
    public function store(SaveEnrollmentRequest $request, GeneralSettingsService $settingsService): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $this->canManageEnrollment($actor, 'create'), 403);

        $validated = $request->validated();
        $canManageTuitionFees = $this->canManageTuitionFees($actor);

        if (! $canManageTuitionFees) {
            $validated['discount'] = 0;
            $validated['discount_id'] = null;
            $validated['miscellaneous_fee'] = null;
            $validated['additional_fees'] = [];
        }

        $validated = array_merge($validated, $this->resolveDiscountSelection($validated));

        try {
            $result = $this->withoutEnrollmentSearchSyncing(fn (): array => DB::transaction(function () use ($validated, $settingsService, $actor, $canManageTuitionFees): array {
                $student = Student::with('Course')->findOrFail($validated['student_id']);
                $schoolYear = $settingsService->getCurrentSchoolYearString();
                $periodStatus = $this->enrollmentPeriodService->assertEnrollmentAllowed(
                    overrideWindow: (bool) ($validated['override_window'] ?? false),
                    schoolYear: $schoolYear,
                    semester: (int) $validated['semester'],
                );
                $validated['additional_fees'] = $this->enrollmentPeriodService->appendLateFeeIfApplicable(
                    $validated['additional_fees'] ?? [],
                    $periodStatus,
                );
                $this->studentHoldService->assertNoBlockingEnrollmentHolds(
                    student: $student,
                    actor: $actor,
                    overrideHolds: (bool) ($validated['override_holds'] ?? false),
                    overrideReason: isset($validated['override_holds_reason']) ? (string) $validated['override_holds_reason'] : null,
                );

                // Check for existing enrollment in the same period
                $existingEnrollment = StudentEnrollment::query()
                    ->where('student_id', $student->id)
                    ->where('school_year', $schoolYear)
                    ->where('semester', $validated['semester'])
                    ->first();

                if ($existingEnrollment) {
                    throw new Exception('Student already has an enrollment for this semester.');
                }

                $forceOverload = (bool) ($validated['force_overload'] ?? false);
                if (! Feature::active(DynamicEnrollmentPolicies::class)) {
                    $this->ensureClassCapacityForSubjects(
                        $validated['subjects'],
                        $student->id,
                        $forceOverload,
                        null,
                        $actor,
                    );
                }

                $feeConfig = array_merge(EnrollmentFeeCalculator::Defaults, [
                    'course_lecture_rate_per_unit' => (float) ($student->Course?->lec_per_unit ?? 0),
                    'course_laboratory_rate_per_unit' => (float) ($student->Course?->lab_per_unit ?? 0),
                ]);
                $normalizedSubjects = $this->resolveSubjectFeesForPersistence(
                    $validated['subjects'],
                    $feeConfig,
                    $canManageTuitionFees,
                );
                if (! $canManageTuitionFees) {
                    $normalizedSubjects = array_map(
                        static fn (array $subject): array => [...$subject, 'exclude_from_tuition' => false],
                        $normalizedSubjects,
                    );
                }

                $classAssignments = collect($normalizedSubjects)
                    ->filter(fn (array $subject): bool => ! empty($subject['class_id']))
                    ->map(fn (array $subject): array => [
                        'subject_id' => (int) $subject['subject_id'],
                        'class_id' => (int) $subject['class_id'],
                    ])
                    ->values()
                    ->all();
                $miscellaneousFee = isset($validated['miscellaneous_fee'])
                    ? (float) $validated['miscellaneous_fee']
                    : (float) ($student->Course?->getMiscellaneousFee() ?? 3500);
                $submission = new EnrollmentSubmissionData(
                    enrollmentAttributes: [
                        'student_id' => $student->id,
                        'course_id' => $student->course_id,
                        'status' => $this->enrollmentPipelineService->getPendingStatus(),
                        'semester' => $validated['semester'],
                        'academic_year' => $validated['academic_year'],
                        'school_year' => $schoolYear,
                        'downpayment' => $validated['downpayment'] ?? 0,
                        'remarks' => $validated['remarks'] ?? null,
                    ],
                    subjects: $normalizedSubjects,
                    classAssignments: $classAssignments,
                    additionalFees: $validated['additional_fees'] ?? [],
                    billingOverrides: [
                        'discount_percentage' => $validated['discount'] ?? 0,
                        'discount_id' => $validated['discount_id'],
                        'miscellaneous_fee' => $miscellaneousFee,
                        'downpayment' => $validated['downpayment'] ?? 0,
                    ],
                    channel: 'administrator',
                    idempotencyKey: (string) Str::uuid(),
                    actor: $actor,
                    facts: [
                        'force_overload' => $forceOverload,
                    ],
                );
                $enrollment = $this->workflowCoordinator->submit(
                    $submission,
                    function (StudentEnrollment $legacyEnrollment) use ($validated, $normalizedSubjects, $student, $schoolYear, $miscellaneousFee): void {
                        $classIds = collect($normalizedSubjects)->pluck('class_id')->filter()->map(fn (mixed $id): int => (int) $id)->unique();
                        $classesById = $classIds->isNotEmpty()
                            ? Classes::query()->whereIn('id', $classIds)->get()->keyBy('id')
                            : collect();

                        foreach ($normalizedSubjects as $subjectData) {
                            $classId = isset($subjectData['class_id']) && $subjectData['class_id'] !== '' ? (int) $subjectData['class_id'] : null;
                            $classModel = $classId ? $classesById->get($classId) : null;
                            $legacyEnrollment->subjectsEnrolled()->create([
                                ...$subjectData,
                                'class_id' => $classId,
                                'section' => $classModel instanceof Classes ? $classModel->section : null,
                                'student_id' => $student->id,
                                'academic_year' => $validated['academic_year'],
                                'school_year' => $schoolYear,
                                'semester' => $validated['semester'],
                            ]);
                            if ($classId) {
                                $classEnrollment = ClassEnrollment::query()->withTrashed()->firstOrNew([
                                    'student_id' => $student->id,
                                    'class_id' => $classId,
                                ]);
                                if ($classEnrollment->trashed()) {
                                    $classEnrollment->restore();
                                }
                                $classEnrollment->status = true;
                                $classEnrollment->save();
                            }
                        }
                        foreach ($validated['additional_fees'] ?? [] as $fee) {
                            $legacyEnrollment->additionalFees()->create([
                                'fee_name' => $fee['fee_name'],
                                'amount' => $fee['amount'],
                            ]);
                        }
                        $tuition = $this->enrollmentService->createStudentTuition($legacyEnrollment, [
                            'subjectsEnrolled' => $normalizedSubjects,
                            'discount' => $validated['discount'] ?? 0,
                            'discount_id' => $validated['discount_id'],
                            'miscellaneous_fee' => $miscellaneousFee,
                            'downpayment' => $validated['downpayment'] ?? 0,
                            'additionalFees' => $validated['additional_fees'] ?? [],
                        ]);
                        if (! $tuition instanceof StudentTuition) {
                            throw new Exception('Failed to create the tuition assessment.');
                        }
                    },
                );

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                ];
            }));

            $this->syncCreatedEnrollmentToSearch($result['enrollment']);

            return redirect()
                ->route('administrators.enrollments.show', $result['enrollment']->id)
                ->with('flash', ['success' => 'Enrollment created successfully for '.$result['student']->full_name.'.']);
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->with('flash', ['error' => 'Failed to create enrollment: '.$e->getMessage()]);
        }
    }

    /**
     * Search students for enrollment form autocomplete.
     *
     * @return JsonResponse
     */
    public function searchStudents(Request $request, CurriculumCapabilityResolver $capabilityResolver)
    {
        $search = $request->input('search', '');

        if (mb_strlen((string) $search) < 2) {
            return response()->json([]);
        }

        $like = '%'.mb_strtolower((string) $search).'%';

        $students = Student::query()
            ->with('Course')
            ->where(function ($query) use ($like): void {
                $query->where('id', 'like', $like)
                    ->orWhere('student_id', 'like', $like)
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", [$like])
                    ->orWhereHas('Course', function ($q) use ($like): void {
                        $q->whereRaw('LOWER(code) LIKE ?', [$like]);
                    });
            })
            ->limit(50)
            ->get()
            ->map(fn (Student $student): array => [
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
            ]);

        return response()->json($students);
    }

    /**
     * Search subjects for a specific course.
     *
     * @return JsonResponse
     */
    public function searchSubjects(Request $request, GeneralSettingsService $settingsService)
    {
        $courseId = $request->input('course_id');
        $search = $request->input('search', '');

        if (! $courseId) {
            return response()->json([]);
        }

        $schoolYear = filled($request->input('school_year'))
            ? (string) $request->input('school_year')
            : $settingsService->getCurrentSchoolYearString();
        $semester = $request->filled('semester')
            ? (int) $request->input('semester')
            : $settingsService->getCurrentSemester();

        // Get subjects with classes for the target period (batch-loaded, zero N+1)
        $classesWithSubjects = Classes::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->get();

        $matchingClasses = $classesWithSubjects->filter(function (Classes $class) use ($courseId): bool {
            if (empty($class->course_codes) || ! is_array($class->course_codes)) {
                return false;
            }

            return in_array((string) $courseId, array_map(strval(...), $class->course_codes), true);
        });

        $allClassSubjectIds = $matchingClasses
            ->flatMap(fn (Classes $class): array => is_array($class->subject_ids) ? $class->subject_ids : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $subjectsWithClasses = $allClassSubjectIds->isNotEmpty()
            ? Subject::query()->whereIn('id', $allClassSubjectIds)->pluck('code')
            : collect();

        foreach ($matchingClasses as $class) {
            if (! empty($class->subject_code)) {
                $codes = array_map(trim(...), explode(',', (string) $class->subject_code));
                foreach ($codes as $code) {
                    if ($code !== '') {
                        $subjectsWithClasses->push($code);
                    }
                }
            }
        }
        $subjectsWithClasses = $subjectsWithClasses->unique();

        // Search subjects
        $subjectsQuery = Subject::query()
            ->with('course')
            ->where('course_id', $courseId);

        if ($search) {
            $like = '%'.mb_strtolower((string) $search).'%';
            $subjectsQuery->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$like]);
            });
        }

        $targetAcademicYear = $request->filled('academic_year') ? (int) $request->input('academic_year') : null;
        $checklistBySubjectId = collect();
        if ($request->filled('student_id')) {
            $studentForChecklist = Student::with('Course')->find((int) $request->input('student_id'));
            if ($studentForChecklist instanceof Student && $studentForChecklist->course_id) {
                $analysis = app(StudentChecklistService::class)->analyze($studentForChecklist);
                $checklistBySubjectId = collect($analysis['checklist'] ?? [])->keyBy('id');
                $targetAcademicYear ??= (int) ($studentForChecklist->academic_year ?? 1);
            }
        }

        $subjects = $subjectsQuery
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->orderBy('code')
            ->limit(100)
            ->get()
            ->map(function (Subject $subject) use ($subjectsWithClasses, $checklistBySubjectId, $targetAcademicYear, $semester): array {
                $checkItem = $checklistBySubjectId->get($subject->id);
                $alreadyPassed = is_array($checkItem) ? (bool) ($checkItem['is_completed'] ?? false) : false;
                $isFailedRetake = is_array($checkItem) ? (($checkItem['status'] ?? null) === 'Failed') : false;
                $prereqSatisfied = is_array($checkItem) ? (bool) ($checkItem['prerequisite_satisfied'] ?? true) : true;
                $missingPrereq = is_array($checkItem) ? ($checkItem['missing_prerequisite'] ?? null) : null;
                $recommended = ! $alreadyPassed
                    && $prereqSatisfied
                    && (
                        $isFailedRetake
                        || ($targetAcademicYear !== null
                            && (int) $subject->academic_year === $targetAcademicYear
                            && (int) $subject->semester === $semester)
                    );

                return [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'title' => $subject->title,
                    'academic_year' => $subject->academic_year,
                    'semester' => $subject->semester,
                    'lecture' => $subject->lecture,
                    'laboratory' => $subject->laboratory,
                    'lec_per_unit' => $subject->course?->lec_per_unit ?? 0,
                    'lab_per_unit' => $subject->course?->lab_per_unit ?? 0,
                    'has_classes' => $subjectsWithClasses->contains($subject->code),
                    'checklist_status' => is_array($checkItem) ? ($checkItem['status'] ?? null) : null,
                    'already_passed' => $alreadyPassed,
                    'is_failed_retake' => $isFailedRetake,
                    'prerequisite_satisfied' => $prereqSatisfied,
                    'missing_prerequisite' => $missingPrereq,
                    'recommended' => $recommended,
                    'label' => ($subjectsWithClasses->contains($subject->code) ? '⭐ ' : '')
                        .$subject->code.' - '.$subject->title
                        .($subjectsWithClasses->contains($subject->code) ? '' : ' (⚠️ No Classes)'),
                ];
            });

        return response()->json($subjects);
    }

    /**
     * Get available sections for a subject.
     *
     * @return JsonResponse
     */
    public function getSubjectSections(Request $request, GeneralSettingsService $settingsService)
    {
        $subjectId = $request->input('subject_id');
        $courseId = $request->input('course_id');

        if (! $subjectId || ! $courseId) {
            return response()->json([]);
        }

        $subject = Subject::find($subjectId);
        if (! $subject) {
            return response()->json([]);
        }

        $schoolYear = filled($request->input('school_year'))
            ? (string) $request->input('school_year')
            : $settingsService->getCurrentSchoolYearString();
        $semester = $request->filled('semester')
            ? (int) $request->input('semester')
            : $settingsService->getCurrentSemester();

        $classes = Classes::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->where(function ($query) use ($subject): void {
                $query->whereJsonContains('subject_ids', $subject->id)
                    ->orWhereJsonContains('subject_ids', (string) $subject->id)
                    ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code]);
            })
            ->where(function ($query) use ($courseId): void {
                $query->whereJsonContains('course_codes', (int) $courseId)
                    ->orWhereJsonContains('course_codes', (string) $courseId);
            })
            ->with(['Faculty', 'schedules.room'])
            ->withCount(['class_enrollments' => fn ($query) => $query->where('status', true)])
            ->get();

        $schedulingPolicy = $settingsService->getSchedulingConfig();

        $sections = $classes->map(function ($class) use ($schedulingPolicy): array {
            $enrolledCount = $class->class_enrollments_count;
            $maxSlots = $class->maximum_slots ?: 0;
            $availableSlots = $maxSlots > 0 ? $maxSlots - $enrolledCount : null;
            $classDeliveryMode = ScheduleDelivery::canonicalize($class->delivery_mode);
            $deliverySummary = $class->deliverySummary();

            // Format schedule: "Mon 8:00 AM - 10:00 AM (Room 101)" or "Mon 8:00 AM - 10:00 AM · Online (Async)"
            $scheduleStr = $class->schedules
                ->map(function ($s) use ($classDeliveryMode): string {
                    $mode = ScheduleDelivery::canonicalize($s->delivery_mode ?? $classDeliveryMode);
                    $location = ScheduleDelivery::isOnline($mode)
                        ? ' · '.ScheduleDelivery::shortLabel($mode)
                        : ($s->room ? ' ('.$s->room->name.')' : '');

                    return sprintf(
                        '%s %s%s',
                        mb_substr((string) $s->day_of_week, 0, 3), // Mon
                        $s->time_range,                // 8:00 AM - 10:00 AM
                        $location
                    );
                })
                ->implode(', ');

            return [
                'id' => $class->id,
                'section' => $class->section,
                'faculty' => $class->Faculty?->full_name ?? 'TBA',
                'enrolled_count' => $enrolledCount,
                'max_slots' => $maxSlots,
                'available_slots' => $availableSlots,
                'is_full' => $maxSlots > 0 && $enrolledCount >= $maxSlots,
                'delivery_mode' => $classDeliveryMode,
                'delivery_summary' => $deliverySummary,
                'schedule' => $scheduleStr ?: 'TBA',
                'schedules' => $class->schedules->map(function ($s) use ($classDeliveryMode, $schedulingPolicy): array {
                    $mode = ScheduleDelivery::canonicalize($s->delivery_mode ?? $classDeliveryMode);

                    return [
                        'id' => $s->id,
                        'day' => $s->day_of_week,
                        'start_time' => $s->start_time instanceof \Carbon\Carbon ? $s->start_time->format('H:i') : $s->start_time,
                        'end_time' => $s->end_time instanceof \Carbon\Carbon ? $s->end_time->format('H:i') : $s->end_time,
                        'room' => ScheduleDelivery::isOnline($mode) ? ScheduleDelivery::shortLabel($mode) : $s->room?->name,
                        'delivery_mode' => $mode,
                        'checks_time_conflicts' => ScheduleDelivery::checksStudentCohortConflicts($mode, $schedulingPolicy),
                    ];
                })->values(),
                'label' => 'Section '.$class->section
                    .($maxSlots > 0 ? " • {$enrolledCount}/{$maxSlots} slots" : ' • Unlimited')
                    .($class->Faculty ? ' • '.$class->Faculty->full_name : '')
                    .($scheduleStr ? ' • '.$scheduleStr : '')
                    .($maxSlots > 0 && $enrolledCount >= $maxSlots ? ' 🚫 FULL' : ''),
            ];
        })->values();

        return response()->json($sections);
    }

    /**
     * Calculate fees for a subject.
     *
     * @return JsonResponse
     */
    public function calculateSubjectFees(Request $request, EnrollmentPolicyResolver $resolver, GeneralSettingsService $settingsService)
    {
        $request->validate([
            'subject_id' => ['required', 'integer'],
            'enrollment_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'is_modular' => ['sometimes', 'boolean'],
            'semester' => ['sometimes', 'integer', 'in:1,2'],
            'school_year' => ['sometimes', 'string', 'max:50'],
        ]);
        $subjectId = $request->input('subject_id');
        $isModular = $request->boolean('is_modular', false);

        if (! $subjectId) {
            return response()->json(['error' => 'Subject ID required'], 400);
        }

        $subject = Subject::with('course')->find($subjectId);
        if (! $subject) {
            return response()->json(['error' => 'Subject not found'], 404);
        }

        $configuration = [
            ...EnrollmentFeeCalculator::Defaults,
            'course_lecture_rate_per_unit' => (float) ($subject->course?->lec_per_unit ?? 0),
            'course_laboratory_rate_per_unit' => (float) ($subject->course?->lab_per_unit ?? 0),
        ];
        if ($request->filled('enrollment_id')) {
            $configuration = $this->feePolicy->forEnrollment(StudentEnrollment::query()->findOrFail($request->integer('enrollment_id')));
        } elseif ($request->filled('student_id')) {
            $configuration = $this->studentFeeConfiguration(Student::query()->findOrFail($request->integer('student_id')), $request, $resolver, $settingsService);
        }
        $fees = $this->feeCalculator->subject($subject->toArray(), $configuration, $isModular);

        return response()->json([
            'subject_id' => $subject->id,
            'code' => $subject->code,
            'title' => $subject->title,
            'lecture_units' => $subject->lecture,
            'laboratory_units' => $subject->laboratory,
            'lecture_fee' => $fees['lecture_fee'],
            'laboratory_fee' => $fees['laboratory_fee'],
            'is_nstp' => ($marker = mb_strtoupper(mb_trim((string) ($configuration['nstp_code_marker'] ?? 'NSTP')))) !== ''
                && str_contains(mb_strtoupper((string) $subject->code), $marker),
            'is_modular' => $isModular,
            'modular_fee' => $fees['modular_fee'],
        ]);
    }

    /**
     * Get student details with course info.
     *
     * @return JsonResponse
     */
    public function getStudentDetails(Request $request, CurriculumCapabilityResolver $capabilityResolver, EnrollmentPolicyResolver $resolver, GeneralSettingsService $settingsService)
    {
        $studentId = $request->input('student_id');

        if (! $studentId) {
            return response()->json(['error' => 'Student ID required'], 400);
        }

        $student = Student::with('Course')->find($studentId);
        if (! $student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $feeConfiguration = $this->studentFeeConfiguration($student, $request, $resolver, $settingsService);
        $targetSchoolYear = filled($request->input('school_year'))
            ? (string) $request->input('school_year')
            : $settingsService->getCurrentSchoolYearString();
        $targetSemester = $request->filled('semester')
            ? (int) $request->input('semester')
            : $settingsService->getCurrentSemester();

        $existingEnrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->forAcademicPeriod($targetSchoolYear, $targetSemester)
            ->whereNotIn('status', ['Cancelled', 'Rejected'])
            ->first();

        $checklistAnalysis = $student->course_id
            ? app(StudentChecklistService::class)->analyze($student)
            : [];

        return response()->json([
            'billing_configuration' => $feeConfiguration,
            'id' => $student->id,
            'student_id' => $student->student_id,
            'full_name' => $student->full_name,
            'email' => $student->email,
            'course_id' => $student->course_id,
            'course_code' => $student->Course?->code,
            'course_name' => $student->Course?->title,
            'curriculum_kind' => $student->Course ? $capabilityResolver->kindForCourse($student->Course) : null,
            'qualification_level' => $student->Course?->qualification_level,
            'duration_hours' => $student->Course?->duration_hours,
            'tesda_program_type' => $student->Course?->tesda_program_type,
            'duration_years' => $student->Course?->duration_years,
            'internship_hours' => $student->Course?->internship_hours,
            'bundled_qualifications' => $student->Course?->bundled_qualifications,
            'scholarship_type' => $student->scholarship_type?->value,
            'scholarship_label' => $student->scholarship_type?->getLabel(),
            'academic_year' => $student->academic_year,
            'formatted_academic_year' => $student->formatted_academic_year,
            'miscellaneous_fee' => $feeConfiguration['miscellaneous_fee_override'] ?? $student->Course?->getMiscellaneousFee() ?? EnrollmentFeeCalculator::Defaults['miscellaneous_fee_fallback'],
            'recommended_unit_cap' => 24,
            'existing_enrollment' => $existingEnrollment ? [
                'id' => $existingEnrollment->id,
                'status' => $existingEnrollment->status,
                'school_year' => $existingEnrollment->school_year,
                'semester' => $existingEnrollment->semester,
            ] : null,
            'checklist_summary' => isset($checklistAnalysis['academic_summary']) ? [
                'progress_percentage' => $checklistAnalysis['academic_summary']['progress_percentage'] ?? 0,
                'failed_retakes_count' => $checklistAnalysis['academic_summary']['failed_subjects_count'] ?? 0,
                'completed_subjects_count' => $checklistAnalysis['academic_summary']['completed_subjects_count'] ?? 0,
            ] : null,
            'active_holds' => $this->studentHoldService
                ->activeHoldsForStudent((int) $student->id)
                ->map(fn ($hold): array => $this->studentHoldService->serializeHold($hold))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Soft delete a student enrollment.
     */
    public function destroy(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->can('delete', $enrollment) || $user->can('Delete:StudentEnrollment') || $this->canManageEnrollment($user, 'update', $enrollment)), 403);

        $studentName = $enrollment->student?->full_name ?? 'Unknown Student';

        $enrollment->delete();

        return back()->with('flash', ['success' => "Enrollment for \"{$studentName}\" has been deleted."]);
    }

    /**
     * Permanently delete a student enrollment and all related records.
     */
    public function forceDestroy(int $enrollment): RedirectResponse
    {
        $enrollmentModel = StudentEnrollment::withTrashed()->findOrFail($enrollment);
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->can('forceDelete', $enrollmentModel) || $user->can('ForceDelete:StudentEnrollment') || $user->hasRole('super_admin')), 403);

        $studentName = $enrollmentModel->student?->full_name ?? 'Unknown Student';

        if ($enrollmentModel->hasFinancialRecords()) {
            return back()->with('flash', ['error' => "Enrollment for \"{$studentName}\" cannot be permanently deleted because tuition ledgers, fees, or transactions exist."]);
        }

        try {
            DB::transaction(function () use ($enrollmentModel): void {
                $enrollmentModel->subjectsEnrolled()->delete();
                $enrollmentModel->additionalFees()->delete();
                $enrollmentModel->studentTuition()->delete();
                $enrollmentModel->resources()->delete();
                $enrollmentModel->enrollmentTransactions()->delete();
                $enrollmentModel->forceDelete();
            });

            return redirect()
                ->route('administrators.enrollments.index')
                ->with('flash', ['success' => "Enrollment for \"{$studentName}\" has been permanently deleted."]);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => 'Failed to delete enrollment: '.$e->getMessage()]);
        }
    }

    /**
     * Restore a soft-deleted student enrollment.
     */
    public function restore(int $enrollment): RedirectResponse
    {
        $enrollmentModel = StudentEnrollment::withTrashed()->findOrFail($enrollment);
        $user = Auth::user();
        abort_unless($user instanceof User && ($user->can('restore', $enrollmentModel) || $user->can('Restore:StudentEnrollment') || $this->canManageEnrollment($user, 'update', $enrollmentModel)), 403);

        if (! $enrollmentModel->trashed()) {
            return back()->with('flash', ['warning' => 'This enrollment is not deleted.']);
        }

        $studentName = $enrollmentModel->student?->full_name ?? 'Unknown Student';

        $enrollmentModel->restore();

        return back()->with('flash', ['success' => "Enrollment for \"{$studentName}\" has been restored."]);
    }

    /**
     * Get recent activity log entries for an enrollment (deleted subjects/class enrollments).
     */
    public function activityLog(StudentEnrollment $enrollment): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canViewEnrollment($user, $enrollment), 403);

        $recentDeletions = Activity::query()
            ->with('causer')
            ->where(function ($query) use ($enrollment): void {
                $query->where('log_name', 'subject_enrollment')
                    ->where('event', 'deleted')
                    ->where('attribute_changes', 'like', '%"enrollment_id":'.$enrollment->id.'%');
            })
            ->orWhere(function ($query) use ($enrollment): void {
                $query->where('log_name', 'class_enrollments')
                    ->where('event', 'deleted')
                    ->where('attribute_changes', 'like', '%"student_id":'.$enrollment->student_id.'%');
            })
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->filter(function (Activity $activity) use ($enrollment): bool {
                $oldData = ($activity->attribute_changes?->toArray() ?? [])['old'] ?? [];
                if ($activity->log_name === 'subject_enrollment') {
                    return (int) ($oldData['enrollment_id'] ?? 0) === (int) $enrollment->id;
                }

                return (int) ($oldData['student_id'] ?? 0) === (int) $enrollment->student_id;
            })
            ->take(50)
            ->values()
            ->map(function (Activity $activity): array {
                $properties = $activity->attribute_changes?->toArray() ?? [];
                $oldData = $properties['old'] ?? [];

                return [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'subject_id' => $activity->subject_id,
                    'old_data' => $oldData,
                    'created_at' => $activity->created_at->toDateTimeString(),
                    'created_at_human' => $activity->created_at->diffForHumans(),
                    'causer' => $activity->causer?->name ?? 'System',
                ];
            });

        return response()->json([
            'deletions' => $recentDeletions,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    /**
     * Restore deleted subject enrollments from activity log.
     */
    public function restoreSubjects(Request $request, StudentEnrollment $enrollment, EnrollmentLoadChangeService $loadChangeService): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageEnrollment($user, 'update', $enrollment), 403);

        if ($this->isTerminalLocked($enrollment) || $enrollment->trashed()) {
            return back()->with('flash', ['error' => 'Cannot restore subjects on a cancelled, rejected, or trashed enrollment.']);
        }

        $validated = $request->validate([
            'activity_ids' => ['required', 'array', 'min:1'],
            'activity_ids.*' => ['required', 'integer', 'exists:activity_log,id'],
        ]);

        $restoredCount = 0;
        $errors = [];

        DB::transaction(function () use ($validated, $enrollment, $loadChangeService, &$restoredCount, &$errors): void {
            foreach ($validated['activity_ids'] as $activityId) {
                $activity = Activity::query()->find($activityId);
                if (! $activity) {
                    $errors[] = "Activity log #{$activityId} not found";

                    continue;
                }

                $properties = $activity->attribute_changes?->toArray() ?? [];
                $oldData = $properties['old'] ?? [];

                if ($activity->log_name === 'subject_enrollment' && $activity->event === 'deleted') {
                    if ((int) ($oldData['enrollment_id'] ?? 0) !== (int) $enrollment->id) {
                        $errors[] = "Activity #{$activityId} belongs to a different enrollment";

                        continue;
                    }

                    $existing = SubjectEnrollment::query()
                        ->where('enrollment_id', $oldData['enrollment_id'])
                        ->where('subject_id', $oldData['subject_id'])
                        ->first();

                    if ($existing) {
                        $errors[] = "Subject enrollment for subject_id {$oldData['subject_id']} already exists";

                        continue;
                    }

                    $restorable = array_intersect_key(
                        $oldData,
                        array_flip((new SubjectEnrollment)->getFillable()),
                    );

                    $restored = new SubjectEnrollment($restorable);

                    if (isset($oldData['id'])) {
                        $restored->id = $oldData['id'];
                    }

                    $restored->save();

                    $restoredCount++;

                    if (! empty($oldData['class_id'])) {
                        app(\App\Services\ClassEnrollmentService::class)->enrollOnce(
                            (int) $oldData['student_id'],
                            (int) $oldData['class_id'],
                            ['status' => true],
                        );
                    }
                }
            }

            if ($restoredCount > 0) {
                $loadChangeService->recalculateEnrollmentTuition($enrollment);
            }
        });

        if ($restoredCount > 0) {
            $message = "Successfully restored {$restoredCount} subject enrollment(s) and updated tuition.";
            if ($errors !== []) {
                $message .= ' Some items could not be restored: '.implode('; ', $errors);
            }

            return back()->with('flash', ['success' => $message]);
        }

        return back()->with('flash', ['error' => 'No items were restored. '.implode('; ', $errors)]);
    }

    public function yearLevelByDepartment(Request $request, GeneralSettingsService $settingsService): JsonResponse
    {
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYearString = $settingsService->getCurrentSchoolYearString();
        $department = $request->input('department', 'all');

        $query = StudentEnrollment::query()
            ->withTrashed()
            ->where('student_enrollment.school_year', $currentSchoolYearString)
            ->where('student_enrollment.semester', $currentSemester)
            ->where('student_enrollment.status', '!=', $this->enrollmentPipelineService->getPendingStatus());

        if ($department !== 'all') {
            $query->join('courses', 'student_enrollment.course_id', '=', 'courses.id')
                ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                ->whereRaw('TRIM(departments.code) = ?', [mb_trim($department)]);
        }

        $yearLevelData = $query
            ->selectRaw('student_enrollment.academic_year as year_level, count(*) as count')
            ->groupBy('student_enrollment.academic_year')
            ->get()
            ->map(fn ($item): array => [
                'year_level' => $item->year_level,
                'count' => $item->count,
            ]);

        return response()->json([
            'by_year_level' => $yearLevelData,
            'department' => $department,
        ]);
    }

    public function departmentByYearLevel(Request $request, GeneralSettingsService $settingsService): JsonResponse
    {
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYearString = $settingsService->getCurrentSchoolYearString();
        $yearLevel = $request->input('year_level', 'all');

        $query = StudentEnrollment::query()
            ->withTrashed()
            ->where('student_enrollment.school_year', $currentSchoolYearString)
            ->where('student_enrollment.semester', $currentSemester)
            ->where('student_enrollment.status', '!=', $this->enrollmentPipelineService->getPendingStatus());

        $query->join('courses', 'student_enrollment.course_id', '=', 'courses.id')
            ->leftJoin('departments', 'courses.department_id', '=', 'departments.id');

        if ($yearLevel !== 'all') {
            $query->where('student_enrollment.academic_year', (int) $yearLevel);
        }

        $departmentData = $query
            ->selectRaw('TRIM(departments.code) as department, count(*) as count')
            ->groupByRaw('TRIM(departments.code)')
            ->get()
            ->map(fn ($item): array => [
                'department' => $item->department ?? 'Unknown',
                'count' => $item->count,
            ]);

        return response()->json([
            'by_department' => $departmentData,
            'year_level' => $yearLevel,
        ]);
    }

    /**
     * Generate enrollment report data for PDF preview.
     *
     * Supports report types:
     * - enrolled_by_course: List of students enrolled in a specific course/program
     * - enrolled_by_subject: List of students enrolled in a specific subject
     * - enrollment_summary: Summary counts by department, course, and year level
     */
    public function enrollmentReportData(Request $request, GeneralSettingsService $settingsService): JsonResponse
    {
        $validated = $this->validateEnrollmentReportFilters($request);
        $payload = $this->buildEnrollmentReportPayload($validated, $settingsService);

        return response()->json($payload);
    }

    public function enrollmentReportPreviewPdf(Request $request, GeneralSettingsService $settingsService): JsonResponse
    {
        $validated = $this->validateEnrollmentReportFilters($request);
        $payload = $this->buildEnrollmentReportPayload($validated, $settingsService);
        $reportType = Str::slug((string) (($payload['report']['type'] ?? 'enrollment-report')));

        $downloadName = sprintf('enrollment-report-%s-%s.pdf', $reportType, now()->format('Y-m-d_His'));

        GenerateEnrollmentReportPreviewPdfJob::dispatch($payload, $downloadName, (int) Auth::id());

        return response()->json([
            'message' => 'Enrollment report preview queued. You will be notified when the PDF is ready.',
        ], 202);
    }

    public function enrollmentReportPdf(
        Request $request,
        GeneralSettingsService $settingsService,
        PdfGenerationService $pdfGenerationService,
    ): BinaryFileResponse {
        $validated = $this->validateEnrollmentReportFilters($request);
        $payload = $this->buildEnrollmentReportPayload($validated, $settingsService);
        $reportType = Str::slug((string) data_get($payload, 'report.type', 'report'));
        $variant = $this->resolveEnrollmentReportVariant($request->string('variant')->toString());
        $filename = sprintf('enrollment-report-%s-%s-%s.pdf', $reportType, $variant, now()->format('YmdHis'));
        $temporaryPath = tempnam(sys_get_temp_dir(), 'enrollment_report_');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary enrollment report file.');
        }

        $pdfGenerationService->generatePdfFromView('pdf.enrollment-report', ['data' => $payload, 'variant' => $variant], $temporaryPath, [
            'landscape' => true,
            'format' => 'A4',
            'headless' => true,
            'no-sandbox' => true,
            'disable-dev-shm-usage' => true,
            'print-to-pdf-no-header' => true,
            'disable-gpu' => true,
            'no-first-run' => true,
            'disable-extensions' => true,
            'virtual-time-budget' => 10000,
        ]);

        return response()->download($temporaryPath, $filename)->deleteFileAfterSend(true);
    }

    public function enrollmentReportExport(Request $request, GeneralSettingsService $settingsService): BinaryFileResponse
    {
        $validated = $this->validateEnrollmentReportFilters($request);
        $payload = $this->buildEnrollmentReportPayload($validated, $settingsService);
        $reportData = $payload['report'];
        $variant = $this->resolveEnrollmentReportVariant($request->string('variant')->toString());

        [$headings, $rows] = $this->buildEnrollmentReportExportData($reportData, $variant);

        $fileName = sprintf(
            'enrollment-report-%s-%s-%s.xlsx',
            Str::slug((string) ($reportData['type'] ?? 'report')),
            $variant,
            now()->format('Y-m-d_His')
        );

        return Excel::download(new EnrollmentReportExport($headings, $rows), $fileName);
    }

    /**
     * Return available subjects for the current semester (for report filters).
     *
     * Supports optional ?search= query param for searching by code or title.
     * Only returns subjects that have at least one class section this semester.
     * Includes enrolled student count and class section count per subject.
     */
    public function reportSubjectOptions(Request $request, GeneralSettingsService $settingsService): JsonResponse
    {
        $schoolYearString = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();
        $search = $request->query('search', '');

        $query = SubjectEnrollment::query()
            ->where('school_year', $schoolYearString)
            ->where('semester', $semester)
            ->whereHas('subject')
            ->whereHas('class')
            ->with(['subject:id,code,title,units,lecture,laboratory', 'class:id,subject_id,section,subject_code']);

        if (is_string($search) && mb_strlen($search) >= 1) {
            $like = '%'.mb_strtolower($search).'%';
            $query->whereHas('subject', function ($q) use ($like): void {
                $q->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$like]);
            });
        }

        $subjectEnrollments = $query->get();

        $subjects = $subjectEnrollments
            ->groupBy(fn ($se) => $se->subject?->code ?? 'Unknown')
            ->map(function (Collection $items, string $code): array {
                $subject = $items->first()?->subject;
                $classSections = $items->pluck('class')->filter()->unique('id');

                return [
                    'id' => $code, // Use code as the ID for the Combobox value
                    'code' => $code,
                    'title' => $subject?->title,
                    'units' => $subject?->units ?? 0,
                    'label' => $code.' - '.$subject?->title,
                    'enrolled_count' => $items->count(),
                    'class_count' => $classSections->count(),
                    'sections' => $classSections->pluck('section')->filter()->unique()->values()->all(),
                ];
            })
            ->sortBy('code')
            ->values();

        return response()->json(['subjects' => $subjects]);
    }

    /**
     * Return available courses for the current semester (for report filters).
     */
    public function reportCourseOptions(GeneralSettingsService $settingsService): JsonResponse
    {
        $schoolYearVariants = $settingsService->getCurrentSchoolYearVariants();
        $semester = $settingsService->getCurrentSemester();

        $courseIds = StudentEnrollment::query()
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $semester)
            ->whereNotNull('course_id')
            ->distinct()
            ->pluck('course_id')
            ->filter(fn (mixed $courseId): bool => is_numeric($courseId))
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();

        $courses = Course::query()
            ->whereIn('id', $courseIds)
            ->with('department:id,code')
            ->get(['id', 'code', 'title', 'department_id'])
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'department_id' => $course->department_id,
                'department' => $course->department?->code,
                'label' => $course->code.' - '.$course->title,
            ])
            ->sortBy('code')
            ->values();

        return response()->json(['courses' => $courses]);
    }

    /**
     * @return array{can_verify_head: bool, can_verify_cashier: bool, can_delete: bool, can_reopen: bool, is_super_admin: bool}
     */
    private function enrollmentIndexPermissions(User $user): array
    {
        return [
            'can_verify_head' => (
                $user->can('verify_by_head_dept_guest::enrollment')
                || $user->hasRole('super_admin')
                || $this->enrollmentPipelineService->canUserPerformStep(
                    $user,
                    $this->enrollmentPipelineService->getStepByActionType('department_verification') ?? ['allowed_roles' => []]
                )
            ),
            'can_verify_cashier' => (
                $user->can('verify_by_cashier_guest::enrollment')
                || $user->hasRole('super_admin')
                || $this->enrollmentPipelineService->canUserPerformStep(
                    $user,
                    $this->enrollmentPipelineService->getStepByActionType('cashier_verification') ?? ['allowed_roles' => []]
                )
            ),
            'can_delete' => $user->can('Delete:StudentEnrollment') || $user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('registrar'),
            'can_reopen' => $user->can('Reopen:StudentEnrollment') || $user->hasRole('super_admin'),
            'is_super_admin' => $user->hasRole('super_admin'),
        ];
    }

    /**
     * Resolve the active enrollment pipeline (either Dynamic Policy Engine v1 or Legacy Pipeline)
     * plus configuration metadata for the /administrators/enrollments workspace.
     *
     * @return array{enrollment_pipeline: array<string, mixed>, pipeline_meta: array<string, mixed>}
     */
    private function resolveActivePipelinePayload(User $user): array
    {
        $canConfigure = $user->can('updateEnrollmentPipeline', GeneralSetting::class) || $user->hasRole('super_admin');
        $configureUrl = route('administrators.system-management.enrollment-pipeline.index');
        $availableRoles = Role::query()->orderBy('name')->pluck('name')->values()->all();

        if (Feature::active(DynamicEnrollmentPolicies::class)) {
            $activePolicies = EnrollmentPolicy::query()
                ->where('is_enabled', true)
                ->whereHas('activeVersion')
                ->with('activeVersion')
                ->orderByRaw('CASE WHEN school_id IS NULL AND course_id IS NULL AND student_type IS NULL AND school_year IS NULL AND semester IS NULL THEN 0 ELSE 1 END')
                ->orderBy('id')
                ->get();

            /** @var EnrollmentPolicy|null $primaryPolicy */
            $primaryPolicy = $activePolicies->first();

            if ($primaryPolicy !== null && $primaryPolicy->activeVersion !== null) {
                $configuration = $primaryPolicy->activeVersion->configuration;
                $policySteps = collect(data_get($configuration, 'workflow.steps', []));

                if ($policySteps->isNotEmpty()) {
                    $entryStep = $policySteps->firstWhere('entry', true) ?? $policySteps->first();
                    $completionStep = $policySteps->firstWhere('terminal', true) ?? $policySteps->last();

                    $steps = $policySteps->map(function (array $step) use ($completionStep): array {
                        $handlers = collect($step['actions'] ?? [])->pluck('handler');
                        $actionType = match (true) {
                            $handlers->contains('enrollment.verify_payment') => 'cashier_verification',
                            $handlers->contains('enrollment.verify_academic') => 'department_verification',
                            default => 'standard',
                        };
                        $fallback = collect($step['transitions'] ?? [])->firstWhere('fallback', true)
                            ?? collect($step['transitions'] ?? [])->first();
                        $status = (string) ($step['status'] ?? $step['label'] ?? $step['key']);

                        return [
                            'key' => (string) $step['key'],
                            'status' => $status,
                            'label' => (string) ($step['label'] ?? $status),
                            'color' => (string) ($step['color'] ?? ((bool) ($step['terminal'] ?? false) ? 'green' : 'blue')),
                            'allowed_roles' => [],
                            'is_core' => false,
                            'action_type' => $actionType,
                            'next_step_key' => is_array($fallback) ? ($fallback['to'] ?? null) : null,
                            'is_completion' => (bool) ($step['terminal'] ?? false) || $step['key'] === ($completionStep['key'] ?? null),
                        ];
                    })->values()->all();

                    $deptStep = collect($steps)->firstWhere('action_type', 'department_verification');
                    $cashierStep = collect($steps)->firstWhere('action_type', 'cashier_verification') ?? collect($steps)->last();
                    $entryStatus = (string) ($entryStep['status'] ?? $entryStep['label'] ?? ($steps[0]['status'] ?? 'Pending'));

                    $statusClasses = [];
                    foreach ($steps as $s) {
                        $statusClasses[$s['status']] = $this->enrollmentPipelineService->getStatusColorClasses()[$s['status']]
                            ?? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border-blue-200';
                    }

                    return [
                        'enrollment_pipeline' => [
                            'submitted_label' => (string) ($entryStep['label'] ?? 'Submitted'),
                            'entry_step_key' => (string) ($entryStep['key'] ?? ($steps[0]['key'] ?? 'pending')),
                            'completion_step_key' => (string) ($completionStep['key'] ?? ($steps[count($steps) - 1]['key'] ?? 'enrolled')),
                            'pending_status' => $entryStatus,
                            'pending_label' => (string) ($entryStep['label'] ?? $entryStatus),
                            'pending_color' => (string) ($entryStep['color'] ?? 'amber'),
                            'pending_roles' => [],
                            'department_verified_status' => (string) ($deptStep['status'] ?? ($steps[1]['status'] ?? $entryStatus)),
                            'department_verified_label' => (string) ($deptStep['label'] ?? 'Department Verification'),
                            'department_verified_color' => (string) ($deptStep['color'] ?? 'blue'),
                            'department_verified_roles' => [],
                            'cashier_verified_status' => (string) ($cashierStep['status'] ?? 'Enrolled'),
                            'cashier_verified_label' => (string) ($cashierStep['label'] ?? 'Enrolled'),
                            'cashier_verified_color' => (string) ($cashierStep['color'] ?? 'green'),
                            'cashier_verified_roles' => [],
                            'additional_steps' => [],
                            'steps' => $steps,
                            'status_options' => collect($steps)->map(fn (array $s): array => [
                                'value' => $s['status'],
                                'label' => $s['label'],
                            ])->values()->all(),
                            'status_classes' => $statusClasses,
                        ],
                        'pipeline_meta' => [
                            'engine' => 'policy_v1',
                            'policy_id' => $primaryPolicy->id,
                            'policy_name' => $primaryPolicy->name,
                            'policy_version' => $primaryPolicy->activeVersion->version,
                            'active_policies_count' => $activePolicies->count(),
                            'configure_url' => $configureUrl,
                            'can_configure' => $canConfigure,
                            'available_roles' => $availableRoles,
                        ],
                    ];
                }
            }
        }

        return [
            'enrollment_pipeline' => [
                ...$this->enrollmentPipelineService->getConfiguration(),
                'steps' => $this->enrollmentPipelineService->getSteps(),
                'status_options' => $this->enrollmentPipelineService->getStatusOptions(),
                'status_classes' => $this->enrollmentPipelineService->getStatusColorClasses(),
            ],
            'pipeline_meta' => [
                'engine' => 'legacy',
                'policy_id' => null,
                'policy_name' => null,
                'policy_version' => null,
                'active_policies_count' => 0,
                'configure_url' => $configureUrl,
                'can_configure' => $canConfigure,
                'available_roles' => $availableRoles,
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, to: string, to_status: string, requires_reason: bool, needs_payment: bool, action_type: string}>
     */
    private function resolvePolicyRowTransitions(StudentEnrollment $enrollment, User $user): array
    {
        $enrollment->loadMissing('policySnapshot');
        if ($enrollment->policySnapshot === null) {
            return [];
        }

        $steps = collect(data_get($enrollment->policySnapshot->configuration, 'workflow.steps', []));
        $available = $this->transitionEngine->availableTransitions($enrollment, $user);

        return collect($available)->map(function (array $transition) use ($steps): array {
            $target = $steps->firstWhere('key', $transition['to'] ?? null);
            $handlers = is_array($target) ? collect($target['actions'] ?? [])->pluck('handler') : collect();
            $needsPayment = $handlers->contains('enrollment.verify_payment');
            $actionType = match (true) {
                $needsPayment => 'cashier_verification',
                $handlers->contains('enrollment.verify_academic') => 'department_verification',
                default => 'standard',
            };
            $toStatus = is_array($target)
                ? (string) ($target['status'] ?? $target['label'] ?? $target['key'])
                : (string) ($transition['to'] ?? '');

            return [
                'key' => (string) $transition['key'],
                'label' => (string) ($transition['label'] ?? $transition['key']),
                'to' => (string) ($transition['to'] ?? ''),
                'to_status' => $toStatus,
                'requires_reason' => (bool) ($transition['requires_reason'] ?? false),
                'needs_payment' => $needsPayment,
                'action_type' => $actionType,
            ];
        })->values()->all();
    }

    /**
     * @param  array{active_count: int, blocking_count: int, offices: list<string>}|null  $holdSummary
     * @return array<string, mixed>
     */
    private function serializeEnrollmentRow(StudentEnrollment $enrollment, User $user, ?array $holdSummary = null): array
    {
        $isCancelled = $enrollment->terminal_outcome === EnrollmentCancellationService::OUTCOME;
        $isPolicyRuntime = $enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1 && $enrollment->policySnapshot !== null;
        $allowedTransitions = [];

        if ($isPolicyRuntime) {
            $allowedTransitions = (! $enrollment->trashed() && ! $isCancelled)
                ? $this->resolvePolicyRowTransitions($enrollment, $user)
                : [];
            $primary = $allowedTransitions[0] ?? null;
            $nextStep = $primary ? [
                'key' => $primary['to'],
                'status' => $primary['to_status'],
                'label' => $primary['label'],
                'action_type' => $primary['action_type'],
                'transition_key' => $primary['key'],
                'requires_reason' => $primary['requires_reason'],
            ] : null;
            $canAdvance = count($allowedTransitions) > 0;
        } else {
            $legacyNext = $this->enrollmentPipelineService->getNextStep($enrollment->status);
            $nextStep = $legacyNext ? [
                'key' => (string) ($legacyNext['key'] ?? ''),
                'status' => (string) ($legacyNext['status'] ?? ''),
                'label' => (string) ($legacyNext['label'] ?? ''),
                'action_type' => (string) ($legacyNext['action_type'] ?? 'standard'),
                'transition_key' => null,
                'requires_reason' => false,
            ] : null;
            $canAdvance = ! $enrollment->trashed()
                && ! $isCancelled
                && $legacyNext !== null
                && $this->enrollmentPipelineService->canUserAdvanceToNextStep($user, $enrollment->status);
        }

        $overallTuition = $enrollment->studentTuition ? (float) $enrollment->studentTuition->overall_tuition : null;
        $balanceTuition = $enrollment->studentTuition ? (float) $enrollment->studentTuition->total_balance : null;
        $paidTuition = ($overallTuition !== null && $balanceTuition !== null)
            ? max(0.0, round($overallTuition - $balanceTuition, 2))
            : null;

        return [
            'id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'student_number' => $enrollment->student?->student_id,
            'student_name' => $enrollment->student?->full_name,
            'student_email' => $enrollment->student?->email,
            'course_id' => $enrollment->course_id,
            'course' => $enrollment->course?->code,
            'course_title' => $enrollment->course?->title,
            'department' => $enrollment->course?->department?->code,
            'status' => $enrollment->status ?? 'N/A',
            'terminal_outcome' => $enrollment->terminal_outcome,
            'workflow_runtime' => $enrollment->workflow_runtime,
            'current_step_key' => $enrollment->current_step_key,
            'school_year' => $enrollment->school_year,
            'semester' => $enrollment->semester,
            'academic_year' => $enrollment->academic_year,
            'subjects_count' => $enrollment->subjects_enrolled_count ?? 0,
            'tuition' => $enrollment->studentTuition ? [
                'overall' => $overallTuition,
                'balance' => $balanceTuition,
                'paid' => $paidTuition,
            ] : null,
            'next_step' => $nextStep,
            'allowed_transitions' => $allowedTransitions,
            'can_advance' => $canAdvance,
            'active_holds_count' => (int) ($holdSummary['active_count'] ?? 0),
            'blocking_holds_count' => (int) ($holdSummary['blocking_count'] ?? 0),
            'hold_offices' => $holdSummary['offices'] ?? [],
            'created_at' => $enrollment->created_at?->toDateTimeString(),
            'deleted_at' => $enrollment->deleted_at?->toDateTimeString(),
            'is_trashed' => $enrollment->trashed(),
        ];
    }

    /** @return array<string, mixed> */
    private function studentFeeConfiguration(Student $student, Request $request, EnrollmentPolicyResolver $resolver, GeneralSettingsService $settingsService): array
    {
        $feeConfiguration = EnrollmentFeeCalculator::Defaults;
        if (Feature::active(DynamicEnrollmentPolicies::class)) {
            $context = new EnrollmentContext(
                schoolId: $student->school_id,
                studentType: $student->student_type?->value,
                courseId: $student->course_id,
                schoolYear: (string) $request->input('school_year', $settingsService->getCurrentSchoolYearString()),
                semester: (int) $request->input('semester', $settingsService->getCurrentSemester()),
                yearLevel: $student->academic_year,
            );
            $feeConfiguration = data_get($resolver->resolve($context)->configuration, 'billing.configuration', []);
        }
        $feeConfiguration = array_filter($feeConfiguration, static fn (mixed $value): bool => $value !== null);
        $feeConfiguration['course_lecture_rate_per_unit'] = (float) ($student->Course?->lec_per_unit ?? 0);
        $feeConfiguration['course_laboratory_rate_per_unit'] = (float) ($student->Course?->lab_per_unit ?? 0);

        return $feeConfiguration;
    }

    private function resolveEnrollmentReportVariant(string $variant): string
    {
        return in_array($variant, [
            'full_roster', 'course_signoff', 'compact_roster',
            'attendance_roster', 'faculty_roster', 'student_list',
            'department_breakdown', 'leadership_summary', 'status_summary',
        ], true) ? $variant : 'full_roster';
    }

    /** @return array<string, mixed> */
    private function workflowView(StudentEnrollment $enrollment, mixed $actor): array
    {
        $outstandingPayments = $this->enrollmentCancellationService->outstandingPayments($enrollment);

        if ($enrollment->workflow_runtime !== StudentEnrollment::WorkflowRuntimePolicyV1) {
            return [
                'current_step_key' => null,
                'terminal_outcome' => $enrollment->terminal_outcome,
                'steps' => [],
                'allowed_transitions' => [],
                'allowed_payment_methods' => [],
                'can_cancel' => $actor instanceof User
                    && in_array($enrollment->terminal_outcome, [null, 'completed'], true)
                    && ($actor->can('Cancel:StudentEnrollment') || $actor->hasRole('super_admin')),
                'outstanding_payments' => $outstandingPayments,
            ];
        }

        $enrollment->loadMissing('policySnapshot');
        $steps = collect(data_get($enrollment->policySnapshot?->configuration, 'workflow.steps', []));
        $available = $actor instanceof User ? $this->transitionEngine->availableTransitions($enrollment, $actor) : [];

        return [
            'current_step_key' => $enrollment->current_step_key,
            'terminal_outcome' => $enrollment->terminal_outcome,
            'can_cancel' => $actor instanceof User
                && in_array($enrollment->terminal_outcome, [null, 'completed'], true)
                && ($actor->can('Cancel:StudentEnrollment') || $actor->hasRole('super_admin')),
            'outstanding_payments' => $outstandingPayments,
            'allowed_payment_methods' => array_values(data_get(
                $enrollment->policySnapshot?->configuration,
                'billing.allowed_payment_methods',
                [],
            )),
            'steps' => $steps->map(fn (array $step): array => [
                'key' => $step['key'],
                'label' => $step['label'] ?? $step['key'],
                'terminal' => (bool) ($step['terminal'] ?? false),
            ])->values()->all(),
            'allowed_transitions' => collect($available)->map(function (array $transition) use ($steps): array {
                $target = $steps->firstWhere('key', $transition['to'] ?? null);
                $paymentAction = is_array($target)
                    ? collect($target['actions'] ?? [])->firstWhere('handler', 'enrollment.verify_payment')
                    : null;

                return [
                    'key' => $transition['key'],
                    'label' => $transition['label'] ?? $transition['key'],
                    'to' => $transition['to'],
                    'requires_reason' => (bool) ($transition['requires_reason'] ?? false),
                    'target_actions' => is_array($target)
                        ? collect($target['actions'] ?? [])->pluck('handler')->filter()->values()->all()
                        : [],
                    'payment_configuration' => is_array($paymentAction)
                        ? $paymentAction['configuration'] ?? []
                        : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array{
     *     report_type: string,
     *     course_filter?: string,
     *     subject_filter?: string,
     *     department_filter?: string,
     *     year_level_filter?: string,
     *     status_filter?: string
     * }
     */
    private function validateEnrollmentReportFilters(Request $request): array
    {
        /** @var array{
         *     report_type: string,
         *     course_filter?: string,
         *     subject_filter?: string,
         *     department_filter?: string,
         *     year_level_filter?: string,
         *     status_filter?: string
         * } $validated
         */
        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:enrolled_by_course,enrolled_by_subject,enrollment_summary'],
            'course_filter' => ['nullable', 'string'],
            'subject_filter' => ['nullable', 'string'],
            'department_filter' => ['nullable', 'string'],
            'year_level_filter' => ['nullable', 'string'],
            'status_filter' => ['nullable', 'string'],
        ]);

        return $validated;
    }

    /**
     * @param  array{
     *     report_type: string,
     *     course_filter?: string,
     *     subject_filter?: string,
     *     department_filter?: string,
     *     year_level_filter?: string,
     *     status_filter?: string
     * }  $validated
     * @return array{
     *     report: array<string, mixed>,
     *     school: array{name: string, logo: string, contact: string, email: string, address: string},
     *     school_year: string,
     *     semester: string,
     *     generated_at: string,
     *     generated_by: string
     * }
     */
    private function buildEnrollmentReportPayload(array $validated, GeneralSettingsService $settingsService): array
    {
        $schoolYearString = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();
        $generalSettings = $settingsService->getGlobalSettingsModel();
        $siteSettings = app(SiteSettings::class);

        $school = [
            'name' => $generalSettings?->school_portal_title ?? $generalSettings?->site_name ?? $siteSettings->getOrganizationName(),
            'logo' => $this->resolveReportLogoFromSettings($generalSettings?->school_portal_logo, $siteSettings),
            'contact' => $generalSettings?->support_phone ?? $siteSettings->getSupportPhone() ?? '',
            'email' => $generalSettings?->support_email ?? $siteSettings->getSupportEmail() ?? '',
            'address' => $siteSettings->getOrganizationAddress() ?? '',
        ];

        $baseQuery = StudentEnrollment::query()
            ->where('school_year', $schoolYearString)
            ->where('semester', $semester);

        if (($validated['status_filter'] ?? 'active') === 'active') {
            $baseQuery->whereNull('deleted_at');
        } elseif (($validated['status_filter'] ?? null) === 'all') {
            $baseQuery->withTrashed();
        }

        $reportData = match ($validated['report_type']) {
            'enrolled_by_course' => $this->getEnrolledByCourseReport($baseQuery, $validated),
            'enrolled_by_subject' => $this->getEnrolledBySubjectReport($validated, $schoolYearString, $semester),
            'enrollment_summary' => $this->getEnrollmentSummaryReport($baseQuery, $validated),
        };

        return [
            'report' => $reportData,
            'school' => $school,
            'school_year' => $schoolYearString,
            'semester' => $settingsService->getAvailableSemesters()[$semester] ?? '',
            'generated_at' => now()->format('F d, Y'),
            'generated_by' => Auth::user()?->name ?? 'System',
        ];
    }

    private function resolveReportLogoFromSettings(?string $generalSettingsLogo, SiteSettings $siteSettings): string
    {
        $logoValue = $generalSettingsLogo;
        if (! is_string($logoValue) || mb_trim($logoValue) === '') {
            $logoValue = $siteSettings->logo;
        }

        if (! is_string($logoValue) || mb_trim($logoValue) === '') {
            return '';
        }

        if (filter_var($logoValue, FILTER_VALIDATE_URL)) {
            return $logoValue;
        }

        if (str_starts_with($logoValue, '/')) {
            return $logoValue;
        }

        try {
            return Storage::url($logoValue);
        } catch (Throwable) {
            return $logoValue;
        }
    }

    /**
     * @param  array{
     *     report: array<string, mixed>,
     *     school: array{name: string, logo: string, contact: string, email: string, address: string},
     *     school_year: string,
     *     semester: string,
     *     generated_at: string,
     *     generated_by: string
     * }  $payload
     */
    private function generateEnrollmentReportPdfFallback(array $payload): string
    {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        /** @var array<string, mixed> $report */
        $report = $payload['report'];
        /** @var array<string, mixed> $school */
        $school = $payload['school'];
        $reportType = (string) ($report['type'] ?? '');

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 7, $this->pdfText((string) ($school['name'] ?? 'KoAkademy')), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, $this->pdfText((string) ($school['address'] ?? '')), 0, 1, 'C');
        $pdf->Cell(0, 5, $this->pdfText('Tel: '.($school['contact'] ?? '').' | Email: '.($school['email'] ?? '')), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, $this->pdfText((string) ($report['title'] ?? 'Enrollment Report')), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, $this->pdfText((string) ($report['subtitle'] ?? '')), 0, 1, 'C');
        $pdf->Ln(1);

        $metaLeft = 'School Year: '.($payload['school_year'] ?? '').' | Semester: '.($payload['semester'] ?? '');
        $metaRight = 'Generated: '.($payload['generated_at'] ?? '').' | By: '.($payload['generated_by'] ?? '');
        $pdf->Cell(140, 5, $this->pdfText($metaLeft), 0, 0, 'L');
        $pdf->Cell(137, 5, $this->pdfText($metaRight), 0, 1, 'R');
        $pdf->Ln(2);

        if ($reportType === 'enrolled_by_course') {
            /** @var array<int, array<string, mixed>> $students */
            $students = $report['students'] ?? [];
            $this->renderPdfTable(
                $pdf,
                ['No.', 'Student ID', 'Full Name', 'Course', 'Department', 'Year Level', 'Subjects', 'Status'],
                [10, 24, 55, 52, 22, 18, 18, 35],
                collect($students)->map(fn (array $student): array => [
                    (string) ($student['no'] ?? '—'),
                    (string) ($student['student_id'] ?? '—'),
                    Str::limit((string) ($student['full_name'] ?? '—'), 36),
                    Str::limit((string) ($student['course'] ?? '—'), 30),
                    (string) ($student['department'] ?? '—'),
                    isset($student['year_level']) ? 'Year '.$student['year_level'] : '—',
                    (string) ($student['subjects_count'] ?? '—'),
                    Str::limit((string) ($student['status'] ?? '—'), 20),
                ])->all()
            );
        } elseif ($reportType === 'enrolled_by_subject') {
            /** @var array<int, array<string, mixed>> $subjectGroups */
            $subjectGroups = $report['subject_groups'] ?? [];

            foreach ($subjectGroups as $group) {
                if ($pdf->GetY() > 170) {
                    $pdf->AddPage();
                }

                $pdf->SetFont('Arial', 'B', 9);
                $heading = ($group['subject_code'] ?? '—').' - '.($group['subject_title'] ?? 'Unknown Subject');
                $pdf->Cell(0, 6, $this->pdfText(Str::limit($heading, 120)), 1, 1, 'L');

                /** @var array<int, array<string, mixed>> $students */
                $students = $group['students'] ?? [];
                $this->renderPdfTable(
                    $pdf,
                    ['No.', 'Student ID', 'Full Name', 'Course', 'Year', 'Section', 'Schedule'],
                    [10, 24, 56, 40, 16, 24, 64],
                    collect($students)->map(fn (array $student): array => [
                        (string) ($student['no'] ?? '—'),
                        (string) ($student['student_id'] ?? '—'),
                        Str::limit((string) ($student['full_name'] ?? '—'), 34),
                        Str::limit((string) ($student['course'] ?? '—'), 22),
                        isset($student['year_level']) ? (string) $student['year_level'] : '—',
                        (string) ($student['section'] ?? '—'),
                        Str::limit((string) ($student['class_schedule'] ?? '—'), 40),
                    ])->all()
                );
                $pdf->Ln(1);
            }
        } elseif ($reportType === 'enrollment_summary') {
            $total = (int) ($report['total_enrolled'] ?? 0);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, $this->pdfText('Total Enrolled: '.$total), 0, 1, 'L');
            $pdf->Ln(1);

            /** @var array<int, array<string, mixed>> $byDepartment */
            $byDepartment = $report['by_department'] ?? [];
            $this->renderPdfTable(
                $pdf,
                ['Department', 'Count', 'Percentage'],
                [90, 35, 40],
                collect($byDepartment)->map(function (array $item) use ($total): array {
                    $count = (int) ($item['count'] ?? 0);
                    $percentage = $total > 0 ? round(($count / $total) * 100, 1).'%' : '0%';

                    return [(string) ($item['department'] ?? 'Unknown'), (string) $count, $percentage];
                })->all()
            );

            /** @var array<int, array<string, mixed>> $byCourse */
            $byCourse = $report['by_course'] ?? [];
            $this->renderPdfTable(
                $pdf,
                ['Course', 'Title', 'Department', 'Count', 'Percentage'],
                [34, 78, 28, 24, 30],
                collect($byCourse)->map(function (array $item) use ($total): array {
                    $count = (int) ($item['count'] ?? 0);
                    $percentage = $total > 0 ? round(($count / $total) * 100, 1).'%' : '0%';

                    return [
                        (string) ($item['course_code'] ?? '—'),
                        Str::limit((string) ($item['course_title'] ?? '—'), 46),
                        (string) ($item['department'] ?? '—'),
                        (string) $count,
                        $percentage,
                    ];
                })->all()
            );
        }

        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, $this->pdfText('This is a system-generated report.'), 0, 1, 'R');

        /** @var string|bool $pdfOutput */
        $pdfOutput = $pdf->Output('S');
        if (! is_string($pdfOutput)) {
            throw new Exception('FPDF failed to generate report output.');
        }

        return $pdfOutput;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, int>  $widths
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderPdfTable(FPDF $pdf, array $headers, array $widths, array $rows): void
    {
        $pdf->SetFont('Arial', 'B', 8);

        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index] ?? 20, 6, $this->pdfText($header), 1, 0, 'L');
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8);
        foreach ($rows as $row) {
            if ($pdf->GetY() > 185) {
                $pdf->AddPage();
            }

            foreach (array_keys($headers) as $index) {
                $value = $row[$index] ?? '—';
                $pdf->Cell($widths[$index] ?? 20, 6, $this->pdfText($value), 1, 0, 'L');
            }
            $pdf->Ln();
        }

        if ($rows === []) {
            $pdf->Cell(array_sum($widths), 6, $this->pdfText('No data available.'), 1, 1, 'C');
        }

        $pdf->Ln(2);
    }

    private function pdfText(string $value): string
    {
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function buildEnrollmentReportExportData(array $reportData, string $variant = 'full_roster'): array
    {
        $type = $reportData['type'] ?? '';

        if ($type === 'enrolled_by_course') {
            $headings = match ($variant) {
                'course_signoff' => ['No.', 'Student ID', 'Full Name', 'Course', 'Year Level', 'Status', 'Acknowledgement'],
                'compact_roster' => ['No.', 'Student ID', 'Full Name', 'Course', 'Year Level'],
                default => ['No.', 'Student ID', 'Full Name', 'Course', 'Department', 'Year Level', 'Subjects', 'Status'],
            };
            $rows = collect($reportData['students'] ?? [])->map(function (array $student) use ($variant): array {
                $yearLevel = isset($student['year_level']) ? 'Year '.$student['year_level'] : null;

                return match ($variant) {
                    'course_signoff' => [$student['no'] ?? null, $student['student_id'] ?? null, $student['full_name'] ?? null, $student['course'] ?? null, $yearLevel, $student['status'] ?? null, null],
                    'compact_roster' => [$student['no'] ?? null, $student['student_id'] ?? null, $student['full_name'] ?? null, $student['course'] ?? null, $yearLevel],
                    default => [$student['no'] ?? null, $student['student_id'] ?? null, $student['full_name'] ?? null, $student['course'] ?? null, $student['department'] ?? null, $yearLevel, $student['subjects_count'] ?? null, $student['status'] ?? null],
                };
            })->values()->all();

            return [$headings, $rows];
        }

        if ($type === 'enrolled_by_subject') {
            $headings = match ($variant) {
                'attendance_roster' => ['Subject Code', 'Subject Title', 'Units', 'Total Enrolled', 'No.', 'Student ID', 'Full Name', 'Course', 'Section', 'Attendance Signature'],
                'student_list' => ['Subject Code', 'Subject Title', 'Units', 'Total Enrolled', 'No.', 'Student ID', 'Full Name', 'Course', 'Year Level'],
                default => ['Subject Code', 'Subject Title', 'Units', 'Total Enrolled', 'No.', 'Student ID', 'Full Name', 'Course', 'Year Level', 'Section', 'Schedule'],
            };
            $rows = [];

            foreach ($reportData['subject_groups'] ?? [] as $group) {
                foreach ($group['students'] ?? [] as $student) {
                    $common = [$group['subject_code'] ?? null, $group['subject_title'] ?? null, $group['subject_units'] ?? null, $group['total_enrolled'] ?? null, $student['no'] ?? null, $student['student_id'] ?? null, $student['full_name'] ?? null, $student['course'] ?? null];
                    $rows[] = match ($variant) {
                        'attendance_roster' => [...$common, $student['section'] ?? null, null],
                        'student_list' => [...$common, isset($student['year_level']) ? 'Year '.$student['year_level'] : null],
                        default => [...$common, isset($student['year_level']) ? 'Year '.$student['year_level'] : null, $student['section'] ?? null, $student['class_schedule'] ?? null],
                    };
                }
            }

            return [$headings, $rows];
        }

        $totalEnrolled = (int) ($reportData['total_enrolled'] ?? 0);
        $headings = ['Section', 'Label', 'Count', 'Percentage', 'Additional'];
        $rows = [
            ['Summary', 'Total Enrolled', $totalEnrolled, '100%', null],
        ];

        if ($variant !== 'status_summary') {
            foreach ($reportData['by_department'] ?? [] as $item) {
                $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
                $rows[] = ['By Department', $item['department'] ?? 'Unknown', $item['count'] ?? 0, $percentage, null];
            }
        }

        if (in_array($variant, ['department_breakdown', 'leadership_summary'], true)) {
            foreach ($reportData['by_course'] ?? [] as $item) {
                $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
                $label = mb_trim(($item['course_code'] ?? 'Unknown').' - '.($item['course_title'] ?? 'Unknown'));
                $rows[] = ['By Course', $label, $item['count'] ?? 0, $percentage, $item['department'] ?? null];
            }
        }

        if ($variant === 'department_breakdown') {
            foreach ($reportData['by_year_level'] ?? [] as $item) {
                $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
                $rows[] = ['By Year Level', 'Year '.($item['year_level'] ?? '—'), $item['count'] ?? 0, $percentage, null];
            }
        }

        if (in_array($variant, ['department_breakdown', 'status_summary'], true)) {
            foreach ($reportData['by_status'] ?? [] as $item) {
                $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
                $rows[] = ['By Status', $item['status'] ?? 'Unknown', $item['count'] ?? 0, $percentage, null];
            }
        }

        return [$headings, $rows];
    }

    /**
     * Get recent deletions for an enrollment to show on the enrollment details page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRecentDeletionsForEnrollment(StudentEnrollment $enrollment): array
    {
        $activities = Activity::query()
            ->with('causer')
            ->where(function ($query) use ($enrollment): void {
                $query->where('log_name', 'subject_enrollment')
                    ->where('event', 'deleted')
                    ->where('attribute_changes', 'like', '%"enrollment_id":'.$enrollment->id.'%');
            })
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->filter(function (Activity $activity) use ($enrollment): bool {
                $oldData = ($activity->attribute_changes?->toArray() ?? [])['old'] ?? [];

                return (int) ($oldData['enrollment_id'] ?? 0) === (int) $enrollment->id;
            })
            ->take(20)
            ->values();

        $subjectIds = $activities
            ->map(fn (Activity $a): mixed => ($a->attribute_changes?->toArray() ?? [])['old']['subject_id'] ?? null)
            ->filter()
            ->unique()
            ->values();
        $subjects = $subjectIds->isNotEmpty() ? Subject::query()->findMany($subjectIds)->keyBy('id') : collect();

        return $activities
            ->map(function (Activity $activity) use ($subjects): array {
                $properties = $activity->attribute_changes?->toArray() ?? [];
                $oldData = $properties['old'] ?? [];
                $subject = isset($oldData['subject_id']) ? $subjects->get((int) $oldData['subject_id']) : null;

                return [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'subject_code' => $subject?->code ?? $oldData['subject_code'] ?? 'Unknown',
                    'subject_title' => $subject?->title ?? 'Unknown',
                    'subject_id' => $oldData['subject_id'] ?? null,
                    'class_id' => $oldData['class_id'] ?? null,
                    'grade' => $oldData['grade'] ?? null,
                    'created_at' => $activity->created_at->toDateTimeString(),
                    'created_at_human' => $activity->created_at->diffForHumans(),
                    'causer' => $activity->causer?->name ?? 'System',
                ];
            })
            ->all();
    }

    /**
     * Get assessment data for preview/print
     */
    private function subjectsWithClassesForEnrollment(StudentEnrollment $enrollment): Collection
    {
        $classesWithSubjects = Classes::query()
            ->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)
            ->get();

        $subjectsWithClasses = collect();

        foreach ($classesWithSubjects as $class) {
            if (! empty($class->course_codes) && is_array($class->course_codes)) {
                $courseCodesAsStrings = array_map(strval(...), $class->course_codes);
                if (! in_array((string) $enrollment->course_id, $courseCodesAsStrings, true)) {
                    continue;
                }
            } else {
                continue;
            }

            if (! empty($class->subject_ids) && is_array($class->subject_ids)) {
                $subjects = Subject::query()->whereIn('id', $class->subject_ids)->get();
                foreach ($subjects as $subject) {
                    $subjectsWithClasses->push($subject->code);
                }
            }

            if (! empty($class->subject_code)) {
                $codes = array_map(trim(...), explode(',', (string) $class->subject_code));
                foreach ($codes as $code) {
                    if (! empty($code)) {
                        $subjectsWithClasses->push($code);
                    }
                }
            }
        }

        return $subjectsWithClasses->unique();
    }

    /**
     * @return array<int, array{id: int, name: string, percentage: int}>
     */
    private function discountOptions(): array
    {
        return EnrollmentDiscount::query()
            ->orderBy('percentage')
            ->orderBy('name')
            ->get(['id', 'name', 'percentage'])
            ->map(fn (EnrollmentDiscount $discount): array => [
                'id' => $discount->id,
                'name' => $discount->name,
                'percentage' => $discount->percentage,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{discount_id: int|null, discount: int}
     */
    private function resolveDiscountSelection(array $validated, ?StudentEnrollment $enrollment = null): array
    {
        $discountId = isset($validated['discount_id']) ? (int) $validated['discount_id'] : null;
        $submittedPercentage = (int) ($validated['discount'] ?? 0);

        if ($discountId === null) {
            $tuition = $enrollment?->studentTuition;
            $isUnchangedLegacyDiscount = $tuition !== null
                && $tuition->discount_id === null
                && (int) $tuition->discount === $submittedPercentage;

            if ($submittedPercentage !== 0 && ! $isUnchangedLegacyDiscount) {
                throw ValidationException::withMessages([
                    'discount_id' => 'Please select a saved discount preset.',
                ]);
            }

            return [
                'discount_id' => null,
                'discount' => $submittedPercentage,
            ];
        }

        $discount = EnrollmentDiscount::query()->findOrFail($discountId);

        if ($submittedPercentage !== $discount->percentage) {
            throw ValidationException::withMessages([
                'discount' => 'The selected discount percentage is no longer valid. Please select the discount again.',
            ]);
        }

        return [
            'discount_id' => $discount->id,
            'discount' => $discount->percentage,
        ];
    }

    private function updateEnrollmentTuition(
        StudentEnrollment $enrollment,
        array $subjects,
        int $discount,
        ?int $discountId,
        float $miscellaneousFee,
        float $downpayment,
        array $additionalFees
    ): void {
        $configuration = $this->feePolicy->forEnrollment($enrollment);
        $subjectModels = Subject::query()->findMany(collect($subjects)->pluck('subject_id'))->keyBy('id');
        $totalLecture = 0.0;
        $totalLaboratory = 0.0;
        $totalModularFee = 0.0;
        foreach ($subjects as $subjectData) {
            $subject = $subjectModels->get($subjectData['subject_id']);
            if (! $subject || ($subjectData['exclude_from_tuition'] ?? false)) {
                continue;
            }
            $fees = $this->feeCalculator->subject($subject->toArray(), $configuration, (bool) ($subjectData['is_modular'] ?? false), $subjectData);
            $totalLecture += $fees['lecture_fee'];
            $totalLaboratory += $fees['laboratory_fee'];
            $totalModularFee += $fees['modular_fee'];
        }

        $discounted = (new EnrollmentDiscountCalculation)->calculate($totalLecture, $totalLaboratory, $discount, (string) ($configuration['discount_scope'] ?? 'lecture_only'));
        $discountedLecture = $discounted['lecture'];
        $totalLaboratory = $discounted['laboratory'];
        $totalTuition = $discountedLecture + $totalLaboratory + $totalModularFee;

        $additionalFeesTotal = collect($additionalFees)
            ->sum(fn (array $fee): float => (float) ($fee['amount'] ?? 0));

        $overallTotal = $totalTuition + $miscellaneousFee + $additionalFeesTotal;

        $existingTuition = StudentTuition::query()
            ->where('enrollment_id', $enrollment->id)
            ->lockForUpdate()
            ->first();

        $tuitionData = $this->tuitionAdjustmentRecalculation->preserveFinanceAdjustment($existingTuition, [
            'student_id' => $enrollment->student_id,
            'total_tuition' => $totalTuition,
            'total_balance' => $overallTotal,
            'total_lectures' => $discountedLecture,
            'total_laboratory' => $totalLaboratory,
            'total_miscelaneous_fees' => $miscellaneousFee,
            'discount' => $discount,
            'discount_id' => $discountId,
            'downpayment' => $downpayment,
            'overall_tuition' => $overallTotal,
            'semester' => $enrollment->semester,
            'school_year' => $enrollment->school_year,
            'academic_year' => $enrollment->academic_year,
        ]);

        if ($existingTuition instanceof StudentTuition) {
            $existingTuition->forceFill($tuitionData)->save();
            $tuition = $existingTuition;
        } else {
            $tuition = StudentTuition::query()->create([
                'enrollment_id' => $enrollment->id,
                ...$tuitionData,
            ]);
        }

        $this->enrollmentBillingService->syncTuitionBalance($tuition, $downpayment);
    }

    /**
     * Get list of students enrolled in a specific course/program.
     *
     * @param  Builder<StudentEnrollment>  $baseQuery
     * @param  array<string, mixed>  $filters
     */
    private function getEnrolledByCourseReport($baseQuery, array $filters): array
    {
        $query = clone $baseQuery;

        if (! empty($filters['course_filter']) && $filters['course_filter'] !== 'all') {
            $query->whereExists(function ($subquery) use ($filters): void {
                $subquery->select(DB::raw(1))
                    ->from('courses')
                    ->whereColumn('student_enrollment.course_id', 'courses.id')
                    ->where('code', (string) $filters['course_filter']);
            });
        }

        if (! empty($filters['department_filter']) && $filters['department_filter'] !== 'all') {
            $query->whereExists(function ($subquery) use ($filters): void {
                $subquery->select(DB::raw(1))
                    ->from('courses')
                    ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                    ->whereColumn('student_enrollment.course_id', 'courses.id')
                    ->whereRaw('TRIM(departments.code) = ?', [mb_trim((string) $filters['department_filter'])]);
            });
        }

        if (! empty($filters['year_level_filter']) && $filters['year_level_filter'] !== 'all') {
            $yearLevelFilter = $filters['year_level_filter'];
            $yearLevel = is_scalar($yearLevelFilter) ? (string) $yearLevelFilter : '0';
            $query->where('academic_year', (int) $yearLevel);
        }

        $enrollments = $query
            ->with(['student', 'course', 'subjectsEnrolled'])
            ->orderBy('created_at', 'desc')
            ->get();

        $students = $enrollments->map(fn (StudentEnrollment $e, int $index): array => [
            'no' => $index + 1,
            'student_id' => $e->student?->student_id,
            'full_name' => $e->student?->full_name,
            'course' => $e->course?->code,
            'department' => $e->course?->department?->code,
            'year_level' => $e->academic_year,
            'subjects_count' => $e->subjectsEnrolled->count(),
            'status' => $e->status,
        ]);

        $courseLabel = 'All Courses';
        if (! empty($filters['course_filter']) && $filters['course_filter'] !== 'all') {
            $course = Course::where('code', $filters['course_filter'])->first();
            $courseLabel = $course ? $course->code.' - '.$course->title : $filters['course_filter'];
        }

        return [
            'type' => 'enrolled_by_course',
            'title' => 'List of Enrolled Students',
            'subtitle' => $courseLabel,
            'filters_applied' => array_filter([
                'Course' => $courseLabel,
                'Department' => ($filters['department_filter'] ?? 'all') !== 'all' ? $filters['department_filter'] : null,
                'Year Level' => ($filters['year_level_filter'] ?? 'all') !== 'all' ? 'Year '.$filters['year_level_filter'] : null,
            ]),
            'total_count' => $students->count(),
            'students' => $students->values()->all(),
            'columns' => ['No.', 'Student ID', 'Full Name', 'Course', 'Department', 'Year Level', 'Subjects', 'Status'],
        ];
    }

    /**
     * Get list of students enrolled in a specific subject.
     *
     * @param  array<string, mixed>  $filters
     */
    private function getEnrolledBySubjectReport(array $filters, string $schoolYear, int $semester): array
    {
        $query = SubjectEnrollment::query()
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->whereHas('subject')
            ->with(['student', 'subject', 'class', 'studentEnrollment.course']);

        if (! empty($filters['subject_filter']) && $filters['subject_filter'] !== 'all') {
            $query->whereHas('subject', function ($q) use ($filters): void {
                $q->where('code', $filters['subject_filter']);
            });
        }

        $subjectEnrollments = $query->get();

        // Group by subject
        $grouped = $subjectEnrollments->groupBy(fn (SubjectEnrollment $se): string => $se->subject?->code ?? 'Unknown');

        $subjectGroups = $grouped->map(function (Collection $items, string $subjectCode): array {
            $subject = $items->first()?->subject;

            return [
                'subject_code' => $subjectCode,
                'subject_title' => $subject?->title ?? 'Unknown',
                'subject_units' => $subject?->units ?? 0,
                'total_enrolled' => $items->count(),
                'students' => $items->values()->map(fn (SubjectEnrollment $se, int $index): array => [
                    'no' => $index + 1,
                    'student_id' => $se->student?->student_id,
                    'full_name' => $se->student?->full_name,
                    'course' => $se->studentEnrollment?->course?->code,
                    'year_level' => $se->studentEnrollment?->academic_year,
                    'section' => $se->section ?? ($se->class?->section ?? '—'),
                    'class_schedule' => $se->class ? $se->class->formatted_weekly_schedule : '—',
                ])->all(),
            ];
        })->values()->all();

        $subjectLabel = 'All Subjects';
        if (! empty($filters['subject_filter']) && $filters['subject_filter'] !== 'all') {
            $subject = Subject::query()->where('code', $filters['subject_filter'])->first();
            $subjectLabel = $subject ? $subject->code.' - '.$subject->title : 'Subject '.$filters['subject_filter'];
        }

        return [
            'type' => 'enrolled_by_subject',
            'title' => 'Students Enrolled by Subject',
            'subtitle' => $subjectLabel,
            'filters_applied' => array_filter([
                'Subject' => $subjectLabel,
            ]),
            'total_count' => $subjectEnrollments->count(),
            'subject_groups' => $subjectGroups,
            'columns' => ['No.', 'Student ID', 'Full Name', 'Course', 'Year Level', 'Section', 'Schedule'],
        ];
    }

    /**
     * Get enrollment summary grouped by department, course, and year level.
     *
     * @param  Builder<StudentEnrollment>  $baseQuery
     * @param  array<string, mixed>  $filters
     */
    private function getEnrollmentSummaryReport($baseQuery, array $filters): array
    {
        $query = clone $baseQuery;

        if (! empty($filters['department_filter']) && $filters['department_filter'] !== 'all') {
            $query->whereExists(function ($subquery) use ($filters): void {
                $subquery->select(DB::raw(1))
                    ->from('courses')
                    ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                    ->whereColumn('student_enrollment.course_id', 'courses.id')
                    ->whereRaw('TRIM(departments.code) = ?', [mb_trim((string) $filters['department_filter'])]);
            });
        }

        $enrollments = $query->with(['course'])->get();
        $totalEnrolled = $enrollments->count();

        // By department
        $byDepartment = $enrollments->groupBy(fn (StudentEnrollment $e): string => $e->course?->department?->code ?? 'Unknown')
            ->map(fn (Collection $items, string $dept): array => [
                'department' => $dept,
                'count' => $items->count(),
            ])->values()->all();

        // By course
        $byCourse = $enrollments->groupBy(fn (StudentEnrollment $e): string => $e->course?->code ?? 'Unknown')
            ->map(fn (Collection $items, string $code): array => [
                'course_code' => $code,
                'course_title' => $items->first()?->course?->title ?? 'Unknown',
                'department' => $items->first()?->course?->department?->code ?? 'Unknown',
                'count' => $items->count(),
            ])->sortByDesc('count')->values()->all();

        // By year level
        $byYearLevel = $enrollments->groupBy(fn (StudentEnrollment $e): string => (string) ($e->academic_year ?? 0))
            ->map(fn (Collection $items, string $year): array => [
                'year_level' => (int) $year,
                'count' => $items->count(),
            ])->sortBy('year_level')->values()->all();

        // By status
        $byStatus = $enrollments->groupBy(fn (StudentEnrollment $e): string => $e->status ?? 'Unknown')
            ->map(fn (Collection $items, string $status): array => [
                'status' => $status,
                'count' => $items->count(),
            ])->values()->all();

        return [
            'type' => 'enrollment_summary',
            'title' => 'Enrollment Summary Report',
            'subtitle' => 'Comprehensive enrollment statistics',
            'filters_applied' => array_filter([
                'Department' => ($filters['department_filter'] ?? 'all') !== 'all' ? $filters['department_filter'] : null,
            ]),
            'total_enrolled' => $totalEnrolled,
            'by_department' => $byDepartment,
            'by_course' => $byCourse,
            'by_year_level' => $byYearLevel,
            'by_status' => $byStatus,
        ];
    }

    /**
     * @param  array<int, array{class_id?: int|string|null}>  $subjects
     *
     * @throws Exception
     */
    private function ensureClassCapacityForSubjects(
        array $subjects,
        int $studentId,
        bool $forceOverload,
        ?StudentEnrollment $enrollment = null,
        ?User $actor = null
    ): void {
        $classIds = collect($subjects)
            ->pluck('class_id')
            ->filter()
            ->map(fn (mixed $classId): int => (int) $classId)
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            return;
        }

        $classes = Classes::query()
            ->whereIn('id', $classIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($classIds as $classId) {
            $class = $classes->get($classId);
            if (! $class instanceof Classes || ! $class->maximum_slots) {
                continue;
            }

            $enrolledCount = ClassEnrollment::query()
                ->where('class_id', $classId)
                ->where('student_id', '!=', $studentId)
                ->where('status', true)
                ->count();

            if ($enrolledCount >= $class->maximum_slots) {
                if (! $forceOverload) {
                    throw new Exception(sprintf(
                        'Section %s is full. Confirm overload enrollment to force this student into the class.',
                        (string) $class->section
                    ));
                }

                if ($enrollment instanceof StudentEnrollment) {
                    activity('enrollment')
                        ->performedOn($enrollment)
                        ->causedBy($actor)
                        ->withProperties([
                            'class_id' => $class->id,
                            'section' => $class->section,
                            'enrolled_count' => $enrolledCount,
                            'maximum_slots' => $class->maximum_slots,
                        ])
                        ->event('class_overload_approved')
                        ->log("Approved class seat overload into Section {$class->section}.");
                }
            }
        }
    }

    private function canViewEnrollment(User $user, StudentEnrollment $enrollment): bool
    {
        if ($user->can('view', $enrollment) || $user->can('View:StudentEnrollment')) {
            return true;
        }

        return $this->canManageEnrollment($user, 'update', $enrollment);
    }

    private function isTerminalLocked(StudentEnrollment $enrollment): bool
    {
        return in_array($enrollment->terminal_outcome, [EnrollmentCancellationService::OUTCOME, 'rejected'], true);
    }

    private function canManageEnrollment(User $user, string $ability, ?StudentEnrollment $enrollment = null): bool
    {
        if ($ability === 'update' && $enrollment instanceof StudentEnrollment) {
            if ($user->can('update', $enrollment) || $user->can('Update:StudentEnrollment')) {
                return true;
            }
        } elseif ($user->can('create', StudentEnrollment::class) || $user->can('Create:StudentEnrollment') || $user->can('Update:StudentEnrollment')) {
            return true;
        }

        return in_array($user->role, [
            UserRole::Developer,
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::Registrar,
            UserRole::AssistantRegistrar,
            UserRole::Dean,
            UserRole::AssociateDean,
            UserRole::DepartmentHead,
            UserRole::ProgramChair,
            UserRole::Cashier,
            UserRole::AccountingOfficer,
            UserRole::BursarOfficer,
        ], true);
    }

    private function canManageTuitionFees(User $user): bool
    {
        if ($user->can('manage_tuition_fees')) {
            return true;
        }

        return in_array($user->role, [
            UserRole::Developer,
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::Registrar,
            UserRole::AssistantRegistrar,
            UserRole::Cashier,
            UserRole::AccountingOfficer,
            UserRole::BursarOfficer,
        ], true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $subjects
     * @param  array<string, mixed>  $feeConfiguration
     * @return array<int, array<string, mixed>>
     */
    private function resolveSubjectFeesForPersistence(array $subjects, array $feeConfiguration, bool $allowClientOverrides): array
    {
        $subjectModels = Subject::query()
            ->with('course')
            ->findMany(collect($subjects)->pluck('subject_id'))
            ->keyBy('id');

        return collect($subjects)->map(function (array $subjectData) use ($subjectModels, $feeConfiguration, $allowClientOverrides): array {
            $subject = $subjectModels->get((int) $subjectData['subject_id']);
            if (! $subject instanceof Subject) {
                return [
                    ...$subjectData,
                    'lecture_fee' => (float) ($subjectData['lecture_fee'] ?? 0),
                    'laboratory_fee' => (float) ($subjectData['laboratory_fee'] ?? 0),
                ];
            }

            $courseConfig = [
                ...$feeConfiguration,
                'course_lecture_rate_per_unit' => $feeConfiguration['course_lecture_rate_per_unit'] ?? (float) ($subject->course?->lec_per_unit ?? 0),
                'course_laboratory_rate_per_unit' => $feeConfiguration['course_laboratory_rate_per_unit'] ?? (float) ($subject->course?->lab_per_unit ?? 0),
            ];
            $assessedOverrides = $allowClientOverrides ? array_filter([
                'lecture_fee' => array_key_exists('lecture_fee', $subjectData) && $subjectData['lecture_fee'] !== null ? $subjectData['lecture_fee'] : null,
                'laboratory_fee' => array_key_exists('laboratory_fee', $subjectData) && $subjectData['laboratory_fee'] !== null ? $subjectData['laboratory_fee'] : null,
            ], static fn (mixed $v): bool => $v !== null) : [];

            $computed = $this->feeCalculator->subject(
                $subject->toArray(),
                $courseConfig,
                (bool) ($subjectData['is_modular'] ?? false),
                $assessedOverrides,
            );

            return [
                ...$subjectData,
                'lecture_fee' => $computed['lecture_fee'],
                'laboratory_fee' => $computed['laboratory_fee'],
            ];
        })->values()->all();
    }

    /**
     * Reconcile additional fees without destroying receipted / separate-transaction fees.
     *
     * @param  array<int, array{id?: int|string|null, fee_name: string, amount: int|float|string}>  $incomingFees
     * @return array<int, array{id: int, fee_name: string, amount: float}>
     */
    private function reconcileAdditionalFees(StudentEnrollment $enrollment, array $incomingFees): array
    {
        $existingFees = $enrollment->additionalFees()->get()->keyBy('id');
        $keptIds = [];

        foreach ($incomingFees as $feeData) {
            $feeId = isset($feeData['id']) && $feeData['id'] !== '' ? (int) $feeData['id'] : null;
            $existing = $feeId ? $existingFees->get($feeId) : null;

            if ($existing instanceof AdditionalFee) {
                $isProtected = (bool) $existing->is_separate_transaction || filled($existing->transaction_number);
                if (! $isProtected) {
                    $existing->update([
                        'fee_name' => (string) $feeData['fee_name'],
                        'amount' => (float) $feeData['amount'],
                    ]);
                }
                $keptIds[] = $existing->id;
            } else {
                $created = $enrollment->additionalFees()->create([
                    'fee_name' => (string) $feeData['fee_name'],
                    'amount' => (float) $feeData['amount'],
                ]);
                $keptIds[] = $created->id;
            }
        }

        // Delete only un-receipted editable fees that were removed; always protect separate-transaction / receipted fees.
        foreach ($existingFees as $existing) {
            $isProtected = (bool) $existing->is_separate_transaction || filled($existing->transaction_number);
            if (! $isProtected && ! in_array($existing->id, $keptIds, true)) {
                $existing->delete();
            }
        }

        return $enrollment->additionalFees()
            ->get()
            ->map(fn (AdditionalFee $fee): array => [
                'id' => $fee->id,
                'fee_name' => $fee->fee_name,
                'amount' => (float) $fee->amount,
            ])
            ->values()
            ->all();
    }

    private function syncCreatedEnrollmentToSearch(StudentEnrollment $enrollment): void
    {
        if (config('scout.driver') === 'database') {
            return;
        }

        try {
            $enrollment->searchable();

            $enrollment->loadMissing('subjectsEnrolled');
            $enrollment->subjectsEnrolled->each->searchable();
        } catch (Throwable $e) {
            Log::warning('Enrollment was created but search indexing failed.', [
                'enrollment_id' => $enrollment->id,
                'scout_driver' => config('scout.driver'),
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function withoutEnrollmentSearchSyncing(Closure $callback): mixed
    {
        return StudentEnrollment::withoutSyncingToSearch(
            fn (): mixed => SubjectEnrollment::withoutSyncingToSearch(
                fn (): mixed => ClassEnrollment::withoutSyncingToSearch($callback)
            )
        );
    }
}
