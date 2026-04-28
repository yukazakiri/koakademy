<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttritionCategory;
use App\Enums\EmploymentStatus;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Enums\SubjectEnrolledEnum;
use App\Http\Requests\Administrators\BulkDeleteStudentRequest;
use App\Http\Requests\Administrators\BulkEmailStudentsRequest;
use App\Http\Requests\Administrators\BulkUpdateStudentClearanceRequest;
use App\Http\Requests\Administrators\BulkUpdateStudentStatusRequest;
use App\Jobs\GenerateStudentSoaPdfJob;
use App\Mail\StudentBulkMessage;
use App\Models\Account;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\ShsStrand;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\StudentStatusRecord;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\GeneralSettingsService;
use App\Services\StudentIdUpdateService;
use App\Settings\SiteSettings;
use Exception;
use FPDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
        $scholarshipType = $request->input('scholarship_type');
        $employmentStatus = $request->input('employment_status');
        $isIndigenousPerson = $request->input('is_indigenous_person');
        $regionOfOrigin = $request->input('region_of_origin');
        $previousSemesterCleared = $request->input('previous_semester_cleared');

        $studentsQuery = Student::query()
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
                $query = mb_trim($search);

                $builder->where(function ($nested) use ($query): void {
                    $nested->whereRaw('CAST(student_id AS TEXT) ILIKE ?', ["%{$query}%"])
                        ->orWhere('first_name', 'ilike', "%{$query}%")
                        ->orWhere('last_name', 'ilike', "%{$query}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$query}%"])
                        ->orWhereRaw("CONCAT(last_name, ', ', first_name) ILIKE ?", ["%{$query}%"]);
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

        $sort = $request->input('sort');
        $direction = $request->input('direction', 'asc');

        if ($sort) {
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
            } elseif (in_array($sort, ['academic_year', 'created_at', 'age', 'gender'])) {
                // Check if column exists to avoid SQL errors
                // White list allowed columns
                $studentsQuery->orderBy($sort, $direction);
            }
        } else {
            $studentsQuery->orderBy('last_name')
                ->orderBy('first_name');
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
                'course' => $student->Course?->code,
                'course_title' => $student->Course?->title,
                'academic_year' => $student->formatted_academic_year,
                'type' => $studentType instanceof StudentType ? $studentType->value : (is_string($studentType) ? $studentType : null),
                'status' => $currentStatus instanceof StudentStatus ? $currentStatus->value : (is_string($currentStatus) ? $currentStatus : null),
                'scholarship_type' => $scholarshipType instanceof ScholarshipType ? $scholarshipType->getLabel() : ($scholarshipType ?? 'None'),
                'employment_status' => $employmentStatus instanceof EmploymentStatus ? $employmentStatus->getLabel() : ($employmentStatus ?? 'N/A'),
                'is_indigenous_person' => $student->is_indigenous_person,
                'region_of_origin' => $student->region_of_origin,
                'previous_sem_clearance' => $currentClearanceStatus,
                'avatar_url' => $student->picture1x1 !== '' ? $student->picture1x1 : null,
                'created_at' => format_timestamp($student->created_at),
                'filament' => [
                    'view_url' => route('filament.admin.resources.students.view', $student),
                    'edit_url' => route('filament.admin.resources.students.edit', $student),
                ],
            ];
        });

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

        $stats = [
            'total_students' => $hasActiveFilters ? Student::count() : $students->total(),
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
                'scholarship_type' => $scholarshipType,
                'employment_status' => $employmentStatus,
                'is_indigenous_person' => $isIndigenousPerson,
                'region_of_origin' => $regionOfOrigin,
                'previous_semester_cleared' => $previousSemesterCleared,
                'per_page' => $perPage,
            ],
            'options' => [
                'types' => $types,
                'statuses' => $statuses,
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
        $validated = $request->validate([
            'student_type' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'string', 'in:male,female'],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
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
            'remarks' => ['nullable', 'string'],

            // Additional info
            'personal_contact' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'fathers_name' => ['nullable', 'string', 'max:100'],
            'mothers_name' => ['nullable', 'string', 'max:100'],
            'current_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated): void {
            // Create main student record
            $student = new Student();
            $student->student_type = $validated['student_type'];
            $student->first_name = $validated['first_name'];
            $student->last_name = $validated['last_name'];
            $student->middle_name = $validated['middle_name'];
            $student->gender = $validated['gender'];
            $student->birth_date = $validated['birth_date'];
            $student->email = $validated['email'];
            $student->academic_year = $validated['academic_year'];
            $student->remarks = $validated['remarks'];
            $student->status = StudentStatus::Enrolled; // Default status

            // Handle Type Specifics
            if ($validated['student_type'] === StudentType::SeniorHighSchool->value) {
                $student->lrn = $validated['lrn'];
                $student->student_id = $validated['lrn']; // For SHS, LRN is used as ID
                $student->shs_strand_id = $validated['shs_strand_id'];
            } else {
                $student->student_id = $validated['student_id'];
                $student->course_id = $validated['course_id'];
            }

            // Calculate Age
            $student->age = \Carbon\Carbon::parse($validated['birth_date'])->age;

            $student->save();

            $generalSettingsService = app(GeneralSettingsService::class);
            StudentStatusRecord::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year' => $generalSettingsService->getCurrentSchoolYearString(),
                    'semester' => $generalSettingsService->getCurrentSemester(),
                ],
                [
                    'status' => $student->status,
                ]
            );

            $generalSettingsService = app(GeneralSettingsService::class);
            StudentStatusRecord::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year' => $generalSettingsService->getCurrentSchoolYearString(),
                    'semester' => $generalSettingsService->getCurrentSemester(),
                ],
                [
                    'status' => $student->status,
                ]
            );

            // Create Relations
            $student->studentContactsInfo()->create([
                'personal_contact' => $validated['personal_contact'],
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'],
            ]);

            $student->studentParentInfo()->create([
                'fathers_name' => $validated['fathers_name'],
                'mothers_name' => $validated['mothers_name'],
            ]);

            $student->personalInfo()->create([
                'current_adress' => $validated['current_address'],
                'permanent_address' => $validated['permanent_address'],
            ]);

            // Note: Other relations like Education, DocumentLocation created empty or on demand by model events if needed,
            // or we could explicitly create them here if the form had fields for them.
            // Based on the form I built, these are the fields we have.
        });

        return redirect()->route('administrators.students.index')
            ->with('success', 'Student created successfully.');
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

    public function generateId(Request $request): JsonResponse
    {
        $type = StudentType::tryFrom($request->query('type'));

        if (! $type) {
            return response()->json(['id' => null], 400);
        }

        $nextId = Student::generateNextId($type);

        return response()->json(['id' => $nextId]);
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
            'gender' => ['required', 'string', 'in:male,female'],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
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

            // Parent Info
            'fathers_name' => ['nullable', 'string', 'max:100'],
            'mothers_name' => ['nullable', 'string', 'max:100'],

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

            // Address & Personal Info
            'current_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric'],
            'height' => ['nullable', 'string', 'max:20'],

            // Statistical Data
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'city_of_origin' => ['nullable', 'string', 'max:100'],
            'province_of_origin' => ['nullable', 'string', 'max:100'],
            'region_of_origin' => ['nullable', 'string', 'max:50'],
            'is_indigenous_person' => ['nullable', 'boolean'],
            'indigenous_group' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'withdrawal_date' => ['nullable', 'date'],
            'withdrawal_reason' => ['nullable', 'string'],
            'attrition_category' => ['nullable', 'string'],
            'dropout_date' => ['nullable', 'date'],
            'scholarship_type' => ['nullable', 'string'],
            'scholarship_details' => ['nullable', 'string'],
            'employment_status' => ['nullable', 'string'],
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
            $student->gender = $validated['gender'];
            $student->birth_date = $validated['birth_date'];
            $student->email = $validated['email'];
            $student->academic_year = $validated['academic_year'];
            $student->remarks = $validated['remarks'];

            // Statistical Data
            $student->ethnicity = $validated['ethnicity'] ?? null;
            $student->city_of_origin = $validated['city_of_origin'] ?? null;
            $student->province_of_origin = $validated['province_of_origin'] ?? null;
            $student->region_of_origin = $validated['region_of_origin'] ?? null;
            $student->is_indigenous_person = $validated['is_indigenous_person'] ?? false;
            $student->indigenous_group = $validated['indigenous_group'] ?? null;
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
            $student->age = \Carbon\Carbon::parse($validated['birth_date'])->age;

            $student->save();

            $contactData = [
                'personal_contact' => $validated['personal_contact'],
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'],
                'emergency_contact_address' => $validated['emergency_contact_address'] ?? null,
            ];

            if ($student->studentContactsInfo !== null) {
                $student->studentContactsInfo->update($contactData);
            } else {
                $studentContact = \App\Models\StudentContact::query()->create($contactData);
                $student->student_contact_id = $studentContact->id;
            }

            $parentData = [
                'father_name' => $validated['fathers_name'],
                'mother_name' => $validated['mothers_name'],
            ];

            if ($student->studentParentInfo !== null) {
                $student->studentParentInfo->update($parentData);
            } else {
                $studentParentInfo = \App\Models\StudentParentsInfo::query()->create($parentData);
                $student->student_parent_info = $studentParentInfo->id;
            }

            $educationData = [
                'elementary_school' => $validated['elementary_school'] ?? null,
                'elementary_year_graduated' => $validated['elementary_graduate_year'] ?? null,
                'high_school' => $validated['junior_high_school_name'] ?? null,
                'high_school_year_graduated' => $validated['junior_high_graduation_year'] ?? null,
                'senior_high_school' => $validated['senior_high_name'] ?? null,
                'senior_high_year_graduated' => $validated['senior_high_graduate_year'] ?? null,
            ];

            if ($student->studentEducationInfo !== null) {
                $student->studentEducationInfo->update($educationData);
            } else {
                $studentEducationInfo = \App\Models\StudentEducationInfo::query()->create($educationData);
                $student->student_education_id = $studentEducationInfo->id;
            }

            $personalInfoData = [
                'current_adress' => $validated['current_address'],
                'permanent_address' => $validated['permanent_address'],
                'birthplace' => $validated['birthplace'] ?? null,
                'civil_status' => $validated['civil_status'] ?? null,
                'citizenship' => $validated['citizenship'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'height' => $validated['height'] ?? null,
            ];

            if ($student->personalInfo !== null) {
                $student->personalInfo->update($personalInfoData);
            } else {
                $studentPersonalInfo = \App\Models\StudentsPersonalInfo::query()->create($personalInfoData);
                $student->student_personal_id = $studentPersonalInfo->id;
            }

            if ($student->isDirty([
                'student_contact_id',
                'student_parent_info',
                'student_education_id',
                'student_personal_id',
            ])) {
                $student->save();
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
        $validated = $request->validate([
            'total_lectures' => ['required', 'numeric', 'min:0'],
            'total_laboratory' => ['required', 'numeric', 'min:0'],
            'total_miscelaneous_fees' => ['required', 'numeric', 'min:0'],
            'downpayment' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tuition = $student->getOrCreateCurrentTuition();

        $totalTuition = (float) $validated['total_lectures'] + (float) $validated['total_laboratory'];
        $overallTuition = $totalTuition + (float) $validated['total_miscelaneous_fees'];

        $discountAmount = $overallTuition * ((float) $validated['discount'] / 100);
        $overallTuitionAfterDiscount = $overallTuition - $discountAmount;

        $totalBalance = $overallTuitionAfterDiscount - (float) $validated['downpayment'];

        $tuition->update([
            'total_lectures' => $validated['total_lectures'],
            'total_laboratory' => $validated['total_laboratory'],
            'total_miscelaneous_fees' => $validated['total_miscelaneous_fees'],
            'total_tuition' => $totalTuition,
            'overall_tuition' => $overallTuitionAfterDiscount,
            'downpayment' => $validated['downpayment'],
            'discount' => $validated['discount'],
            'total_balance' => $totalBalance,
            'status' => $totalBalance <= 0 ? 'paid' : 'pending',
        ]);

        return back()->with('success', 'Tuition updated successfully.');
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
     * Permanently delete a student (applicant) and all related records.
     */
    public function forceDestroy(Request $request, int $student): RedirectResponse
    {
        // Use withTrashed to find soft-deleted students too
        $studentModel = Student::withTrashed()->findOrFail($student);

        $studentName = $studentModel->full_name;

        try {
            DB::transaction(function () use ($studentModel): void {
                // Delete related records first
                $studentModel->subjectEnrollments()->forceDelete();
                $studentModel->clearances()->delete();
                $studentModel->classEnrollments()->delete();

                // Delete student enrollments
                StudentEnrollment::where('student_id', $studentModel->id)->forceDelete();

                // Force delete the student
                $studentModel->forceDelete();
            });

            return redirect()
                ->route('administrators.enrollments.index')
                ->with('success', "Student \"{$studentName}\" has been permanently deleted.");
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete student: '.$e->getMessage());
        }
    }

    /**
     * @param  array{
     *     student: array{id:int,student_no:int|string,name:string,email:?string,course:string},
     *     tuition: ?StudentTuition,
     *     transactions: \Illuminate\Support\Collection<int, array{id:int,date:?string,description:string,amount:int|float|string,status:?string,invoice:?string,method:?string}>,
     *     filters: array{semester:int,school_year:string},
     *     school: array{name:string,address:string,logo:string,favicon:string,tagline:string},
     *     generated_at: string,
     *     currency_code: string,
     *     currency_symbol: string
     * }  $data
     */
    private function generateStudentSoaPdfFallback(array $data): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $student = $data['student'];
        $tuition = $data['tuition'];
        $transactions = $data['transactions'];
        $filters = $data['filters'];
        $school = $data['school'];
        $currencySymbol = $data['currency_symbol'] ?? '₱';
        $currencyCode = $data['currency_code'] ?? 'PHP';
        $currencyPrefix = preg_match('/^[\x20-\x7E]+$/', (string) $currencySymbol) === 1
            ? (string) $currencySymbol
            : ((string) $currencyCode).' ';

        $temporaryImagePaths = [];

        $formatMoney = fn (int|float|string|null $amount): string => $currencyPrefix.number_format((float) $amount, 2);

        $assessmentTotal = (float) ($tuition?->overall_tuition ?? 0);
        $ledgerBalance = (float) ($tuition?->total_balance ?? 0);
        $paymentHistoryTotal = $transactions->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
        $ledgerPaid = $tuition ? max(0, $assessmentTotal - $ledgerBalance) : $paymentHistoryTotal;
        $totalPayments = $tuition ? $ledgerPaid : $paymentHistoryTotal;
        $balanceDue = $tuition ? $ledgerBalance : max(0, $assessmentTotal - $paymentHistoryTotal);
        $semesterLabel = ((int) $filters['semester']) === 1 ? '1st Semester' : (((int) $filters['semester']) === 2 ? '2nd Semester' : 'Semester '.$filters['semester']);

        $logoPath = $this->resolveSoaPdfImagePath($school['logo'] ?? null, $temporaryImagePaths);
        if (is_string($logoPath)) {
            $pdf->Image($logoPath, 12, 10, 16, 16);
        }

        $faviconPath = $this->resolveSoaPdfImagePath($school['favicon'] ?? null, $temporaryImagePaths);
        if (is_string($faviconPath)) {
            $pdf->Image($faviconPath, 186, 10, 8, 8);
        }

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell(0, 4, $this->soaPdfText('Republic of the Philippines'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 15);
        $pdf->Cell(0, 7, $this->soaPdfText((string) ($school['name'] ?? 'KoAkademy')), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        if (is_string($school['tagline'] ?? null) && mb_trim((string) $school['tagline']) !== '') {
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(0, 5, $this->soaPdfText((string) $school['tagline']), 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Cell(0, 5, $this->soaPdfText((string) ($school['address'] ?? '')), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 6, $this->soaPdfText('Statement of Account'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 5, $this->soaPdfText('Generated: '.($data['generated_at'] ?? '')), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(242, 242, 242);
        $pdf->Cell(95, 6, $this->soaPdfText('Control No.: SOA-'.(string) ($student['student_no'] ?? $student['id'] ?? 'N/A').'-'.preg_replace('/\s|-/u', '', (string) ($filters['school_year'] ?? '')).'-'.(string) ($filters['semester'] ?? '')), 1, 0, 'L', true);
        $pdf->Cell(95, 6, $this->soaPdfText('School Year / Term: '.(string) ($filters['school_year'] ?? '').' - '.$semesterLabel), 1, 1, 'L', true);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(229, 229, 229);
        $pdf->Cell(190, 6, $this->soaPdfText('STUDENT INFORMATION'), 1, 1, 'L', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(38, 6, $this->soaPdfText('Student No.'), 1, 0);
        $pdf->Cell(57, 6, $this->soaPdfText(mb_strimwidth((string) ($student['student_no'] ?? $student['id'] ?? 'N/A'), 0, 26, '...')), 1, 0);
        $pdf->Cell(38, 6, $this->soaPdfText('Student Name'), 1, 0);
        $pdf->Cell(57, 6, $this->soaPdfText(mb_strimwidth((string) ($student['name'] ?? 'N/A'), 0, 38, '...')), 1, 1);

        $pdf->Cell(38, 6, $this->soaPdfText('Course'), 1, 0);
        $pdf->Cell(57, 6, $this->soaPdfText(mb_strimwidth((string) ($student['course'] ?? 'N/A'), 0, 34, '...')), 1, 0);
        $pdf->Cell(38, 6, $this->soaPdfText('Term'), 1, 0);
        $pdf->Cell(57, 6, $this->soaPdfText(mb_strimwidth($semesterLabel.', A.Y. '.((string) ($filters['school_year'] ?? '')), 0, 34, '...')), 1, 1);
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(229, 229, 229);
        $pdf->Cell(190, 6, $this->soaPdfText('ACCOUNT SUMMARY'), 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 9);

        $pdf->Cell(100, 6, $this->soaPdfText('Total Assessment'), 1, 0);
        $pdf->Cell(90, 6, $this->soaPdfText($formatMoney($assessmentTotal)), 1, 1, 'R');
        $pdf->Cell(100, 6, $this->soaPdfText('Total Payments'), 1, 0);
        $pdf->Cell(90, 6, $this->soaPdfText($formatMoney($totalPayments)), 1, 1, 'R');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(100, 7, $this->soaPdfText('BALANCE DUE'), 1, 0);
        $pdf->Cell(90, 7, $this->soaPdfText($formatMoney($balanceDue)), 1, 1, 'R', true);
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(229, 229, 229);
        $pdf->Cell(190, 6, $this->soaPdfText('PAYMENT HISTORY'), 1, 1, 'L', true);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(242, 242, 242);
        $pdf->Cell(30, 6, $this->soaPdfText('Date'), 1, 0);
        $pdf->Cell(35, 6, $this->soaPdfText('OR No.'), 1, 0);
        $pdf->Cell(90, 6, $this->soaPdfText('Particulars'), 1, 0);
        $pdf->Cell(35, 6, $this->soaPdfText('Amount'), 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 8);
        $rows = $transactions->take(18);
        if ($rows->isEmpty()) {
            $pdf->Cell(190, 6, $this->soaPdfText('No payment records found for the selected term.'), 1, 1, 'C');
        } else {
            foreach ($rows as $row) {
                if ($pdf->GetY() > 265) {
                    $pdf->AddPage();
                }

                $pdf->Cell(30, 6, $this->soaPdfText((string) ($row['date'] ?? '—')), 1, 0);
                $pdf->Cell(35, 6, $this->soaPdfText((string) ($row['invoice'] ?? '-')), 1, 0);
                $pdf->Cell(90, 6, $this->soaPdfText((string) ($row['description'] ?? 'Tuition Payment')), 1, 0);
                $pdf->Cell(35, 6, $this->soaPdfText($formatMoney($row['amount'] ?? 0)), 1, 1, 'R');
            }
        }

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(155, 6, $this->soaPdfText('PAYMENT HISTORY TOTAL'), 1, 0);
        $pdf->Cell(35, 6, $this->soaPdfText($formatMoney($paymentHistoryTotal)), 1, 1, 'R', true);

        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, $this->soaPdfText('Prepared by: ____________________'), 0, 1, 'L');
        $pdf->Cell(0, 5, $this->soaPdfText('Verified by: ____________________'), 0, 1, 'L');
        $pdf->Ln(2);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->MultiCell(0, 5, $this->soaPdfText('This is a system-generated official document.'), 0, 'R');
        $pdf->SetTextColor(0, 0, 0);

        try {
            /** @var string|bool $pdfOutput */
            $pdfOutput = $pdf->Output('S');
            if (! is_string($pdfOutput)) {
                throw new Exception('FPDF failed to generate SOA output.');
            }

            return $pdfOutput;
        } finally {
            foreach ($temporaryImagePaths as $temporaryImagePath) {
                if (is_string($temporaryImagePath) && file_exists($temporaryImagePath)) {
                    @unlink($temporaryImagePath);
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $temporaryImagePaths
     */
    private function resolveSoaPdfImagePath(mixed $imageValue, array &$temporaryImagePaths): ?string
    {
        if (! is_string($imageValue) || mb_trim($imageValue) === '') {
            return null;
        }

        if (str_starts_with($imageValue, '/')) {
            $publicPath = public_path(mb_ltrim($imageValue, '/'));

            return is_file($publicPath) ? $publicPath : null;
        }

        if (filter_var($imageValue, FILTER_VALIDATE_URL)) {
            try {
                $imageContent = @file_get_contents($imageValue);
                if (! is_string($imageContent) || $imageContent === '') {
                    return null;
                }

                /** @var array{mime?:string}|false $imageInfo */
                $imageInfo = @getimagesizefromstring($imageContent);
                $extension = match ($imageInfo['mime'] ?? null) {
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    default => null,
                };

                if (! is_string($extension)) {
                    return null;
                }

                $tempBaseFile = tempnam(sys_get_temp_dir(), 'soa_img_');
                if ($tempBaseFile === false) {
                    return null;
                }

                $tempFile = $tempBaseFile.'.'.$extension;
                if (! @rename($tempBaseFile, $tempFile)) {
                    @unlink($tempBaseFile);

                    return null;
                }

                file_put_contents($tempFile, $imageContent);
                $temporaryImagePaths[] = $tempFile;

                return $tempFile;
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function soaPdfText(string $value): string
    {
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }

    private function getFormOptions(): array
    {
        return [
            'types' => collect(StudentType::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'statuses' => collect(StudentStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'scholarship_types' => collect(ScholarshipType::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'employment_statuses' => collect(EmploymentStatus::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'attrition_categories' => collect(AttritionCategory::cases())->map(fn ($s): array => ['value' => $s->value, 'label' => $s->getLabel()])->values()->all(),
            'courses' => Course::all(['id', 'code', 'title', 'is_active'])
                ->sortBy([['is_active', 'desc'], ['code', 'asc']])
                ->map(fn ($c): array => [
                    'value' => $c->id,
                    'label' => $c->code.' - '.$c->title.($c->is_active ? '' : ' (Inactive)'),
                    'is_active' => $c->is_active,
                ])
                ->values()
                ->all(),
            'shs_strands' => ShsStrand::all(['id', 'strand_name'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->strand_name])->all(),
            'regions' => $this->getPhilippineRegions(),
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
