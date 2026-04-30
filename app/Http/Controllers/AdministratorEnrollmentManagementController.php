<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Exports\EnrollmentReportExport;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Jobs\GenerateBulkAssessmentsJob;
use App\Jobs\GenerateEnrollmentReportPreviewPdfJob;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Resource;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use App\Services\EnrollmentService;
use App\Services\GeneralSettingsService;
use App\Settings\SiteSettings;
use Closure;
use Exception;
use FPDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class AdministratorEnrollmentManagementController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService,
        private readonly EnrollmentPipelineService $enrollmentPipelineService
    ) {}

    public function index(GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $workflowSetupRequired = ! $this->enrollmentPipelineService->hasWorkflowSetup();
        if ($workflowSetupRequired) {
            $scheme = request()->getScheme();
            $adminHost = (string) config('app.admin_host', 'admin.koakademy.test');
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
                ],
                'flash' => session('flash'),
                'filters' => [
                    'search' => null,
                    'per_page' => 10,
                    'status_filter' => 'all',
                    'department_filter' => 'all',
                    'year_level_filter' => 'all',
                    'currentSemester' => $settingsService->getCurrentSemester(),
                    'currentSchoolYear' => $settingsService->getCurrentSchoolYearStart(),
                    'systemSemester' => $settingsService->getSystemDefaultSemester(),
                    'systemSchoolYear' => $settingsService->getSystemDefaultSchoolYearStart(),
                    'availableSemesters' => $settingsService->getAvailableSemesters(),
                    'availableSchoolYears' => $settingsService->getAvailableSchoolYears(),
                ],
                'enrollment_pipeline' => [
                    ...$this->enrollmentPipelineService->getConfiguration(),
                    'steps' => $this->enrollmentPipelineService->getSteps(),
                    'status_options' => $this->enrollmentPipelineService->getStatusOptions(),
                    'status_classes' => $this->enrollmentPipelineService->getStatusColorClasses(),
                ],
                'enrollment_stats' => $this->enrollmentPipelineService->getStatsConfiguration(),
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

        $enrolledThisSemester = fn () => StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('semester', $currentSemester)
            ->where('status', '!=', $pendingStatus)
            ->count();

        $enrolledThisSchoolYear = fn () => StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('status', '!=', $pendingStatus)
            ->count();

        $enrolledPreviousSemester = fn () => StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $previousSchoolYearString)
            ->where('semester', $previousSemester)
            ->where('status', '!=', $pendingStatus)
            ->count();

        $enrolledByDepartment = fn () => StudentEnrollment::query()
            ->withTrashed()
            ->where('student_enrollment.school_year', $currentSchoolYearString)
            ->where('student_enrollment.semester', $currentSemester)
            ->where('student_enrollment.status', '!=', $pendingStatus)
            ->join('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
            ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
            ->selectRaw('TRIM(departments.code) as department, count(*) as count')
            ->groupByRaw('TRIM(departments.code)')
            ->get();

        $enrolledByYearLevel = fn () => StudentEnrollment::query()
            ->withTrashed()
            ->where('school_year', $currentSchoolYearString)
            ->where('semester', $currentSemester)
            ->where('status', '!=', $pendingStatus)
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

        $applicantsCount = Student::query()
            ->withTrashed()
            ->where('status', StudentStatus::Applicant)
            ->count();

        $search = request('search');
        $sort = request('sort', 'created_at');
        $direction = request('direction', 'desc');
        $perPage = request('per_page', 10);
        $statusFilter = request('status_filter', 'all');
        $departmentFilter = request('department_filter', 'all');
        $yearLevelFilter = request('year_level_filter', 'all');

        // Normalize per_page
        if ($perPage === 'all') {
            $perPage = 100000;
        } else {
            $perPage = (int) $perPage;
            if ($perPage <= 0) {
                $perPage = 10;
            }
        }

        $enrollments = fn () => StudentEnrollment::query()
            ->withTrashed()
            ->where('student_enrollment.school_year', $currentSchoolYearString)
            ->where('student_enrollment.semester', $currentSemester)
            ->when($search, function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->whereExists(function ($subquery) use ($search): void {
                        $subquery->select(DB::raw(1))
                            ->from('students')
                            ->whereRaw('CAST(NULLIF(student_enrollment.student_id, \'\') AS BIGINT) = students.id')
                            ->where(function ($studentQ) use ($search): void {
                                $studentQ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('middle_name', 'like', "%{$search}%")
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                                    ->orWhere('student_id', 'like', "%{$search}%");
                            });
                    })->orWhere(function ($q) use ($search): void {
                        $q->whereExists(function ($subquery) use ($search): void {
                            $subquery->select(DB::raw(1))
                                ->from('courses')
                                ->whereRaw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT) = courses.id')
                                ->where('code', 'like', "%{$search}%");
                        });
                    });
                });
            })
            ->when($statusFilter !== 'all', function ($query) use ($statusFilter): void {
                if ($statusFilter === 'trashed') {
                    $query->onlyTrashed();
                } elseif ($statusFilter === 'active') {
                    $query->whereNull('student_enrollment.deleted_at');
                } else {
                    $query->where('student_enrollment.status', $statusFilter);
                }
            })
            ->when($departmentFilter !== 'all', function ($query) use ($departmentFilter): void {
                $query->whereExists(function ($subquery) use ($departmentFilter): void {
                    $subquery->select(DB::raw(1))
                        ->from('courses')
                        ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                        ->whereRaw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT) = courses.id')
                        ->whereRaw('TRIM(departments.code) = ?', [mb_trim($departmentFilter)]);
                });
            })
            ->when($yearLevelFilter !== 'all', function ($query) use ($yearLevelFilter): void {
                $query->where('student_enrollment.academic_year', (int) $yearLevelFilter);
            })
            ->with(['student.Course', 'course', 'studentTuition'])
            ->when($sort === 'student_name', function ($query) use ($direction): void {
                $query->leftJoin('students', DB::raw('CAST(NULLIF(student_enrollment.student_id, \'\') AS BIGINT)'), '=', 'students.id')
                    ->orderBy('students.last_name', $direction)
                    ->orderBy('students.first_name', $direction)
                    ->select('student_enrollment.*');
            }, function ($query) use ($sort, $direction): void {
                // Handle default sorting or specific columns
                if (in_array($sort, ['created_at', 'status', 'school_year', 'semester'])) {
                    $query->orderBy('student_enrollment.'.$sort, $direction);
                } else {
                    $query->orderBy('student_enrollment.created_at', 'desc');
                }
            })
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StudentEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'student_name' => $enrollment->student?->full_name,
                'course' => $enrollment->course?->code,
                'department' => $enrollment->course?->department?->code,
                'status' => $enrollment->status ?? 'N/A',
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'academic_year' => $enrollment->academic_year,
                'subjects_count' => $enrollment->subjectsEnrolled()->count(),
                'tuition' => $enrollment->studentTuition ? [
                    'overall' => $enrollment->studentTuition->overall_tuition,
                    'balance' => $enrollment->studentTuition->total_balance,
                ] : null,
                'created_at' => $enrollment->created_at?->toDateTimeString(),
                'deleted_at' => $enrollment->deleted_at?->toDateTimeString(),
                'is_trashed' => $enrollment->trashed(),
            ]);

        $scheme = request()->getScheme();
        $adminHost = (string) config('app.admin_host', 'admin.koakademy.test');
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
            'analytics' => fn (): array => [
                'current_semester_count' => $enrolledThisSemester instanceof Closure ? $enrolledThisSemester() : $enrolledThisSemester,
                'current_school_year_count' => $enrolledThisSchoolYear instanceof Closure ? $enrolledThisSchoolYear() : $enrolledThisSchoolYear,
                'previous_semester_count' => $enrolledPreviousSemester instanceof Closure ? $enrolledPreviousSemester() : $enrolledPreviousSemester,
                'by_department' => $enrolledByDepartment instanceof Closure ? $enrolledByDepartment() : $enrolledByDepartment,
                'by_year_level' => $enrolledByYearLevel instanceof Closure ? $enrolledByYearLevel() : $enrolledByYearLevel,
                'trashed_count' => $trashedCount instanceof Closure ? $trashedCount() : $trashedCount,
                'active_count' => $activeCount instanceof Closure ? $activeCount() : $activeCount,
                'by_status' => $enrollmentByStatus instanceof Closure ? $enrollmentByStatus() : $enrollmentByStatus,
            ],
            'flash' => session('flash'),
            'filters' => [
                'search' => $search,
                'per_page' => request('per_page', 10),
                'status_filter' => $statusFilter,
                'department_filter' => $departmentFilter,
                'year_level_filter' => $yearLevelFilter,
                'currentSemester' => $currentSemester,
                'currentSchoolYear' => $currentSchoolYearStart,
                'systemSemester' => $settingsService->getSystemDefaultSemester(),
                'systemSchoolYear' => $settingsService->getSystemDefaultSchoolYearStart(),
                'availableSemesters' => $settingsService->getAvailableSemesters(),
                'availableSchoolYears' => $settingsService->getAvailableSchoolYears(),
            ],
            'enrollment_pipeline' => [
                ...$this->enrollmentPipelineService->getConfiguration(),
                'steps' => $this->enrollmentPipelineService->getSteps(),
                'status_options' => $this->enrollmentPipelineService->getStatusOptions(),
                'status_classes' => $this->enrollmentPipelineService->getStatusColorClasses(),
            ],
            'enrollment_stats' => $this->enrollmentPipelineService->getStatsConfiguration(),
            'workflow_setup_required' => false,
        ]);
    }

    public function applicants(): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $search = request('search');
        $sort = request('sort', 'created_at');
        $direction = request('direction', 'desc');
        $perPage = request('per_page', 10);

        if ($perPage === 'all') {
            $perPage = 100000;
        } else {
            $perPage = (int) $perPage;
            if ($perPage <= 0) {
                $perPage = 10;
            }
        }

        $applicants = Student::query()
            ->withTrashed()
            ->where('status', StudentStatus::Applicant)
            ->with('Course')
            ->when($search, function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhereHas('Course', function ($courseQuery) use ($search): void {
                            $courseQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('department', 'like', "%{$search}%");
                        });
                });
            })
            ->when($sort === 'name', function ($query) use ($direction): void {
                $query->orderBy('students.last_name', $direction)
                    ->orderBy('students.first_name', $direction);
            })
            ->when($sort === 'student_id', function ($query) use ($direction): void {
                $query->orderBy('students.student_id', $direction);
            })
            ->when(in_array($sort, ['course', 'department']), function ($query) use ($sort, $direction): void {
                $query->leftJoin('courses', 'students.course_id', '=', 'courses.id')
                    ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                    ->select('students.*')
                    ->orderBy($sort === 'course' ? 'courses.code' : 'departments.code', $direction);
            })
            ->when($sort === 'created_at', function ($query) use ($direction): void {
                $query->orderBy('students.created_at', $direction);
            }, function ($query): void {
                $query->orderBy('students.created_at', 'desc');
            })
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Student $student): array => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'student_type' => is_object($student->student_type) ? $student->student_type->value : $student->student_type,
                'course' => $student->Course?->code,
                'department' => $student->Course?->department?->code,
                'academic_year' => $student->academic_year,
                'scholarship_type' => $student->scholarship_type,
                'created_at' => $student->created_at?->toDateTimeString(),
                'deleted_at' => $student->deleted_at?->toDateTimeString(),
                'is_trashed' => $student->trashed(),
            ]);

        return Inertia::render('administrators/enrollments/applicants', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'applicants' => $applicants,
            'filters' => [
                'search' => $search,
                'per_page' => request('per_page', 10),
            ],
            'flash' => session('flash'),
        ]);
    }

    public function show(StudentEnrollment $enrollment): Response
    {
        $enrollment->load([
            'student.Course',
            'student.studentTuition',
            'subjectsEnrolled.subject',
            'studentTuition',
            'additionalFees',
            'resources',
        ]);

        // Fetch enrollment transactions directly (eager loading doesn't work well with dynamic constraints)
        $enrollmentTransactions = $enrollment->enrollmentTransactions()->with('transaction')->get();

        // Fetch Class Enrollments (Active)
        $activeClassEnrollments = $enrollment->student->classEnrollments()
            ->with(['class.subject', 'class.faculty', 'class.schedules.room'])
            ->where('status', true)
            ->whereHas('class', function ($query) use ($enrollment): void {
                $query->where('school_year', $enrollment->school_year)
                    ->where('semester', $enrollment->semester);
            })
            ->get()
            ->map(fn ($ce): array => [
                'id' => $ce->id,
                'class_id' => $ce->class_id,
                'subject_code' => $ce->class->subject_code,
                'subject_title' => $ce->class->subject_title,
                'section' => $ce->class->section,
                'faculty' => $ce->class->faculty->full_name ?? 'TBA',
                'schedule' => $ce->class->schedules->map(fn ($s): string => $s->day_of_week.' '.$s->time_range)->implode(', ') ?: 'TBA',
                'room' => $ce->class->schedules->map(fn ($s) => $s->room?->name)->filter()->unique()->implode(', ') ?: 'TBA',
                'grades' => [
                    'prelim' => $ce->prelim_grade,
                    'midterm' => $ce->midterm_grade,
                    'finals' => $ce->finals_grade,
                    'average' => $ce->total_average,
                ],
                'status' => $ce->status,
            ]);

        // Identify Missing Classes
        $enrolledSubjectCodes = $activeClassEnrollments->pluck('subject_code')
            ->map(fn ($code): array => array_map(trim(...), explode(',', (string) $code)))
            ->flatten()
            ->toArray();
        $missingClasses = collect();

        foreach ($enrollment->subjectsEnrolled as $subjectEnrollment) {
            $subject = $subjectEnrollment->subject;
            if (! $subject) {
                continue;
            }

            if (! in_array($subject->code, $enrolledSubjectCodes)) {
                $availableClasses = Classes::query()
                    ->where('school_year', $enrollment->school_year)
                    ->where('semester', $enrollment->semester)
                    ->whereJsonContains('course_codes', $subject->course_id)
                    ->where(function ($query) use ($subject): void {
                        $query->whereJsonContains('subject_ids', $subject->id)
                            ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                            ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
                    })
                    ->withCount('class_enrollments') // Load count
                    ->get()
                    ->map(fn ($class): array => [
                        'class_id' => $class->id,
                        'subject_code' => $subject->code,
                        'subject_title' => $class->subject_title,
                        'section' => $class->section,
                        'faculty' => $class->faculty->full_name ?? 'TBA',
                        'available_slots' => ($class->maximum_slots ?: 0) - ($class->class_enrollments_count ?? 0),
                        'max_slots' => $class->maximum_slots ?: 0,
                        'is_full' => ($class->maximum_slots ?: 0) > 0 && ($class->class_enrollments_count ?? 0) >= $class->maximum_slots,
                    ]);

                if ($availableClasses->isEmpty()) {
                    $missingClasses->push([
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
        }

        return Inertia::render('administrators/enrollments/show', [
            'user' => Auth::user(),
            'enrollment' => [
                'id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'status' => $enrollment->status,
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'academic_year' => $enrollment->academic_year,
                'signature' => $enrollment->signature,
                'student' => [
                    'id' => $enrollment->student->id,
                    'full_name' => $enrollment->student->full_name,
                    'email' => $enrollment->student->email,
                    'student_id' => $enrollment->student->student_id,
                    'course_code' => $enrollment->student->Course?->code,
                ],
                'subjects_enrolled' => $enrollment->subjectsEnrolled->map(fn ($se): array => [
                    'id' => $se->id,
                    'subject_code' => $se->subject->code ?? 'Unknown',
                    'subject_title' => $se->subject->title ?? 'Unknown',
                    'units' => $se->subject->units ?? 0,
                    'lecture_fee' => $se->lecture,
                    'lab_fee' => $se->laboratory,
                ]),
                'class_enrollments' => $activeClassEnrollments,
                'missing_classes' => $missingClasses,
                'tuition' => $enrollment->studentTuition ? $enrollment->studentTuition->append('total_paid') : null,
                'additional_fees' => $enrollment->additionalFees,
                'transactions' => $enrollmentTransactions->map(function ($studentTransaction): array {
                    $tx = $studentTransaction->transaction;

                    return [
                        'id' => $tx->id,
                        'transaction_number' => $tx->transaction_number,
                        'invoicenumber' => $tx->invoicenumber,
                        'description' => $tx->description,
                        'status' => $tx->status,
                        'total_amount' => $tx->total_amount,
                        'amount' => $studentTransaction->amount ?? $tx->raw_total_amount, // Use amount from relationship or transaction total
                        'transaction_date' => $tx->transaction_date,
                        'created_at' => $tx->created_at->toDateTimeString(),
                    ];
                }),
                'resources' => $enrollment->resources->map(fn (Resource $res): array => [
                    'id' => $res->id,
                    'type' => $res->type,
                    'file_name' => $res->file_name,
                    'file_size' => $res->file_size,
                    'created_at' => $res->created_at->toDateTimeString(),
                    'download_url' => route('assessment.download', ['record' => $enrollment->id], false),
                ]),
            ],
            'auth' => [
                'user' => Auth::user(),
                'can_verify_head' => (
                    Auth::user()->can('verify_by_head_dept_guest::enrollment') ||
                    Auth::user()->hasRole('super_admin') ||
                    $this->enrollmentPipelineService->canUserPerformStep(
                        Auth::user(),
                        $this->enrollmentPipelineService->getStepByActionType('department_verification') ?? ['allowed_roles' => []]
                    )
                ),
                'can_verify_cashier' => (
                    Auth::user()->can('verify_by_cashier_guest::enrollment') ||
                    Auth::user()->hasRole('super_admin') ||
                    $this->enrollmentPipelineService->canUserPerformStep(
                        Auth::user(),
                        $this->enrollmentPipelineService->getStepByActionType('cashier_verification') ?? ['allowed_roles' => []]
                    )
                ),
                'is_super_admin' => Auth::user()->hasRole('super_admin'),
                'can_advance_pipeline' => $this->enrollmentPipelineService->canUserAdvanceToNextStep(Auth::user(), $enrollment->status),
            ],
            'recent_deletions' => $this->getRecentDeletionsForEnrollment($enrollment),
            'flash' => session('flash'),
            'enrollment_pipeline' => [
                ...$this->enrollmentPipelineService->getConfiguration(),
                'steps' => $this->enrollmentPipelineService->getSteps(),
                'status_options' => $this->enrollmentPipelineService->getStatusOptions(),
                'status_classes' => $this->enrollmentPipelineService->getStatusColorClasses(),
                'next_step' => $this->enrollmentPipelineService->getNextStep($enrollment->status),
            ],
            'enrollment_stats' => $this->enrollmentPipelineService->getStatsConfiguration(),
        ]);
    }

    public function advancePipelineStep(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $nextStep = $this->enrollmentPipelineService->getNextStep($enrollment->status);
        if ($nextStep === null) {
            return back()->with('flash', ['error' => 'No next pipeline step is available.']);
        }

        if (! $this->enrollmentPipelineService->canUserPerformStep($user, $nextStep)) {
            abort(403);
        }

        $actionType = $nextStep['action_type'] ?? 'standard';

        if ($actionType === 'cashier_verification') {
            return back()->with('flash', ['error' => 'This step requires payment verification flow.']);
        }

        if ($actionType === 'department_verification') {
            if ($this->enrollmentService->verifyByHeadDept($enrollment)) {
                return back()->with('flash', ['success' => 'Advanced to the next pipeline step.']);
            }

            return back()->with('flash', ['error' => 'Failed to advance pipeline step.']);
        }

        $enrollment->status = $nextStep['status'];
        $enrollment->save();

        return back()->with('flash', ['success' => 'Advanced to the next pipeline step.']);
    }

    public function verifyHeadDept(StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $departmentStep = $this->enrollmentPipelineService->getStepByActionType('department_verification');
        if ($departmentStep !== null && ! $this->enrollmentPipelineService->canUserPerformStep($user, $departmentStep)) {
            abort(403);
        }

        $nextStep = $this->enrollmentPipelineService->getNextStep($enrollment->status);
        if (($nextStep['action_type'] ?? null) !== 'department_verification') {
            return back()->with('flash', ['error' => 'Enrollment is not ready for department verification.']);
        }

        if ($this->enrollmentService->verifyByHeadDept($enrollment)) {
            return back()->with('flash', ['success' => 'Successfully verified as Head Dept.']);
        }

        return back()->with('flash', ['error' => 'Verification failed.']);
    }

    public function verifyCashier(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $cashierStep = $this->enrollmentPipelineService->getStepByActionType('cashier_verification');
        if ($cashierStep !== null && ! $this->enrollmentPipelineService->canUserPerformStep($user, $cashierStep)) {
            abort(403);
        }

        $nextStep = $this->enrollmentPipelineService->getNextStep($enrollment->status);
        if (($nextStep['action_type'] ?? null) !== 'cashier_verification') {
            return back()->with('flash', ['error' => 'Enrollment is not ready for cashier verification.']);
        }

        $request->validate([
            'invoicenumber' => 'required|string',
            'settlements' => 'required|array',
            'payment_method' => 'required|string',
        ]);

        // Merge extra dynamic fields for separate transaction fees if present in request
        $allData = $request->all();

        if ($this->enrollmentService->verifyByCashier($enrollment, $allData)) {
            return back()->with('flash', ['success' => 'Successfully enrolled student.']);
        }

        return back()->with('flash', ['error' => 'Enrollment failed.']);
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

        if ($this->enrollmentService->verifyByCashierWithoutReceipt($enrollment, $data)) {
            return back()->with('flash', ['success' => 'Student enrolled without receipt.']);
        }

        return back()->with('flash', ['error' => 'Enrollment failed.']);
    }

    public function undoCashierVerification(StudentEnrollment $enrollment): RedirectResponse
    {
        if ($this->enrollmentService->undoCashierVerification($enrollment->id)) {
            return back()->with('flash', ['success' => 'Cashier verification undone.']);
        }

        return back()->with('flash', ['error' => 'Undo failed.']);
    }

    public function undoHeadDeptVerification(StudentEnrollment $enrollment): RedirectResponse
    {
        if ($this->enrollmentService->undoHeadDeptVerification($enrollment)) {
            return back()->with('flash', ['success' => 'Head Dept verification undone.']);
        }

        return back()->with('flash', ['error' => 'Undo failed.']);
    }

    public function enrollInClass(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'force_enrollment' => 'boolean',
        ]);

        try {
            $class = Classes::findOrFail($data['class_id']);
            $student = $enrollment->student;

            // Check if already enrolled
            $exists = ClassEnrollment::where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->exists();

            if ($exists) {
                return back()->with('flash', ['warning' => 'Student is already enrolled in this class.']);
            }

            // Check capacity
            $enrolledCount = ClassEnrollment::where('class_id', $class->id)->count();
            if (! ($data['force_enrollment'] ?? false) && $class->maximum_slots > 0 && $enrolledCount >= $class->maximum_slots) {
                return back()->with('flash', ['error' => 'Class is full. Use force enrollment to override.']);
            }

            ClassEnrollment::create([
                'class_id' => $class->id,
                'student_id' => $student->id,
                'status' => true,
            ]);

            return back()->with('flash', ['success' => "Enrolled in {$class->subject_code}."]);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        }
    }

    public function retryEnrollment(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $force = $request->boolean('force_enrollment', true);
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
        $result = $this->enrollmentService->resendAssessmentNotification($enrollment);
        if ($result['success']) {
            return back()->with('flash', ['success' => 'Assessment notification queued.']);
        }

        return back()->with('flash', ['error' => $result['message']]);
    }

    public function createAssessmentPdf(StudentEnrollment $enrollment): RedirectResponse
    {
        try {
            GenerateAssessmentPdfJob::dispatch($enrollment, uniqid('pdf_', true), true);

            return back()->with('flash', ['success' => 'PDF generation queued.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate bulk assessments PDF for current semester enrollments
     */
    public function generateBulkAssessments(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_filter' => ['nullable', 'string'],
            'year_level_filter' => ['nullable', 'string'],
            'student_limit' => ['nullable', 'string'],
            'include_deleted' => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();
        if (! $user instanceof User) {
            return back()->with('flash', ['error' => 'Unauthorized.']);
        }

        try {
            // Get current settings explicitly for the job context
            $settingsService = app(GeneralSettingsService::class);
            $currentSemester = $settingsService->getCurrentSemester();
            $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

            $filters = [
                'course_filter' => $validated['course_filter'] ?? 'all',
                'year_level_filter' => $validated['year_level_filter'] ?? 'all',
                'student_limit' => $validated['student_limit'] ?? 'all',
                'include_deleted' => $validated['include_deleted'] ?? false,
                'semester' => $currentSemester,
                'school_year' => $currentSchoolYear,
            ];

            GenerateBulkAssessmentsJob::dispatch($filters, $user->id);

            return back()->with('flash', [
                'success' => 'Bulk assessment generation has been queued. You will receive a notification when it\'s ready.',
            ]);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => 'Failed to queue bulk assessment generation: '.$e->getMessage()]);
        }
    }

    /**
     * Get assessment preview data for client-side rendering
     */
    public function assessmentPreviewData(StudentEnrollment $enrollment, GeneralSettingsService $settingsService): JsonResponse
    {
        return response()->json($this->getAssessmentData($enrollment, $settingsService));
    }

    /**
     * Show the assessment preview page (immersive, print-friendly)
     */
    public function assessmentPreview(StudentEnrollment $enrollment, GeneralSettingsService $settingsService): Response
    {
        return Inertia::render('administrators/enrollments/assessment-preview', [
            'data' => $this->getAssessmentData($enrollment, $settingsService),
            'enrollmentId' => $enrollment->id,
        ]);
    }

    public function quickEnroll(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        if (! Auth::user()->hasRole('super_admin')) {
            abort(403);
        }

        $request->validate([
            'remarks' => 'required|string',
            'confirm_emergency' => 'required|accepted',
            'confirm_payment' => 'required|accepted',
        ]);

        try {
            $enrollment->status = $this->enrollmentPipelineService->getDepartmentVerifiedStatus();
            $enrollment->save();

            $success = $this->enrollmentService->verifyByCashierWithoutReceipt($enrollment, [
                'remarks' => '⚡ QUICK ENROLL: '.$request->input('remarks'),
            ]);

            if ($success) {
                return back()->with('flash', ['success' => 'Quick enrollment successful.']);
            }

            return back()->with('flash', ['error' => 'Quick enrollment failed.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'scholarship_type' => ['nullable', 'string', 'max:255'],
        ]);

        $student->update([
            'scholarship_type' => $validated['scholarship_type'],
        ]);

        return redirect()->back()->with('flash', [
            'success' => 'Student scholarship status updated successfully.',
        ]);
    }

    public function updateTransaction(Request $request, StudentEnrollment $enrollment, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'invoicenumber' => ['required', 'string'],
        ]);

        try {
            DB::transaction(function () use ($enrollment, $transaction, $validated): void {
                // Calculate old total amount
                $oldAmount = $transaction->raw_total_amount;

                // Update settlements
                $settlements = $transaction->settlements;
                if (is_string($settlements)) {
                    $settlements = json_decode($settlements, true);
                }

                // Heuristic: If settlements has 'tuition_fee', update that.
                // If it has 'others', update that.
                // If it's empty or null, create 'tuition_fee'.
                if (isset($settlements['tuition_fee'])) {
                    $settlements['tuition_fee'] = $validated['amount'];
                } elseif (isset($settlements['others'])) {
                    $settlements['others'] = $validated['amount'];
                } else {
                    // Default to tuition_fee if ambiguous
                    $settlements['tuition_fee'] = $validated['amount'];
                }

                $transaction->update([
                    'settlements' => $settlements,
                    'invoicenumber' => $validated['invoicenumber'],
                ]);

                // Recalculate difference
                $newAmount = $transaction->raw_total_amount;
                $diff = $newAmount - $oldAmount;

                // Update StudentTuition
                $tuition = $enrollment->studentTuition;
                if ($tuition) {
                    $tuition->total_balance -= $diff;

                    // Update downpayment if description matches
                    if (str_contains(mb_strtolower($transaction->description), 'downpayment')) {
                        $tuition->downpayment += $diff;
                    }

                    $tuition->save();
                }
            });

            return back()->with('flash', ['success' => 'Transaction updated successfully.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => 'Failed to update transaction: '.$e->getMessage()]);
        }
    }

    public function updateTuition(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate([
            'total_lectures' => ['required', 'numeric', 'min:0'],
            'total_laboratory' => ['required', 'numeric', 'min:0'],
            'total_miscelaneous_fees' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'integer', 'min:0', 'max:100'],
            'paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tuition = $enrollment->studentTuition;

        if (! $tuition) {
            return back()->with('flash', ['error' => 'Tuition record not found.']);
        }

        try {
            DB::transaction(function () use ($tuition, $validated, $enrollment): void {
                $lecture = (float) $validated['total_lectures'];
                $laboratory = (float) $validated['total_laboratory'];
                $misc = (float) $validated['total_miscelaneous_fees'];
                $discountPercent = (int) $validated['discount'];
                $paid = isset($validated['paid']) ? (float) $validated['paid'] : null;

                $tuition->total_lectures = $lecture;
                $tuition->total_laboratory = $laboratory;
                $tuition->total_miscelaneous_fees = $misc;
                $tuition->discount = $discountPercent;
                $tuition->total_tuition = $lecture + $laboratory;

                if ($paid !== null) {
                    $tuition->paid = $paid;
                }

                // Recalculate overall tuition
                // overall = total_tuition + misc + additional_fees
                $additionalFees = $enrollment->additionalFees()->sum('amount');
                $overall = $tuition->total_tuition + $misc + $additionalFees;
                $tuition->overall_tuition = $overall;

                // Recalculate balance
                // balance = overall - paid
                // We rely on the totalPaid accessor to get accurate paid amount
                // If we updated 'paid' column above, accessor will prioritize it
                $totalPaid = $tuition->total_paid;
                $tuition->total_balance = $overall - $totalPaid;

                $tuition->save();
            });

            return back()->with('flash', ['success' => 'Tuition details updated successfully.']);
        } catch (Exception $e) {
            return back()->with('flash', ['error' => 'Failed to update tuition: '.$e->getMessage()]);
        }
    }

    /**
     * Show the form for creating a new enrollment.
     */
    public function edit(StudentEnrollment $enrollment, GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $enrollment->load([
            'student.Course',
            'subjectsEnrolled.subject.course',
            'studentTuition',
            'additionalFees',
        ]);

        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        $subjectsWithClasses = $this->subjectsWithClassesForEnrollment($enrollment);

        $tuition = $enrollment->studentTuition;

        return Inertia::render('administrators/enrollments/edit', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'settings' => [
                'currentSemester' => $currentSemester,
                'currentSchoolYear' => $currentSchoolYear,
                'availableSemesters' => $settingsService->getAvailableSemesters(),
                'availableAcademicYears' => [
                    '1' => '1st Year',
                    '2' => '2nd Year',
                    '3' => '3rd Year',
                    '4' => '4th Year',
                ],
            ],
            'enrollment' => [
                'id' => $enrollment->id,
                'semester' => $enrollment->semester,
                'academic_year' => $enrollment->academic_year,
                'school_year' => $enrollment->school_year,
                'student' => [
                    'id' => $enrollment->student->id,
                    'full_name' => $enrollment->student->full_name,
                    'email' => $enrollment->student->email,
                    'course_id' => $enrollment->student->course_id,
                    'course_code' => $enrollment->student->Course?->code,
                    'academic_year' => $enrollment->student->academic_year,
                    'formatted_academic_year' => $enrollment->student->formatted_academic_year,
                    'miscellaneous_fee' => $enrollment->student->Course?->getMiscellaneousFee() ?? 3500,
                ],
                'subjects' => $enrollment->subjectsEnrolled->map(fn ($subjectEnrollment): array => [
                    'subject_id' => $subjectEnrollment->subject_id,
                    'subject_code' => $subjectEnrollment->subject?->code ?? '',
                    'subject_title' => $subjectEnrollment->subject?->title ?? '',
                    'class_id' => $subjectEnrollment->class_id,
                    'is_modular' => (bool) $subjectEnrollment->is_modular,
                    'lecture_units' => $subjectEnrollment->enrolled_lecture_units ?? ($subjectEnrollment->subject?->lecture ?? 0),
                    'laboratory_units' => $subjectEnrollment->enrolled_laboratory_units ?? ($subjectEnrollment->subject?->laboratory ?? 0),
                    'lecture_fee' => $subjectEnrollment->lecture_fee ?? 0,
                    'laboratory_fee' => $subjectEnrollment->laboratory_fee ?? 0,
                    'lec_per_unit' => $subjectEnrollment->subject?->course?->lec_per_unit ?? 0,
                    'lab_per_unit' => $subjectEnrollment->subject?->course?->lab_per_unit ?? 0,
                    'has_classes' => $subjectsWithClasses->contains($subjectEnrollment->subject?->code ?? ''),
                ])->values(),
                'tuition' => $tuition ? [
                    'discount' => $tuition->discount,
                    'downpayment' => $tuition->downpayment,
                ] : null,
                'additional_fees' => $enrollment->additionalFees->map(fn ($fee): array => [
                    'fee_name' => $fee->fee_name,
                    'amount' => $fee->amount,
                ])->values(),
            ],
            'flash' => session('flash'),
        ]);
    }

    public function updateEnrollment(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'academic_year' => ['required', 'integer', 'in:1,2,3,4'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subject,id'],
            'subjects.*.class_id' => ['nullable', 'exists:classes,id'],
            'subjects.*.is_modular' => ['boolean'],
            'subjects.*.lecture_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.laboratory_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.enrolled_lecture_units' => ['required', 'integer', 'min:0'],
            'subjects.*.enrolled_laboratory_units' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'additional_fees' => ['nullable', 'array'],
            'additional_fees.*.fee_name' => ['required_with:additional_fees', 'string'],
            'additional_fees.*.amount' => ['required_with:additional_fees', 'numeric', 'min:0'],
        ]);

        if ((int) $validated['student_id'] !== (int) $enrollment->student_id) {
            return back()->with('flash', ['error' => 'Student selection cannot be changed for this enrollment.']);
        }

        try {
            DB::transaction(function () use ($validated, $enrollment): void {
                $enrollment->update([
                    'semester' => $validated['semester'],
                    'academic_year' => $validated['academic_year'],
                    'downpayment' => $validated['downpayment'] ?? 0,
                ]);

                /** @var Collection<int, array{subject_id: int|string, class_id?: int|string|null, is_modular?: bool, lecture_fee: int|float|string, laboratory_fee: int|float|string, enrolled_lecture_units: int|string, enrolled_laboratory_units: int|string}> $incomingSubjects */
                $incomingSubjects = collect($validated['subjects']);
                /** @var Collection<int|string, SubjectEnrollment> $existingSubjects */
                $existingSubjects = $enrollment->subjectsEnrolled()->get()->keyBy('subject_id');

                foreach ($incomingSubjects as $subjectData) {
                    $subjectId = $subjectData['subject_id'];
                    $incomingClassId = isset($subjectData['class_id']) ? (int) $subjectData['class_id'] : null;
                    $payload = [
                        'class_id' => $incomingClassId,
                        'is_modular' => $subjectData['is_modular'] ?? false,
                        'lecture_fee' => $subjectData['lecture_fee'],
                        'laboratory_fee' => $subjectData['laboratory_fee'],
                        'enrolled_lecture_units' => $subjectData['enrolled_lecture_units'],
                        'enrolled_laboratory_units' => $subjectData['enrolled_laboratory_units'],
                        'academic_year' => $validated['academic_year'],
                        'school_year' => $enrollment->school_year,
                        'semester' => $validated['semester'],
                    ];

                    if ($existingSubjects->has($subjectId)) {
                        $existingSubject = $existingSubjects->get($subjectId);
                        if (! $existingSubject instanceof SubjectEnrollment) {
                            continue;
                        }
                        $previousClassId = $existingSubject->class_id;

                        $existingSubject->update($payload);

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
                        }
                    } else {
                        $enrollment->subjectsEnrolled()->create(array_merge($payload, [
                            'subject_id' => $subjectId,
                            'student_id' => $enrollment->student_id,
                        ]));

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

                // Delete subject enrollments that are no longer in the incoming list
                // Note: We filter manually because Eloquent Collection's except() doesn't work correctly with keyBy()
                $incomingSubjectIds = $incomingSubjects
                    ->pluck('subject_id')
                    ->map(fn (mixed $value, int|string $key): int => (int) $value)
                    ->all();
                $existingSubjects->filter(
                    fn (SubjectEnrollment $subject): bool => ! in_array((int) $subject->subject_id, $incomingSubjectIds, true)
                )->each(function (SubjectEnrollment $subject) use ($enrollment): void {
                    if ($subject->class_id) {
                        ClassEnrollment::query()
                            ->where('student_id', $enrollment->student_id)
                            ->where('class_id', $subject->class_id)
                            ->delete();
                    }

                    $subject->delete();
                });

                $enrollment->additionalFees()->delete();
                foreach ($validated['additional_fees'] ?? [] as $fee) {
                    $enrollment->additionalFees()->create([
                        'fee_name' => $fee['fee_name'],
                        'amount' => $fee['amount'],
                    ]);
                }

                $this->updateEnrollmentTuition(
                    $enrollment,
                    $validated['subjects'],
                    (int) ($validated['discount'] ?? 0),
                    (float) ($validated['downpayment'] ?? 0),
                    $validated['additional_fees'] ?? []
                );
            });

            return redirect()
                ->route('administrators.enrollments.show', $enrollment->id)
                ->with('flash', ['success' => 'Enrollment updated successfully.']);
        } catch (Exception $e) {
            return back()
                ->withInput()
                ->with('flash', ['error' => 'Failed to update enrollment: '.$e->getMessage()]);
        }
    }

    public function create(GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        return Inertia::render('administrators/enrollments/create', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'settings' => [
                'currentSemester' => $currentSemester,
                'currentSchoolYear' => $currentSchoolYear,
                'availableSemesters' => $settingsService->getAvailableSemesters(),
                'availableAcademicYears' => [
                    '1' => '1st Year',
                    '2' => '2nd Year',
                    '3' => '3rd Year',
                    '4' => '4th Year',
                ],
            ],
            'flash' => session('flash'),
        ]);
    }

    /**
     * Store a newly created enrollment in storage.
     */
    public function store(Request $request, GeneralSettingsService $settingsService): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'academic_year' => ['required', 'integer', 'in:1,2,3,4'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subject,id'],
            'subjects.*.class_id' => ['nullable', 'exists:classes,id'],
            'subjects.*.is_modular' => ['boolean'],
            'subjects.*.lecture_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.laboratory_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.enrolled_lecture_units' => ['required', 'integer', 'min:0'],
            'subjects.*.enrolled_laboratory_units' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'additional_fees' => ['nullable', 'array'],
            'additional_fees.*.fee_name' => ['required_with:additional_fees', 'string'],
            'additional_fees.*.amount' => ['required_with:additional_fees', 'numeric', 'min:0'],
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $settingsService): array {
                $student = Student::with('Course')->findOrFail($validated['student_id']);
                $schoolYear = $settingsService->getCurrentSchoolYearString();

                // Check for existing enrollment in the same period
                $existingEnrollment = StudentEnrollment::query()
                    ->where('student_id', $student->id)
                    ->where('school_year', $schoolYear)
                    ->where('semester', $validated['semester'])
                    ->first();

                if ($existingEnrollment) {
                    throw new Exception('Student already has an enrollment for this semester.');
                }

                // Create the enrollment record
                $enrollment = StudentEnrollment::query()->create([
                    'student_id' => $student->id,
                    'course_id' => $student->course_id,
                    'status' => $this->enrollmentPipelineService->getPendingStatus(),
                    'semester' => $validated['semester'],
                    'academic_year' => $validated['academic_year'],
                    'school_year' => $schoolYear,
                    'downpayment' => $validated['downpayment'] ?? 0,
                ]);

                // Create subject enrollments
                foreach ($validated['subjects'] as $subjectData) {
                    $classId = isset($subjectData['class_id']) ? (int) $subjectData['class_id'] : null;

                    $enrollment->subjectsEnrolled()->create([
                        'subject_id' => $subjectData['subject_id'],
                        'class_id' => $classId,
                        'student_id' => $student->id,
                        'is_modular' => $subjectData['is_modular'] ?? false,
                        'lecture_fee' => $subjectData['lecture_fee'],
                        'laboratory_fee' => $subjectData['laboratory_fee'],
                        'enrolled_lecture_units' => $subjectData['enrolled_lecture_units'],
                        'enrolled_laboratory_units' => $subjectData['enrolled_laboratory_units'],
                        'academic_year' => $validated['academic_year'],
                        'school_year' => $schoolYear,
                        'semester' => $validated['semester'],
                    ]);

                    if ($classId) {
                        $classEnrollment = ClassEnrollment::query()
                            ->withTrashed()
                            ->firstOrNew([
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

                // Create tuition record
                $this->enrollmentService->createStudentTuition($enrollment, [
                    'subjectsEnrolled' => $validated['subjects'],
                    'discount' => $validated['discount'] ?? 0,
                    'downpayment' => $validated['downpayment'] ?? 0,
                    'additionalFees' => $validated['additional_fees'] ?? [],
                ]);

                // Create additional fees if provided
                if (! empty($validated['additional_fees'])) {
                    foreach ($validated['additional_fees'] as $fee) {
                        $enrollment->additionalFees()->create([
                            'fee_name' => $fee['fee_name'],
                            'amount' => $fee['amount'],
                        ]);
                    }
                }

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                ];
            });

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
    public function searchStudents(Request $request)
    {
        $search = $request->input('search', '');

        if (mb_strlen((string) $search) < 2) {
            return response()->json([]);
        }

        $students = Student::query()
            ->with('Course')
            ->where(function ($query) use ($search): void {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"])
                    ->orWhereHas('Course', function ($q) use ($search): void {
                        $q->where('code', 'ilike', "%{$search}%");
                    });
            })
            ->limit(50)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'course_id' => $student->course_id,
                'course_code' => $student->Course?->code,
                'academic_year' => $student->academic_year,
                'formatted_academic_year' => $student->formatted_academic_year,
                'label' => sprintf(
                    '%d - %s, %s | %s | %s',
                    $student->id,
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

        $schoolYear = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();

        // Get subjects with classes for the current period
        $classesWithSubjects = Classes::query()
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->get();

        $subjectsWithClasses = collect();
        foreach ($classesWithSubjects as $class) {
            if (! empty($class->course_codes) && is_array($class->course_codes)) {
                $courseCodesAsStrings = array_map(strval(...), $class->course_codes);
                if (! in_array((string) $courseId, $courseCodesAsStrings)) {
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
        $subjectsWithClasses = $subjectsWithClasses->unique();

        // Search subjects
        $subjectsQuery = Subject::query()
            ->with('course')
            ->where('course_id', $courseId);

        if ($search) {
            $subjectsQuery->where(function ($query) use ($search): void {
                $query->where('code', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%");
            });
        }

        $subjects = $subjectsQuery
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->orderBy('code')
            ->limit(100)
            ->get()
            ->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'lecture' => $subject->lecture,
                'laboratory' => $subject->laboratory,
                'lec_per_unit' => $subject->course?->lec_per_unit ?? 0,
                'lab_per_unit' => $subject->course?->lab_per_unit ?? 0,
                'has_classes' => $subjectsWithClasses->contains($subject->code),
                'label' => ($subjectsWithClasses->contains($subject->code) ? '⭐ ' : '')
                    .$subject->code.' - '.$subject->title
                    .($subjectsWithClasses->contains($subject->code) ? '' : ' (⚠️ No Classes)'),
            ]);

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

        $schoolYear = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();

        $allClasses = Classes::query()
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->where(function ($query) use ($subject): void {
                $query->whereJsonContains('subject_ids', $subject->id)
                    ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                    ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
            })
            ->with(['Faculty', 'schedules.room'])
            ->withCount('class_enrollments')
            ->get();

        // Filter by course
        $classes = $allClasses->filter(function ($class) use ($courseId): bool {
            if (! empty($class->course_codes) && is_array($class->course_codes)) {
                $courseCodesAsStrings = array_map(strval(...), $class->course_codes);

                return in_array((string) $courseId, $courseCodesAsStrings);
            }

            return false;
        });

        $sections = $classes->map(function ($class): array {
            $enrolledCount = $class->class_enrollments_count;
            $maxSlots = $class->maximum_slots ?: 0;
            $availableSlots = $maxSlots > 0 ? $maxSlots - $enrolledCount : null;

            // Format schedule: "Mon 8:00 AM - 10:00 AM (Room 101)"
            $scheduleStr = $class->schedules
                ->map(fn ($s): string => sprintf(
                    '%s %s%s',
                    mb_substr((string) $s->day_of_week, 0, 3), // Mon
                    $s->time_range,                // 8:00 AM - 10:00 AM
                    $s->room ? ' ('.$s->room->name.')' : ''
                ))
                ->implode(', ');

            return [
                'id' => $class->id,
                'section' => $class->section,
                'faculty' => $class->Faculty?->full_name ?? 'TBA',
                'enrolled_count' => $enrolledCount,
                'max_slots' => $maxSlots,
                'available_slots' => $availableSlots,
                'is_full' => $maxSlots > 0 && $enrolledCount >= $maxSlots,
                'schedule' => $scheduleStr ?: 'TBA',
                'schedules' => $class->schedules->map(fn ($s): array => [
                    'day' => $s->day_of_week,
                    'start_time' => $s->start_time instanceof \Carbon\Carbon ? $s->start_time->format('H:i') : $s->start_time,
                    'end_time' => $s->end_time instanceof \Carbon\Carbon ? $s->end_time->format('H:i') : $s->end_time,
                    'room' => $s->room?->name,
                ]),
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
    public function calculateSubjectFees(Request $request)
    {
        $subjectId = $request->input('subject_id');
        $isModular = $request->boolean('is_modular', false);

        if (! $subjectId) {
            return response()->json(['error' => 'Subject ID required'], 400);
        }

        $subject = Subject::with('course')->find($subjectId);
        if (! $subject) {
            return response()->json(['error' => 'Subject not found'], 404);
        }

        $isNSTP = str_contains(mb_strtoupper((string) $subject->code), 'NSTP');
        $totalUnits = $subject->lecture + $subject->laboratory;
        $courseLecPerUnit = $subject->course?->lec_per_unit ?? 0;
        $courseLabPerUnit = $subject->course?->lab_per_unit ?? 0;

        // Calculate lecture fee
        $lectureFee = $subject->lecture ? $totalUnits * $courseLecPerUnit : 0;
        if ($isNSTP) {
            $lectureFee *= 0.5;
        }

        // Calculate laboratory fee
        $laboratoryFee = $subject->laboratory ? 1 * $courseLabPerUnit : 0;
        if ($isModular && $subject->laboratory) {
            $laboratoryFee /= 2;
        }

        return response()->json([
            'subject_id' => $subject->id,
            'code' => $subject->code,
            'title' => $subject->title,
            'lecture_units' => $subject->lecture,
            'laboratory_units' => $subject->laboratory,
            'lecture_fee' => round($lectureFee, 2),
            'laboratory_fee' => round($laboratoryFee, 2),
            'is_nstp' => $isNSTP,
            'is_modular' => $isModular,
            'modular_fee' => $isModular ? 2400 : 0,
        ]);
    }

    /**
     * Get student details with course info.
     *
     * @return JsonResponse
     */
    public function getStudentDetails(Request $request)
    {
        $studentId = $request->input('student_id');

        if (! $studentId) {
            return response()->json(['error' => 'Student ID required'], 400);
        }

        $student = Student::with('Course')->find($studentId);
        if (! $student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        return response()->json([
            'id' => $student->id,
            'full_name' => $student->full_name,
            'email' => $student->email,
            'course_id' => $student->course_id,
            'course_code' => $student->Course?->code,
            'course_name' => $student->Course?->name,
            'academic_year' => $student->academic_year,
            'formatted_academic_year' => $student->formatted_academic_year,
            'miscellaneous_fee' => $student->Course?->getMiscellaneousFee() ?? 3500,
        ]);
    }

    /**
     * Soft delete a student enrollment.
     */
    public function destroy(StudentEnrollment $enrollment): RedirectResponse
    {
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

        $studentName = $enrollmentModel->student?->full_name ?? 'Unknown Student';

        try {
            DB::transaction(function () use ($enrollmentModel): void {
                // Delete related subject enrollments
                $enrollmentModel->subjectsEnrolled()->delete();

                // Delete related additional fees
                $enrollmentModel->additionalFees()->delete();

                // Delete related tuition record
                $enrollmentModel->studentTuition()->delete();

                // Delete related resources
                $enrollmentModel->resources()->delete();

                // Delete related enrollment transactions
                $enrollmentModel->enrollmentTransactions()->delete();

                // Force delete the enrollment
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
        $recentDeletions = Activity::query()
            ->where(function ($query) use ($enrollment): void {
                // Subject enrollment deletions for this enrollment
                $query->where('log_name', 'subject_enrollment')
                    ->where('event', 'deleted')
                    ->where('properties', 'like', '%"enrollment_id":'.$enrollment->id.'%');
            })
            ->orWhere(function ($query) use ($enrollment): void {
                // Class enrollment deletions for this student in the same school year/semester
                $query->where('log_name', 'class_enrollments')
                    ->where('event', 'deleted')
                    ->where('properties', 'like', '%"student_id":'.$enrollment->student_id.'%');
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (Activity $activity): array {
                $properties = $activity->properties?->toArray() ?? [];
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
    public function restoreSubjects(Request $request, StudentEnrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate([
            'activity_ids' => ['required', 'array', 'min:1'],
            'activity_ids.*' => ['required', 'integer', 'exists:activity_log,id'],
        ]);

        $restoredCount = 0;
        $errors = [];

        DB::transaction(function () use ($validated, $enrollment, &$restoredCount, &$errors): void {
            foreach ($validated['activity_ids'] as $activityId) {
                $activity = Activity::query()->find($activityId);
                if (! $activity) {
                    $errors[] = "Activity log #{$activityId} not found";

                    continue;
                }

                $properties = $activity->properties?->toArray() ?? [];
                $oldData = $properties['old'] ?? [];

                if ($activity->log_name === 'subject_enrollment' && $activity->event === 'deleted') {
                    // Check if enrollment_id matches
                    if (($oldData['enrollment_id'] ?? null) !== $enrollment->id) {
                        $errors[] = "Activity #{$activityId} belongs to a different enrollment";

                        continue;
                    }

                    // Check if subject enrollment already exists
                    $existing = SubjectEnrollment::query()
                        ->where('enrollment_id', $oldData['enrollment_id'])
                        ->where('subject_id', $oldData['subject_id'])
                        ->first();

                    if ($existing) {
                        $errors[] = "Subject enrollment for subject_id {$oldData['subject_id']} already exists";

                        continue;
                    }

                    // Restore the subject enrollment
                    SubjectEnrollment::query()->create($oldData);
                    $restoredCount++;

                    // Also restore class enrollment if needed
                    if (! empty($oldData['class_id'])) {
                        $classEnrollmentExists = ClassEnrollment::query()
                            ->where('class_id', $oldData['class_id'])
                            ->where('student_id', $oldData['student_id'])
                            ->exists();

                        if (! $classEnrollmentExists) {
                            ClassEnrollment::query()->create([
                                'class_id' => $oldData['class_id'],
                                'student_id' => $oldData['student_id'],
                                'status' => true,
                            ]);
                        }
                    }
                }
            }
        });

        if ($restoredCount > 0) {
            $message = "Successfully restored {$restoredCount} subject enrollment(s).";
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
            $query->join('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
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
            ->where('student_enrollment.status', '!=', $this->enrollmentPipelineService->getPendingStatus())
            ->join('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
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

    public function enrollmentReportExport(Request $request, GeneralSettingsService $settingsService): BinaryFileResponse
    {
        $validated = $this->validateEnrollmentReportFilters($request);
        $payload = $this->buildEnrollmentReportPayload($validated, $settingsService);
        $reportData = $payload['report'];

        [$headings, $rows] = $this->buildEnrollmentReportExportData($reportData);

        $fileName = sprintf(
            'enrollment-report-%s-%s.xlsx',
            Str::slug((string) ($reportData['type'] ?? 'report')),
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
            $query->whereHas('subject', function ($q) use ($search): void {
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%");
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
        $schoolYearString = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();

        $courses = StudentEnrollment::query()
            ->where('school_year', $schoolYearString)
            ->where('semester', $semester)
            ->whereHas('course')
            ->with('course:id,code,title,department_id')
            ->select('course_id')
            ->distinct()
            ->get()
            ->map(fn (StudentEnrollment $se): array => [
                'id' => $se->course_id,
                'code' => $se->course?->code,
                'title' => $se->course?->title,
                'department' => $se->course?->department?->code,
                'label' => $se->course?->code.' - '.$se->course?->title,
            ])
            ->sortBy('code')
            ->values();

        return response()->json(['courses' => $courses]);
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
            return \Illuminate\Support\Facades\Storage::url($logoValue);
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
    private function buildEnrollmentReportExportData(array $reportData): array
    {
        $type = $reportData['type'] ?? '';

        if ($type === 'enrolled_by_course') {
            $headings = ['No.', 'Student ID', 'Full Name', 'Course', 'Department', 'Year Level', 'Subjects', 'Status'];
            $rows = collect($reportData['students'] ?? [])
                ->map(fn (array $student): array => [
                    $student['no'] ?? null,
                    $student['student_id'] ?? null,
                    $student['full_name'] ?? null,
                    $student['course'] ?? null,
                    $student['department'] ?? null,
                    isset($student['year_level']) ? 'Year '.$student['year_level'] : null,
                    $student['subjects_count'] ?? null,
                    $student['status'] ?? null,
                ])
                ->values()
                ->all();

            return [$headings, $rows];
        }

        if ($type === 'enrolled_by_subject') {
            $headings = ['Subject Code', 'Subject Title', 'Units', 'Total Enrolled', 'No.', 'Student ID', 'Full Name', 'Course', 'Year Level', 'Section', 'Schedule'];
            $rows = [];

            foreach ($reportData['subject_groups'] ?? [] as $group) {
                foreach ($group['students'] ?? [] as $student) {
                    $rows[] = [
                        $group['subject_code'] ?? null,
                        $group['subject_title'] ?? null,
                        $group['subject_units'] ?? null,
                        $group['total_enrolled'] ?? null,
                        $student['no'] ?? null,
                        $student['student_id'] ?? null,
                        $student['full_name'] ?? null,
                        $student['course'] ?? null,
                        isset($student['year_level']) ? 'Year '.$student['year_level'] : null,
                        $student['section'] ?? null,
                        $student['class_schedule'] ?? null,
                    ];
                }
            }

            return [$headings, $rows];
        }

        $totalEnrolled = (int) ($reportData['total_enrolled'] ?? 0);
        $headings = ['Section', 'Label', 'Count', 'Percentage', 'Additional'];
        $rows = [
            ['Summary', 'Total Enrolled', $totalEnrolled, '100%', null],
        ];

        foreach ($reportData['by_department'] ?? [] as $item) {
            $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
            $rows[] = ['By Department', $item['department'] ?? 'Unknown', $item['count'] ?? 0, $percentage, null];
        }

        foreach ($reportData['by_course'] ?? [] as $item) {
            $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
            $label = mb_trim(($item['course_code'] ?? 'Unknown').' - '.($item['course_title'] ?? 'Unknown'));
            $rows[] = ['By Course', $label, $item['count'] ?? 0, $percentage, $item['department'] ?? null];
        }

        foreach ($reportData['by_year_level'] ?? [] as $item) {
            $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
            $rows[] = ['By Year Level', 'Year '.($item['year_level'] ?? '—'), $item['count'] ?? 0, $percentage, null];
        }

        foreach ($reportData['by_status'] ?? [] as $item) {
            $percentage = $totalEnrolled > 0 ? round(($item['count'] / $totalEnrolled) * 100, 1).'%' : '0%';
            $rows[] = ['By Status', $item['status'] ?? 'Unknown', $item['count'] ?? 0, $percentage, null];
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
        return Activity::query()
            ->where(function ($query) use ($enrollment): void {
                $query->where('log_name', 'subject_enrollment')
                    ->where('event', 'deleted')
                    ->where('properties', 'like', '%"enrollment_id":'.$enrollment->id.'%');
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (Activity $activity): array {
                $properties = $activity->properties?->toArray() ?? [];
                $oldData = $properties['old'] ?? [];

                // Get subject info for display
                $subject = Subject::query()->find($oldData['subject_id'] ?? null);

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
            ->toArray();
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

    private function updateEnrollmentTuition(
        StudentEnrollment $enrollment,
        array $subjects,
        int $discount,
        float $downpayment,
        array $additionalFees
    ): void {
        $subjectIds = collect($subjects)
            ->pluck('subject_id')
            ->filter()
            ->unique();

        $subjectModels = Subject::with('course')
            ->findMany($subjectIds)
            ->keyBy('id');

        $totalLecture = 0.0;
        $totalLaboratory = 0.0;

        foreach ($subjects as $subjectData) {
            $subject = $subjectModels->get($subjectData['subject_id'] ?? 0);
            if (! $subject) {
                continue;
            }

            $isModular = (bool) ($subjectData['is_modular'] ?? false);
            $isNSTP = str_contains(mb_strtoupper((string) $subject->code), 'NSTP');
            $totalUnits = ($subject->lecture ?? 0) + ($subject->laboratory ?? 0);
            $lectureFee = $subject->lecture ? $totalUnits * ($subject->course?->lec_per_unit ?? 0) : 0;

            if ($isNSTP) {
                $lectureFee *= 0.5;
            }

            $laboratoryFee = $subject->laboratory ? 1 * ($subject->course?->lab_per_unit ?? 0) : 0;

            if ($isModular) {
                $lectureFee = 2400;
                $laboratoryFee = 0;
            }

            $totalLecture += (float) $lectureFee;
            $totalLaboratory += (float) $laboratoryFee;
        }

        $discountedLecture = $totalLecture * (1 - $discount / 100);
        $totalTuition = $discountedLecture + $totalLaboratory;
        $miscellaneousFee = $enrollment->course?->getMiscellaneousFee() ?? 3500;

        $additionalFeesTotal = collect($additionalFees)
            ->sum(fn (array $fee): float => (float) ($fee['amount'] ?? 0));

        $overallTotal = $totalTuition + $miscellaneousFee + $additionalFeesTotal;
        $totalPaid = $enrollment->studentTuition?->total_paid ?? 0.0;
        $balance = max(0.0, $overallTotal - $totalPaid);

        StudentTuition::query()->updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'student_id' => $enrollment->student_id,
                'total_tuition' => $totalTuition,
                'total_balance' => $balance,
                'total_lectures' => $discountedLecture,
                'total_laboratory' => $totalLaboratory,
                'total_miscelaneous_fees' => $miscellaneousFee,
                'discount' => $discount,
                'downpayment' => $downpayment,
                'overall_tuition' => $overallTotal,
                'semester' => $enrollment->semester,
                'school_year' => $enrollment->school_year,
                'academic_year' => $enrollment->academic_year,
            ]
        );
    }

    private function getAssessmentData(StudentEnrollment $enrollment, GeneralSettingsService $settingsService): array
    {
        $enrollment->load([
            'student.Course',
            'subjectsEnrolled.subject.course',
            'studentTuition',
            'additionalFees',
        ]);

        // Calculate subject data with fees
        $subjects = $enrollment->subjectsEnrolled->map(function ($se): array {
            $subject = $se->subject;
            $isModular = $se->is_modular ?? false;
            $isNSTP = str_contains(mb_strtoupper($subject->code ?? ''), 'NSTP');
            $hasLab = ($subject->laboratory ?? 0) !== 0;

            // Calculate lecture fee
            $totalSubjectUnits = ($subject->lecture ?? 0) + ($subject->laboratory ?? 0);
            $lectureFee = $totalSubjectUnits * ($subject->course->lec_per_unit ?? 0);

            // Apply NSTP discount
            if ($isNSTP) {
                $lectureFee *= 0.5;
            }

            // Lab fee
            $laboratoryFee = $hasLab ? (1 * ($subject->course->lab_per_unit ?? 0)) : 0;
            if ($isModular && $hasLab) {
                $laboratoryFee /= 2;
            }

            return [
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units ?? 0,
                'is_modular' => $isModular,
                'lecture_fee' => $lectureFee,
                'laboratory_fee' => $laboratoryFee,
                'class_id' => $se->class_id,
            ];
        });

        // Get schedules for each subject
        $subjectsWithSchedules = $subjects->map(function (array $subject): array {
            $class = Classes::find($subject['class_id']);
            $scheduleByDay = [
                'monday' => '',
                'tuesday' => '',
                'wednesday' => '',
                'thursday' => '',
                'friday' => '',
                'saturday' => '',
            ];

            if ($class) {
                foreach ($class->Schedule as $schedule) {
                    $day = mb_strtolower((string) $schedule->day_of_week);
                    if (array_key_exists($day, $scheduleByDay)) {
                        $room = $schedule->room->name ?? '';
                        $section = $class->section ?? '';
                        $scheduleByDay[$day] = $schedule->start_time->format('g:i').'-'.$schedule->end_time->format('g:i').' '.$section.' ('.$room.')';
                    }
                }
            }

            return array_merge($subject, ['schedule' => $scheduleByDay]);
        });

        // Calculate totals
        $totalUnits = $subjects->sum('units');
        $totalLecture = $subjects->sum('lecture_fee');
        $totalLaboratory = $subjects->sum('laboratory_fee');
        $totalModularSubjects = $subjects->where('is_modular', true)->count();
        $totalModularFee = $totalModularSubjects * 2400;

        // Additional fees
        $additionalFees = $enrollment->additionalFees->map(fn ($fee): array => [
            'name' => $fee->fee_name,
            'amount' => $fee->amount,
            'is_required' => $fee->is_required,
        ]);
        $additionalFeesTotal = $enrollment->additionalFees->sum('amount');

        // Tuition summary
        $tuition = $enrollment->studentTuition;
        $totalAmount = $tuition?->overall_tuition ?? ($tuition?->total_lectures + $tuition?->total_laboratory + $tuition?->total_miscelaneous_fees + $additionalFeesTotal);
        $calculatedBalance = null;

        if ($tuition) {
            $calculatedBalance = max(0.0, (float) $tuition->overall_tuition - $tuition->total_paid);

            if ((float) $tuition->total_balance !== $calculatedBalance) {
                $tuition->forceFill([
                    'total_balance' => $calculatedBalance,
                ])->save();
            }
        }

        // General settings
        $generalSettings = $settingsService->getGlobalSettingsModel();
        $siteSettings = app(SiteSettings::class);

        return [
            'student' => [
                'full_name' => $enrollment->student->full_name,
                'student_id' => $enrollment->student->student_id,
                'course_code' => $enrollment->student->Course?->code,
            ],
            'enrollment' => [
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'semester_label' => $settingsService->getAvailableSemesters()[$enrollment->semester] ?? '',
            ],
            'subjects' => $subjectsWithSchedules,
            'totals' => [
                'units' => $totalUnits,
                'lecture' => $totalLecture,
                'laboratory' => $totalLaboratory,
                'modular_subjects' => $totalModularSubjects,
                'modular_fee' => $totalModularFee,
            ],
            'additional_fees' => $additionalFees,
            'additional_fees_total' => $additionalFeesTotal,
            'tuition' => $tuition ? [
                'total_lectures' => $tuition->total_lectures,
                'total_laboratory' => $tuition->total_laboratory,
                'total_miscelaneous_fees' => $tuition->total_miscelaneous_fees,
                'discount' => $tuition->discount,
                'downpayment' => $tuition->downpayment,
                'overall_tuition' => $tuition->overall_tuition,
                'total_balance' => $calculatedBalance ?? $tuition->total_balance,
            ] : null,
            'total_amount' => $totalAmount,
            'school' => [
                'name' => $generalSettings?->school_portal_title ?? $generalSettings?->site_name ?? $siteSettings->getOrganizationName(),
                'logo' => $this->resolveReportLogoFromSettings($generalSettings?->school_portal_logo, $siteSettings),
                'contact' => $generalSettings?->support_phone ?? $siteSettings->getSupportPhone() ?? '',
                'email' => $generalSettings?->support_email ?? $siteSettings->getSupportEmail() ?? '',
                'address' => $siteSettings->getOrganizationAddress() ?? '',
            ],
            'generated_at' => now()->format('m-d-Y'),
        ];
    }

    /**
     * Get list of students enrolled in a specific course/program.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StudentEnrollment>  $baseQuery
     * @param  array<string, mixed>  $filters
     */
    private function getEnrolledByCourseReport($baseQuery, array $filters): array
    {
        $query = clone $baseQuery;

        if (! empty($filters['course_filter']) && $filters['course_filter'] !== 'all') {
            $query->whereExists(function ($subquery) use ($filters): void {
                $subquery->select(DB::raw(1))
                    ->from('courses')
                    ->whereRaw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT) = courses.id')
                    ->where('code', (string) $filters['course_filter']);
            });
        }

        if (! empty($filters['department_filter']) && $filters['department_filter'] !== 'all') {
            $query->whereExists(function ($subquery) use ($filters): void {
                $subquery->select(DB::raw(1))
                    ->from('courses')
                    ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                    ->whereRaw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT) = courses.id')
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
            $course = \App\Models\Course::where('code', $filters['course_filter'])->first();
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
     * @param  \Illuminate\Database\Eloquent\Builder<StudentEnrollment>  $baseQuery
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
                    ->whereRaw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT) = courses.id')
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
}
