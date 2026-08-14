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
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\ShsStrand;
use App\Models\ShsStudent;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\StudentStatusRecord;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Notifications\StatementOfAccountAdjustedNotification;
use App\Services\GeneralSettingsService;
use App\Services\IdentifierGenerator;
use App\Services\StudentIdUpdateService;
use App\Services\StudentSchoolOptionService;
use App\Settings\SiteSettings;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorStudentManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();
        $currentPeriod = [
            'academic_year' => $currentSchoolYear,
            'semester' => $currentSemester,
        ];
        $clearanceCheckEnabled = $generalSettingsService->getGlobalSettingsModel()?->enable_clearance_check ?? true;

        $search = $request->input('search');
        $type = $request->input('type');
        $status = $request->input('status');
        $courseId = $request->integer('course_id');
        $courseId = $courseId > 0 ? $courseId : null;
        $departmentId = $request->integer('department_id');
        $departmentId = $departmentId > 0 ? $departmentId : null;
        $yearLevel = $request->integer('year_level');
        $yearLevel = in_array($yearLevel, [1, 2, 3, 4], true) ? $yearLevel : null;
        $currentEnrollment = $request->string('current_enrollment')->toString();
        $currentEnrollment = in_array($currentEnrollment, ['enrolled', 'not_enrolled'], true) ? $currentEnrollment : null;
        $scholarshipType = $request->input('scholarship_type');
        $employmentStatus = $request->input('employment_status');
        $isIndigenousPerson = $request->input('is_indigenous_person');
        $regionOfOrigin = $request->input('region_of_origin');
        $previousSemesterCleared = $request->input('previous_semester_cleared');
        $trashedFilter = $request->string('trashed', 'active')->toString();
        if (! in_array($trashedFilter, ['active', 'trashed', 'all'], true)) {
            $trashedFilter = 'active';
        }

        $studentsQuery = Student::query()
            ->when($trashedFilter !== 'active', function ($builder): void {
                $builder->withTrashed();
            })
            ->when($trashedFilter === 'trashed', function ($builder): void {
                $builder->onlyTrashed();
            })
            ->with([
                'Course',
                'DocumentLocation',
                'clearances' => function ($query) use ($currentPeriod): void {
                    $query->where('academic_year', $currentPeriod['academic_year'])
                        ->where('semester', $currentPeriod['semester']);
                },
                'statusRecords' => function ($query) use ($currentPeriod): void {
                    $query->where('academic_year', $currentPeriod['academic_year'])
                        ->where('semester', $currentPeriod['semester']);
                },
            ])
            ->when(is_string($search) && mb_trim($search) !== '', function ($builder) use ($search): void {
                $query = mb_strtolower(mb_trim($search));
                $likeQuery = "%{$query}%";
                $driver = DB::connection()->getDriverName();
                $studentIdExpression = $driver === 'mysql'
                    ? 'LOWER(CAST(student_id AS CHAR))'
                    : 'LOWER(CAST(student_id AS TEXT))';
                $fullNameExpression = $driver === 'sqlite'
                    ? "LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))"
                    : "LOWER(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))";
                $lastNameFirstExpression = $driver === 'sqlite'
                    ? "LOWER(COALESCE(last_name, '') || ', ' || COALESCE(first_name, ''))"
                    : "LOWER(CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, '')))";

                $builder->where(function ($nested) use ($fullNameExpression, $lastNameFirstExpression, $likeQuery, $studentIdExpression): void {
                    $nested->whereRaw("{$studentIdExpression} LIKE ?", [$likeQuery])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$likeQuery])
                        ->orWhereRaw("{$fullNameExpression} LIKE ?", [$likeQuery])
                        ->orWhereRaw("{$lastNameFirstExpression} LIKE ?", [$likeQuery]);
                });
            })
            ->when(is_string($type) && $type !== '' && $type !== 'all', function ($builder) use ($type): void {
                $builder->where('student_type', $type);
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function ($builder) use ($status, $currentPeriod): void {
                $builder->whereHas('statusRecords', function ($query) use ($status, $currentPeriod): void {
                    $query->where('academic_year', $currentPeriod['academic_year'])
                        ->where('semester', $currentPeriod['semester'])
                        ->where('status', $status);
                });
            })
            ->when($courseId !== null, function ($builder) use ($courseId): void {
                $builder->where('course_id', $courseId);
            })
            ->when($departmentId !== null, function ($builder) use ($departmentId): void {
                $builder->whereHas('Course', function ($query) use ($departmentId): void {
                    $query->where('department_id', $departmentId);
                });
            })
            ->when($yearLevel !== null, function ($builder) use ($yearLevel): void {
                $builder->where('academic_year', $yearLevel);
            })
            ->when($currentEnrollment !== null, function ($builder) use ($currentEnrollment, $currentPeriod): void {
                $method = $currentEnrollment === 'enrolled' ? 'whereHas' : 'whereDoesntHave';

                $builder->{$method}('statusRecords', function ($query) use ($currentPeriod): void {
                    $query->where('academic_year', $currentPeriod['academic_year'])
                        ->where('semester', $currentPeriod['semester'])
                        ->where('status', StudentStatus::Enrolled->value);
                });
            })
            ->when($scholarshipType && $scholarshipType !== 'all', function ($builder) use ($scholarshipType): void {
                $builder->where('scholarship_type', $scholarshipType);
            })
            ->when($employmentStatus && $employmentStatus !== 'all', function ($builder) use ($employmentStatus): void {
                $builder->where('employment_status', $employmentStatus);
            })
            ->when($isIndigenousPerson !== null && $isIndigenousPerson !== 'all', function ($builder) use ($isIndigenousPerson): void {
                if ($isIndigenousPerson === 'yes') {
                    $builder->where('is_indigenous_person', true);
                } elseif ($isIndigenousPerson === 'no') {
                    $builder->where('is_indigenous_person', false);
                }
            })
            ->when($regionOfOrigin && $regionOfOrigin !== 'all', function ($builder) use ($regionOfOrigin): void {
                $builder->where('region_of_origin', $regionOfOrigin);
            })
            ->when($previousSemesterCleared !== null && $previousSemesterCleared !== 'all', function ($builder) use ($previousSemesterCleared, $currentPeriod): void {
                $isCleared = $previousSemesterCleared === 'true';
                $builder->whereHas('clearances', function ($q) use ($isCleared, $currentPeriod): void {
                    $q->where('academic_year', $currentPeriod['academic_year'])
                        ->where('semester', $currentPeriod['semester'])
                        ->where('is_cleared', $isCleared);
                });
            });

        $sort = $request->string('sort', 'created_at')->toString();
        $allowedSorts = ['name', 'status', 'student_id', 'type', 'course', 'academic_year', 'created_at', 'age', 'gender'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $direction = $request->string('direction', 'desc')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        if ($sort !== '0') {
            if ($sort === 'name') {
                $studentsQuery->orderBy('last_name', $direction)
                    ->orderBy('first_name', $direction);
            } elseif ($sort === 'status') {
                $studentsQuery->orderBy(
                    StudentStatusRecord::query()
                        ->select('status')
                        ->whereColumn('student_id', 'students.id')
                        ->where('academic_year', $currentSchoolYear)
                        ->where('semester', $currentSemester)
                        ->limit(1),
                    $direction
                );
            } elseif ($sort === 'student_id') {
                $studentsQuery->orderBy('student_id', $direction);
            } elseif ($sort === 'type') {
                $studentsQuery->orderBy('student_type', $direction);
            } elseif ($sort === 'course') {
                // Course is a relationship, so we need to join or order by related column
                // Simple approach: orderBy subquery or just ignore complex sort for now
                // Let's use a subquery for accuracy if needed, or join.
                // Given the context, joining might duplicate rows if not careful.
                // Using a subquery/closure for sorting:
                $studentsQuery->orderBy(
                    Course::select('code')
                        ->whereColumn('courses.id', 'students.course_id')
                        ->limit(1),
                    $direction
                );
            } elseif (in_array($sort, ['academic_year', 'created_at', 'age', 'gender'], true)) {
                $studentsQuery->orderBy($sort, $direction);

                if ($sort === 'created_at') {
                    $studentsQuery->orderBy('id', $direction);
                }
            }
        } else {
            $studentsQuery->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        /** @var LengthAwarePaginator $students */
        $perPage = $request->input('per_page', 20);
        $perPage = in_array((int) $perPage, [10, 20, 50, 100]) ? (int) $perPage : 20;

        $students = $studentsQuery
            ->paginate($perPage)
            ->withQueryString();

        $hasActiveFilters = (is_string($search) && mb_trim($search) !== '')
            || (is_string($type) && $type !== '' && $type !== 'all')
            || (is_string($status) && $status !== '' && $status !== 'all')
            || $courseId !== null
            || $departmentId !== null
            || $yearLevel !== null
            || $currentEnrollment !== null
            || ($scholarshipType && $scholarshipType !== 'all')
            || ($employmentStatus && $employmentStatus !== 'all')
            || ($isIndigenousPerson !== null && $isIndigenousPerson !== 'all')
            || ($regionOfOrigin && $regionOfOrigin !== 'all')
            || ($previousSemesterCleared !== null && $previousSemesterCleared !== 'all');

        $students->through(function (Student $student) use ($clearanceCheckEnabled): array {
            $studentType = $student->student_type;
            $currentStatusRecord = $student->statusRecords->first();
            $currentStatus = $currentStatusRecord?->status;
            $scholarshipType = $student->scholarship_type;
            $employmentStatus = $student->employment_status;

            // Get current semester clearance status
            $currentClearanceStatus = 'no_record';
            if ($clearanceCheckEnabled) {
                $currentClearance = $student->clearances->first();
                if ($currentClearance) {
                    $currentClearanceStatus = $currentClearance->is_cleared ? 'cleared' : 'not_cleared';
                }
            }

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'course_id' => $student->course_id,
                'department_id' => $student->Course?->department_id,
                'course' => $student->Course?->code,
                'course_title' => $student->Course?->title,
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
                'avatar_url' => $student->picture1x1 !== '' ? $student->picture1x1 : null,
                'created_at' => format_timestamp($student->created_at),
                'deleted_at' => $student->deleted_at ? format_timestamp($student->deleted_at) : null,
                'filament' => [
                    'view_url' => route('filament.admin.resources.students.view', $student),
                    'edit_url' => route('filament.admin.resources.students.edit', $student),
                ],
            ];
        });

        $globalStudentTotal = $hasActiveFilters ? Student::query()->count() : $students->total();

        $request->attributes->set('admin_students_global_total', $globalStudentTotal);

        $types = collect(StudentType::cases())
            ->map(fn (StudentType $studentType): array => [
                'value' => $studentType->value,
                'label' => $studentType->getLabel() ?? $studentType->value,
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

        $courses = Course::query()
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn (Course $course): array => [
                'value' => (string) $course->id,
                'label' => "{$course->code} - {$course->title}",
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

        $yearLevels = collect([1, 2, 3, 4])
            ->map(fn (int $year): array => [
                'value' => (string) $year,
                'label' => "Year {$year}",
            ])
            ->all();

        $stats = [
            'total_students' => $globalStudentTotal,
            'total_enrolled' => StudentStatusRecord::query()
                ->where('academic_year', $currentSchoolYear)
                ->where('semester', $currentSemester)
                ->where('status', StudentStatus::Enrolled->value)
                ->count(),
            'total_applicants' => StudentStatusRecord::query()
                ->where('academic_year', $currentSchoolYear)
                ->where('semester', $currentSemester)
                ->where('status', StudentStatus::Applicant->value)
                ->count(),
            'total_graduated' => StudentStatusRecord::query()
                ->where('academic_year', $currentSchoolYear)
                ->where('semester', $currentSemester)
                ->where('status', StudentStatus::Graduated->value)
                ->count(),
        ];

        return Inertia::render('administrators/students/index', [
            'user' => $this->getUserProps(),
            'filament' => [
                'students' => [
                    'index_url' => route('filament.admin.resources.students.index'),
                    'create_url' => route('filament.admin.resources.students.create'),
                ],
            ],
            'students' => $students,
            'stats' => $stats,
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
                'trashed' => $trashedFilter,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'options' => [
                'types' => $types,
                'statuses' => $statuses,
                'courses' => $courses,
                'departments' => $departments,
                'year_levels' => $yearLevels,
                'scholarship_types' => $scholarshipTypes,
                'employment_statuses' => $employmentStatuses,
            ],
        ]);
    }

    public function show(Student $student): Response
    {
        $student->loadMissing([
            'Course',
            'studentContactsInfo',
            'studentParentInfo',
            'studentEducationInfo',
            'personalInfo',
            'DocumentLocation',
            'clearances',
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

                    // Determine primary enrollment. Priority: "Completed" (has a grade), or the latest one.
                    $enrolledSubject = $enrollments->firstWhere(fn ($e): bool => $e->grade !== null) ?? $enrollments->last();

                    $status = 'Not Completed';
                    $grade = '-';

                    if ($enrolledSubject) {
                        if ($enrolledSubject->grade) {
                            $status = 'Completed';
                            $grade = number_format((float) $enrolledSubject->grade, 2);
                        } else {
                            $status = 'In Progress';
                        }
                    }

                    $enrollmentsHistory = $enrollments->map(fn ($e): array => [
                        'id' => $e->id,
                        'enrollment_id' => $e->enrollment_id,
                        'grade' => $e->grade,
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
                        'code' => $subject->code,
                        'title' => $subject->title,
                        'units' => $subject->units,
                        'status' => $status,
                        'grade' => $enrolledSubject ? $enrolledSubject->grade : null,
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

        // Get Current Clearance
        $currentClearance = $student->getCurrentClearanceRecord()->first();
        $previousClearanceValidation = $student->validateEnrollmentClearance();

        // Get current enrollment and tuition (including trashed records)
        // $generalSettingsService and current years already initialized at top

        // Get current enrollment for the student (including soft-deleted)
        $currentEnrollment = StudentEnrollment::withTrashed()
            ->where('student_id', (string) $student->id)
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->with([
                'studentTuition' => function ($query): void {
                    $query->withTrashed();
                },
            ])
            ->first();

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
            ],
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
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
                'academic_year' => $student->formatted_academic_year,
                'course' => [
                    'id' => $student->Course?->id,
                    'code' => $student->Course?->code,
                    'title' => $student->Course?->title,
                ],
                'created_at' => format_timestamp($student->created_at),
                'updated_at' => format_timestamp($student->updated_at),
                'deleted_at' => $student->deleted_at ? format_timestamp($student->deleted_at) : null,
                'is_trashed' => $student->trashed(),
                'contacts' => $student->studentContactsInfo,
                'parents' => $student->studentParentInfo,
                'education' => $student->studentEducationInfo,
                'personal_info' => $student->personalInfo,
                'documents' => $student->DocumentLocation?->toResolvedDocumentArray(),
                'signature_url' => $this->resolveStoredFileUrl($student->signature_path),
                'current_clearance' => $currentClearance,
                'previous_clearance_validation' => $previousClearanceValidation,
                'clearance_history' => $student->clearances()->orderBy('created_at', 'desc')->get(),
                'tuition' => $tuitionData,
                'current_school_year' => $currentSchoolYear,
                'current_semester' => $currentSemester,
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

    public function create(): Response
    {
        return Inertia::render('administrators/students/create', [
            'user' => $this->getUserProps(),
            'options' => $this->getFormOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $identifierGenerator = app(IdentifierGenerator::class);

        if ($request->input('student_type') !== StudentType::SeniorHighSchool->value && blank($request->input('student_id'))) {
            $request->merge(['student_id' => $identifierGenerator->previewStudentId()]);
        }

        $validated = $request->validate([
            'student_type' => ['required', Rule::enum(StudentType::class)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female'],
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
            'student_id' => [
                Rule::requiredIf(fn (): bool => $request->student_type !== StudentType::SeniorHighSchool->value),
                'nullable',
                'numeric',
                'digits:6',
                'unique:students,student_id',
            ],
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
        ]);

        if (blank($validated['guardian_name'] ?? null) && filled($validated['emergency_contact_name'] ?? null)) {
            $validated['guardian_name'] = $validated['emergency_contact_name'];
        }

        if (blank($validated['guardian_contact'] ?? null) && filled($validated['emergency_contact_phone'] ?? null)) {
            $validated['guardian_contact'] = $validated['emergency_contact_phone'];
        }

        if (blank($validated['guardian_relationship'] ?? null) && filled($validated['emergency_contact_relationship'] ?? null)) {
            $validated['guardian_relationship'] = $validated['emergency_contact_relationship'];
        }

        $student = DB::transaction(function () use ($identifierGenerator, $validated): Student {
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

            if ($studentType === StudentType::SeniorHighSchool) {
                $student->lrn = $validated['lrn'];
                $student->student_id = $validated['lrn'];
                $student->shs_strand_id = $validated['shs_strand_id'];
            } else {
                $submittedStudentId = (int) $validated['student_id'];
                $student->student_id = $submittedStudentId === $identifierGenerator->previewStudentId()
                    ? $identifierGenerator->generateStudentId()
                    : $submittedStudentId;
                $student->course_id = $validated['course_id'];
            }

            $student->save();

            $this->syncStudentRelations($student, $validated);
            $this->syncCurrentStudentStatus($student, $status);

            return $student;
        });

        return match ($validated['submit_action'] ?? 'view') {
            'create_another' => to_route('administrators.students.create', status: 303)
                ->with('success', 'Student created successfully. You can now add another student.'),
            'create_enrollment' => to_route('administrators.enrollments.create', ['student_id' => $student->id], 303)
                ->with('success', 'Student created successfully. You can now create an enrollment.'),
            default => to_route('administrators.students.show', $student, 303)
                ->with('success', 'Student created successfully.'),
        };
    }

    public function edit(Student $student): Response
    {
        $student->loadMissing([
            'Course',
            'shsStrand',
            'studentContactsInfo',
            'studentParentInfo',
            'studentEducationInfo',
            'personalInfo',
            'subjectEnrolledCurrent.subject',
        ]);

        return Inertia::render('administrators/students/edit', [
            'user' => $this->getUserProps(),
            'student' => $student,
            'current_enrollments' => $student->subjectEnrolledCurrent()->with(['subject', 'class'])->get()->map(function ($enrollment): ?array {
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
            'current_classes' => $student->getCurrentClasses(),
            'options' => $this->getFormOptions(),
        ]);
    }

    public function generateId(Request $request, IdentifierGenerator $identifierGenerator): JsonResponse
    {
        $type = StudentType::tryFrom($request->query('type'));

        if (! $type) {
            return response()->json(['id' => null], 400);
        }

        if ($type === StudentType::SeniorHighSchool) {
            return response()->json(['id' => null]);
        }

        $nextId = $identifierGenerator->previewStudentId();

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

        $results = $columns
            ->flatMap(fn (string $column): array => DB::table($table)
                ->whereRaw('LOWER('.$column.') LIKE ?', [$like])
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->limit(15)
                ->pluck($column)
                ->all())
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

    public function update(Request $request, Student $student): RedirectResponse
    {
        $idWasGenerated = false;
        // If student_id is empty and not SHS, fallback to using the model's ID (primary key)
        if (empty($request->input('student_id')) && $request->input('student_type') !== StudentType::SeniorHighSchool->value) {
            $generatedId = mb_str_pad((string) $student->id, 6, '0', STR_PAD_LEFT);
            $request->merge(['student_id' => $generatedId]);
            $idWasGenerated = true;
        }

        $validated = $request->validate([
            'student_type' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female'],
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
            'student_id' => [
                Rule::requiredIf(fn (): bool => $request->student_type !== StudentType::SeniorHighSchool->value),
                'nullable',
                'numeric',
                'digits:6',
                Rule::unique('students', 'student_id')->ignore($student->id),
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
        ]);

        DB::transaction(function () use ($validated, $student): void {
            // Update main student record
            $student->student_type = $validated['student_type'];
            $student->first_name = $validated['first_name'];
            $student->last_name = $validated['last_name'];
            $student->middle_name = $validated['middle_name'];
            $student->suffix = $validated['suffix'] ?? null;
            $student->gender = $validated['gender'];
            $student->birth_date = $validated['birth_date'];
            $student->email = $validated['email'];
            $student->phone = $validated['phone'] ?? null;
            $student->civil_status = $validated['civil_status'] ?? null;
            $student->nationality = $validated['nationality'] ?? ($validated['citizenship'] ?? null);
            $student->religion = $validated['religion'] ?? null;
            $student->address = ($validated['current_address'] ?? null) ?: ($validated['permanent_address'] ?? null);
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
            $student->is_senior_citizen = $validated['is_senior_citizen'] ?? false;
            $student->is_magna_carta = $validated['is_magna_carta'] ?? false;
            $student->is_underprivileged = $validated['is_underprivileged'] ?? false;
            $student->is_first_generation = $validated['is_first_generation'] ?? false;
            $student->income_bracket_mode = $validated['income_bracket_mode'] ?? $student->income_bracket_mode ?? (string) config('income_brackets.default_mode', 'annual');
            $student->use_same_parent_income = $validated['use_same_parent_income'] ?? true;
            $student->family_income_bracket = $validated['family_income_bracket'] ?? null;
            $student->father_income_bracket = $validated['father_income_bracket'] ?? null;
            $student->mother_income_bracket = $validated['mother_income_bracket'] ?? null;
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
            if ($validated['student_type'] === StudentType::SeniorHighSchool->value) {
                $student->lrn = $validated['lrn'];
                $student->student_id = $validated['lrn']; // For SHS, LRN is used as ID
                $student->shs_strand_id = $validated['shs_strand_id'];
                $student->course_id = null;
            } else {
                $student->student_id = $validated['student_id'];
                $student->course_id = $validated['course_id'];
                $student->lrn = null;
                $student->shs_strand_id = null;
            }

            // Calculate Age
            $student->age = Carbon::parse($validated['birth_date'])->age;

            $student->save();

            $this->syncStudentRelations($student, $validated);

            if (isset($validated['status'])) {
                $this->syncCurrentStudentStatus($student, StudentStatus::from($validated['status']));
            }
        });

        $message = 'Student updated successfully.';
        if ($idWasGenerated) {
            $message .= " Student ID defaulted to {$validated['student_id']}.";
        }

        return redirect()->route('administrators.students.index')
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

    public function updateSubjectGrade(Request $request, Student $student, Subject $subject): RedirectResponse
    {
        $validated = $request->validate([
            'enrollment_record_id' => ['nullable', 'integer'],
            'is_new_record' => ['nullable', 'boolean'],
            'grade' => ['nullable', 'numeric', 'min:1', 'max:100'],
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
                ->where('subject_id', $subject->id)
                ->where('id', $enrollmentRecordId)
                ->first();
        } elseif (! $isNewRecord) {
            // Find latest existing enrollment
            $subjectEnrollment = SubjectEnrollment::where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->latest()
                ->first();
        } else {
            $subjectEnrollment = null;
        }

        $data = [
            'grade' => $validated['grade'],
            'remarks' => $validated['remarks'],
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

    public function updateStudentId(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'new_student_id' => ['required', 'integer', 'min:100000', 'max:999999'],
            'confirm_operation' => ['required', 'accepted'],
        ]);

        $newId = (int) $validated['new_student_id'];

        if ($newId === $student->student_id) {
            return back()->with('error', 'New ID cannot be the same as current ID.');
        }

        $service = app(StudentIdUpdateService::class);
        if (! $service->isIdAvailable($newId)) {
            return back()->with('error', "Student ID {$newId} already exists.");
        }

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
        $result = $service->undoStudentIdChange($validated['change_log_id']);

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
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'credits' => ['nullable', 'array'],
            'credits.*.source_subject_id' => ['required', 'exists:subjects,id'],
            'credits.*.target_subject_id' => ['required', 'exists:subjects,id'],
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

    public function updateSignature(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'file', 'mimetypes:image/png', 'max:2048'],
        ]);

        /** @var UploadedFile $signature */
        $signature = $validated['signature'];

        $disk = config('filesystems.default');
        $newPath = $signature->store("students/{$student->id}/signatures");

        if (! is_string($newPath) || $newPath === '') {
            return back()->with('error', 'Failed to store signature.');
        }

        $oldPath = $student->signature_path;
        if (
            is_string($oldPath) &&
            $oldPath !== '' &&
            ! filter_var($oldPath, FILTER_VALIDATE_URL) &&
            ! str_starts_with($oldPath, '/') &&
            is_string($disk) &&
            Storage::disk($disk)->exists($oldPath)
        ) {
            Storage::disk($disk)->delete($oldPath);
        }

        $student->update([
            'signature_path' => $newPath,
        ]);

        $student->refresh();

        return back()->with([
            'success' => 'Student signature saved successfully.',
            'signature_url' => $this->resolveStoredFileUrl($student->signature_path),
        ]);
    }

    public function manageClearance(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'is_cleared' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'academic_year' => ['nullable', 'string'],
            'semester' => ['nullable', 'integer'],
            'cleared_at' => ['nullable', 'date'],
        ]);

        $settings = GeneralSetting::first();

        $academicYear = $validated['academic_year'] ?? $settings->getSchoolYearString();
        $semester = $validated['semester'] ?? $settings->getSemester();

        $clearance = StudentClearance::firstOrCreate(
            [
                'student_id' => $student->id,
                'academic_year' => $academicYear,
                'semester' => $semester,
            ],
            ['is_cleared' => false]
        );

        $isCleared = $validated['is_cleared'];
        $user = Auth::user();
        $clearedBy = $user ? $user->name : 'System';

        $updateData = [
            'is_cleared' => $isCleared,
            'remarks' => $validated['remarks'],
        ];

        if ($isCleared) {
            $updateData['cleared_by'] = $clearedBy;
            $updateData['cleared_at'] = $validated['cleared_at'] ?? now();
        }

        $clearance->update($updateData);

        return back()->with('success', 'Clearance updated successfully.');
    }

    public function updateStatus(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', new \Illuminate\Validation\Rules\Enum(StudentStatus::class)],
        ]);

        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

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

        $student->update(['status' => $validated['status']]);

        return back()->with('success', 'Student status updated successfully.');
    }

    public function bulkUpdateStatus(BulkUpdateStudentStatusRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $validated['student_ids'];
        $status = $validated['status'];

        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $students = Student::query()->whereIn('id', $studentIds)->get();
        $updatedCount = 0;

        foreach ($students as $student) {
            StudentStatusRecord::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year' => $currentSchoolYear,
                    'semester' => $currentSemester,
                ],
                [
                    'status' => $status,
                ]
            );
            $student->update(['status' => $status]);
            $updatedCount++;
        }

        return back()->with('success', "Updated status for {$updatedCount} student(s).");
    }

    public function bulkManageClearance(BulkUpdateStudentClearanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $validated['student_ids'];
        $isCleared = $validated['is_cleared'];
        $remarks = $validated['remarks'] ?? null;

        $generalSettingsService = app(GeneralSettingsService::class);
        $academicYear = $generalSettingsService->getCurrentSchoolYearString();
        $semester = $generalSettingsService->getCurrentSemester();

        $user = Auth::user();
        $clearedBy = $user ? $user->name : 'System';
        $updatedCount = 0;

        foreach (Student::query()->whereIn('id', $studentIds)->get() as $student) {
            $clearance = StudentClearance::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                ],
                ['is_cleared' => false]
            );

            $updateData = [
                'is_cleared' => $isCleared,
                'remarks' => $remarks,
            ];

            if ($isCleared) {
                $updateData['cleared_by'] = $clearedBy;
                $updateData['cleared_at'] = now();
            }

            $clearance->update($updateData);
            $updatedCount++;
        }

        return back()->with('success', "Updated clearance for {$updatedCount} student(s).");
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

        foreach (Student::query()->whereIn('id', $studentIds)->get() as $student) {
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

        $message = "Sent {$sentCount} email(s).";
        if ($skippedCount > 0) {
            $message .= " Skipped {$skippedCount} student(s) without an email address.";
        }

        return back()->with('success', $message);
    }

    public function bulkDestroy(BulkDeleteStudentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $studentIds = $validated['student_ids'];

        $deletedCount = 0;

        foreach (Student::query()->whereIn('id', $studentIds)->get() as $student) {
            $student->delete();
            $deletedCount++;
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

        $deletedCount = 0;
        $failures = [];

        foreach (Student::withTrashed()->whereIn('id', $studentIds)->get() as $student) {
            try {
                DB::transaction(function () use ($student): void {
                    $studentId = $student->id;

                    $student->subjectEnrolled()->forceDelete();
                    $student->clearances()->forceDelete();
                    $student->classEnrollments()->forceDelete();
                    $student->StudentTuition()->forceDelete();
                    $student->StudentTransactions()->forceDelete();

                    StudentEnrollment::where('student_id', $studentId)->forceDelete();
                    StudentStatusRecord::where('student_id', $studentId)->forceDelete();

                    if (Schema::hasTable('class_attendance_records')) {
                        ClassAttendanceRecord::where('student_id', $studentId)->delete();
                    }

                    if ($student->lrn && Schema::hasTable('shs_students')) {
                        ShsStudent::where('student_lrn', $student->lrn)->forceDelete();
                    }

                    $student->forceDelete();
                });
                $deletedCount++;
            } catch (Exception $e) {
                $failures[] = $student->full_name.' ('.$e->getMessage().')';
            }
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
        $studentName = $student->full_name;

        $student->delete();

        return back()->with('success', "Student \"{$studentName}\" has been deleted.");
    }

    /**
     * Restore a soft-deleted student.
     */
    public function restore(int $student): RedirectResponse
    {
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
        $studentModel = Student::withTrashed()->findOrFail($student);

        $studentName = $studentModel->full_name;

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

        if ($student->isDirty([
            'student_contact_id',
            'student_parent_info',
            'student_education_id',
            'student_personal_id',
        ])) {
            $student->save();
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

        if ($id !== null) {
            if ($attributes !== []) {
                if (Schema::hasColumn($table, 'updated_at')) {
                    $attributes['updated_at'] = now();
                }

                DB::table($table)
                    ->where('id', $id)
                    ->update($attributes);
            }

            return $id;
        }

        $attributes = $this->withoutBlankValues($attributes);

        if ($attributes === []) {
            return null;
        }

        if (Schema::hasColumn($table, 'created_at')) {
            $attributes['created_at'] = now();
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $attributes['updated_at'] = now();
        }

        return (int) DB::table($table)->insertGetId($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function existingColumnAttributes(string $table, array $attributes): array
    {
        $filtered = [];

        foreach ($attributes as $key => $value) {
            if (! Schema::hasColumn($table, (string) $key)) {
                continue;
            }

            $filtered[(string) $key] = $value;
        }

        return $filtered;
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

    private function getFormOptions(): array
    {
        return [
            'types' => collect(StudentType::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'statuses' => collect(StudentStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'scholarship_types' => collect(ScholarshipType::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'employment_statuses' => collect(EmploymentStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'attrition_categories' => collect(AttritionCategory::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'courses' => Course::with('department:id,code,name,is_active')
                ->get(['id', 'code', 'title', 'is_active', 'department_id'])
                ->sortBy([['is_active', 'desc'], ['code', 'asc']])
                ->groupBy(fn (Course $course): string => $course->department?->code ?? 'UNASSIGNED')
                ->sortKeys()
                ->map(function ($courses, string $departmentCode): array {
                    $department = $courses->first()->department;
                    $label = $department
                        ? "{$department->code} — {$department->name}".($department->is_active ? '' : ' (Inactive)')
                        : 'Unassigned';

                    return [
                        'label' => $label,
                        'items' => $courses->map(fn (Course $c): array => [
                            'value' => $c->id,
                            'label' => $c->code.' - '.$c->title.($c->is_active ? '' : ' (Inactive)'),
                            'is_active' => $c->is_active,
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
            'subjects' => Subject::all(['id', 'code', 'title', 'units'])->map(fn ($s): array => ['value' => $s->id, 'label' => "{$s->code} - {$s->title} ({$s->units} units)"])->all(),
        ];
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
