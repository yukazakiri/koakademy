<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Http\Requests\StoreEnrollmentRegistrationRequest;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\OnboardingFeature;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Services\EnrollmentService;
use App\Services\GeneralSettingsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class EnrollmentRegistrationController extends Controller
{
    public function create(): Response
    {
        $collegeEnabled = OnboardingFeature::query()
            ->where('feature_key', 'online-college-enrollment')
            ->where('is_active', true)
            ->value('is_active') ?? false;

        $tesdaEnabled = OnboardingFeature::query()
            ->where('feature_key', 'online-tesda-enrollment')
            ->where('is_active', true)
            ->value('is_active') ?? false;

        if (! $collegeEnabled && ! $tesdaEnabled) {
            return Inertia::render('enrollment/closed', [
                'message' => 'Online enrollment is currently unavailable. Please check back later or visit the registrar\'s office.',
            ]);
        }

        $courses = Course::query()
            ->where('is_active', true)
            ->with('department')
            ->when(! $tesdaEnabled, fn ($q) => $q->whereHas('department', fn ($q) => $q->whereRaw('UPPER(TRIM(code)) != ?', ['TESDA'])))
            ->when(! $collegeEnabled, fn ($q) => $q->whereHas('department', fn ($q) => $q->whereRaw('UPPER(TRIM(code)) = ?', ['TESDA'])))
            ->orderBy('title')
            ->get();

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Department $dept): array => [
                'code' => $dept->code,
                'label' => $dept->name,
            ])
            ->values()
            ->all();

        return Inertia::render('enrollment/index', [
            'departments' => $departments,
            'courses' => $courses->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'department' => $course->department?->code,
                'description' => $course->description,
            ])->all(),
            'flash' => session('flash'),
            'college_enrollment_enabled' => $collegeEnabled,
            'tesda_enrollment_enabled' => $tesdaEnabled,
        ]);
    }

    public function store(StoreEnrollmentRegistrationRequest $request, GeneralSettingsService $settings): RedirectResponse
    {
        $payload = $request->validated();
        $studentTypeValue = $payload['student_type'] ?? '';

        // Check feature flags before allowing submission
        if ($studentTypeValue === 'college' && ! OnboardingFeature::query()
            ->where('feature_key', 'online-college-enrollment')
            ->where('is_active', true)
            ->exists()) {
            return redirect()->back()->with('flash', [
                'error' => 'College online registration is currently unavailable.',
            ]);
        }

        if ($studentTypeValue === 'tesda' && ! OnboardingFeature::query()
            ->where('feature_key', 'online-tesda-enrollment')
            ->where('is_active', true)
            ->exists()) {
            return redirect()->back()->with('flash', [
                'error' => 'TESDA online registration is currently unavailable.',
            ]);
        }

        $studentType = match ($payload['student_type']) {
            'tesda' => StudentType::TESDA,
            default => StudentType::College,
        };

        $courseId = (int) $payload['course_id'];
        $course = Course::query()->findOrFail($courseId);

        if ($studentType === StudentType::TESDA && mb_strtoupper(mb_trim((string) ($course->department?->code ?? ''))) !== 'TESDA') {
            return redirect()->back()->with('flash', [
                'error' => 'TESDA applicants must select a TESDA course/program.',
            ]);
        }

        $birthDate = Carbon::parse($payload['birth_date']);
        $academicYear = isset($payload['academic_year']) && $payload['academic_year'] !== '' ? (int) $payload['academic_year'] : null;

        // Guard against duplicate student records. An applicant is considered a
        // duplicate if an existing Student matches by email OR by the tuple
        // (first_name, last_name, birth_date). This catches cases where someone
        // bypasses the returning-student flow and re-registers as "new".
        $normalizedEmail = isset($payload['email']) ? Str::lower(mb_trim((string) $payload['email'])) : null;
        $normalizedFirst = mb_strtolower(mb_trim((string) $payload['first_name']));
        $normalizedLast = mb_strtolower(mb_trim((string) $payload['last_name']));

        $duplicate = Student::query()
            ->where(function ($query) use ($normalizedEmail, $normalizedFirst, $normalizedLast, $birthDate): void {
                if ($normalizedEmail !== null && $normalizedEmail !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$normalizedEmail]);
                }
                $query->orWhere(function ($q) use ($normalizedFirst, $normalizedLast, $birthDate): void {
                    $q->whereRaw('LOWER(first_name) = ?', [$normalizedFirst])
                        ->whereRaw('LOWER(last_name) = ?', [$normalizedLast])
                        ->whereDate('birth_date', $birthDate->toDateString());
                });
            })
            ->first();

        if ($duplicate instanceof Student) {
            $reason = ($normalizedEmail !== null && $normalizedEmail !== '' && Str::lower((string) $duplicate->email) === $normalizedEmail)
                ? 'email address'
                : 'name and date of birth';

            return redirect()->back()->with('flash', [
                'error' => sprintf(
                    'A student record with the same %s already exists (ID: %s). Please use the returning-student lookup above instead of registering again.',
                    $reason,
                    (string) $duplicate->student_id
                ),
            ]);
        }

        /** @var Student $student */
        $student = DB::transaction(function () use ($request, $payload, $studentType, $birthDate, $courseId, $academicYear): Student {
            $studentId = Student::generateNextId($studentType);

            $studentContactId = null;
            $studentContactAttributes = $this->onlyExistingColumns('student_contacts', [
                'personal_contact' => $payload['contacts']['personal_contact'] ?? null,
                'emergency_contact_name' => $payload['contacts']['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $payload['contacts']['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $payload['contacts']['emergency_contact_relationship'] ?? null,
            ]);

            if ($studentContactAttributes !== []) {
                $studentContactId = (int) DB::table('student_contacts')->insertGetId($studentContactAttributes);
            }

            $studentParentInfoId = null;
            $studentParentInfoAttributes = $this->onlyExistingColumns('student_parents_info', [
                'father_name' => $payload['parents']['father_name'] ?? null,
                'father_contact' => $payload['parents']['father_contact'] ?? null,
                'mother_name' => $payload['parents']['mother_name'] ?? null,
                'mother_contact' => $payload['parents']['mother_contact'] ?? null,
                'guardian_name' => $payload['parents']['guardian_name'] ?? null,
                'guardian_relationship' => $payload['parents']['guardian_relationship'] ?? null,
                'guardian_contact' => $payload['parents']['guardian_contact'] ?? null,
                'family_address' => $payload['parents']['family_address'] ?? null,
            ]);

            if ($studentParentInfoAttributes !== []) {
                $studentParentInfoId = (int) DB::table('student_parents_info')->insertGetId($studentParentInfoAttributes);
            }

            $studentEducationInfoId = null;
            $studentEducationInfoAttributes = $this->onlyExistingColumns('student_education_info', [
                'elementary_school' => $payload['education']['elementary_school'] ?? null,
                'elementary_year_graduated' => $payload['education']['elementary_year_graduated'] ?? null,
                'high_school' => $payload['education']['high_school'] ?? null,
                'high_school_year_graduated' => $payload['education']['high_school_year_graduated'] ?? null,
                'senior_high_school' => $payload['education']['senior_high_school'] ?? null,
                'senior_high_year_graduated' => $payload['education']['senior_high_year_graduated'] ?? null,
                'vocational_school' => $payload['education']['vocational_school'] ?? null,
                'vocational_course' => $payload['education']['vocational_course'] ?? null,
                'vocational_year_graduated' => $payload['education']['vocational_year_graduated'] ?? null,
            ]);

            if ($studentEducationInfoAttributes !== []) {
                $studentEducationInfoId = (int) DB::table('student_education_info')->insertGetId($studentEducationInfoAttributes);
            }

            // Process uploaded documents
            $uploadedDocuments = $this->processDocumentUploads($request, (string) $studentId);

            $contacts = array_filter([
                'personal_contact' => $payload['contacts']['personal_contact'] ?? null,
                'emergency_contact_name' => $payload['contacts']['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $payload['contacts']['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $payload['contacts']['emergency_contact_relationship'] ?? null,
                'parents' => $payload['parents'] ?? null,
                'education' => $payload['education'] ?? null,
                'consent' => true,
                'documents' => $uploadedDocuments !== [] ? $uploadedDocuments : null,
            ], static fn ($value): bool => $value !== null && $value !== '');

            return Student::query()->create([
                'school_id' => $this->resolveSiteSchoolId(),
                'student_id' => $studentId,
                'student_type' => $studentType,
                'lrn' => null,
                'first_name' => $payload['first_name'],
                'middle_name' => $payload['middle_name'] ?? null,
                'last_name' => $payload['last_name'],
                'suffix' => $payload['suffix'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'birth_date' => $birthDate,
                'age' => (int) $birthDate->diffInYears(now()),
                'gender' => $payload['gender'],
                'civil_status' => $payload['civil_status'] ?? null,
                'nationality' => $payload['nationality'],
                'religion' => $payload['religion'] ?? null,
                'address' => $payload['address'],
                'emergency_contact' => $payload['contacts']['emergency_contact_phone'] ?? null,
                'course_id' => $courseId,
                'academic_year' => $academicYear,
                'student_contact_id' => $studentContactId,
                'student_parent_info' => $studentParentInfoId,
                'student_education_id' => $studentEducationInfoId,
                'status' => StudentStatus::Applicant,
                'contacts' => $contacts,
                'scholarship_type' => null, // Explicitly not a scholar yet
            ]);
        });

        $systemSchoolYearStart = $settings->getSystemDefaultSchoolYearStart();
        $schoolYear = $systemSchoolYearStart.' - '.($systemSchoolYearStart + 1);
        $semester = $settings->getSystemDefaultSemester();
        $semesterLabel = match ($semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            default => 'Semester '.$semester,
        };

        return redirect()->route('enrollment.create')->with('flash', [
            'success' => sprintf(
                'Registration submitted for %s %s (Applicant ID: %s).',
                $student->first_name,
                $student->last_name,
                (string) $student->student_id
            ),
            'studentId' => (string) $student->student_id,
            'studentName' => sprintf('%s %s', $student->first_name, $student->last_name),
            'course' => $course->title,
            'courseCode' => $course->code,
            'schoolYear' => $schoolYear,
            'semester' => $semester,
            'semesterLabel' => $semesterLabel,
            'academicYear' => $academicYear,
            'yearLevelLabel' => $academicYear ? match ($academicYear) {
                1 => '1st Year',
                2 => '2nd Year',
                3 => '3rd Year',
                4 => '4th Year',
                default => 'Year '.$academicYear,
            } : null,
            'continuing' => false,
        ]);
    }

    /**
     * Look up an existing student record by email + student ID.
     * Used by the enrollment landing page to decide between the
     * returning-student short flow and the new-applicant full form.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'student_id' => ['required', 'string', 'max:20'],
        ]);

        $studentIdNumeric = (int) preg_replace('/\D/', '', (string) $validated['student_id']);

        if ($studentIdNumeric === 0) {
            return response()->json(['matched' => false]);
        }

        $student = Student::query()
            ->with(['Course.department'])
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $validated['email'])])
            ->where('student_id', $studentIdNumeric)
            ->first();

        if (! $student instanceof Student) {
            return response()->json(['matched' => false]);
        }

        $studentTypeValue = $student->student_type instanceof StudentType
            ? $student->student_type->value
            : (string) $student->student_type;

        $statusValue = $student->status instanceof StudentStatus
            ? $student->status->value
            : (string) $student->status;

        return response()->json([
            'matched' => true,
            'student' => [
                'id' => $student->id,
                'student_id' => (string) $student->student_id,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'full_name' => mb_trim(sprintf('%s %s', (string) $student->first_name, (string) $student->last_name)),
                'email' => $student->email,
                'student_type' => $studentTypeValue,
                'status' => $statusValue,
                'academic_year' => $student->academic_year,
                'course' => $student->Course ? [
                    'id' => $student->Course->id,
                    'code' => $student->Course->code,
                    'title' => $student->Course->title,
                    'department' => $student->Course->department?->code,
                ] : null,
            ],
        ]);
    }

    /**
     * Return the subjects available for a returning student's course, scoped to
     * the current academic period. Requires email + student_id for verification
     * since this endpoint is public.
     */
    public function subjectsFor(Request $request, GeneralSettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'student_id' => ['required', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $studentIdNumeric = (int) preg_replace('/\D/', '', (string) $validated['student_id']);

        $student = Student::query()
            ->with('Course')
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $validated['email'])])
            ->where('student_id', $studentIdNumeric)
            ->first();

        if (! $student instanceof Student || ! $student->course_id) {
            return response()->json([], 404);
        }

        $schoolYear = $settings->getCurrentSchoolYearString();
        $semester = $settings->getCurrentSemester();

        // Determine which subject codes have at least one class open this period
        // (mirrors the admin searchSubjects logic so the ⭐ indicator works).
        $subjectsWithClasses = collect();
        try {
            $classesWithSubjects = Classes::query()
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->get();

            foreach ($classesWithSubjects as $class) {
                if (empty($class->course_codes)) {
                    continue;
                }
                if (! is_array($class->course_codes)) {
                    continue;
                }
                $courseCodesAsStrings = array_map(strval(...), $class->course_codes);
                if (! in_array((string) $student->course_id, $courseCodesAsStrings)) {
                    continue;
                }

                if (! empty($class->subject_ids) && is_array($class->subject_ids)) {
                    foreach (Subject::query()->whereIn('id', $class->subject_ids)->get() as $subject) {
                        $subjectsWithClasses->push($subject->code);
                    }
                }

                if (! empty($class->subject_code)) {
                    $codes = array_map(trim(...), explode(',', (string) $class->subject_code));
                    foreach ($codes as $code) {
                        if ($code !== '') {
                            $subjectsWithClasses->push($code);
                        }
                    }
                }
            }
        } catch (Throwable) {
            // If the Classes lookup fails, continue with an empty "has_classes" set.
        }
        $subjectsWithClasses = $subjectsWithClasses->unique();

        $search = (string) ($validated['search'] ?? '');

        $subjectsQuery = Subject::query()
            ->with('course')
            ->where('course_id', $student->course_id);

        if ($search !== '') {
            $subjectsQuery->where(function ($query) use ($search): void {
                $query->where('code', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%");
            });
        }

        $subjects = $subjectsQuery
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->orderBy('code')
            ->limit(200)
            ->get()
            ->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'lecture' => (int) ($subject->lecture ?? 0),
                'laboratory' => (int) ($subject->laboratory ?? 0),
                'academic_year' => (int) ($subject->academic_year ?? 0),
                'semester' => (int) ($subject->semester ?? 0),
                'lec_per_unit' => (float) ($subject->course?->lec_per_unit ?? 0),
                'lab_per_unit' => (float) ($subject->course?->lab_per_unit ?? 0),
                'has_classes' => $subjectsWithClasses->contains($subject->code),
            ])
            ->values();

        return response()->json([
            'course' => [
                'id' => $student->Course?->id,
                'code' => $student->Course?->code,
                'title' => $student->Course?->title,
                'lec_per_unit' => (float) ($student->Course?->lec_per_unit ?? 0),
                'lab_per_unit' => (float) ($student->Course?->lab_per_unit ?? 0),
                'miscellaneous' => $student->Course
                    ? (int) $student->Course->getMiscellaneousFee()
                    : 3500,
            ],
            'subjects' => $subjects,
        ]);
    }

    /**
     * Create a StudentEnrollment for a returning student who was successfully
     * matched via the lookup endpoint. Uses the student's existing course_id
     * (course is locked) and creates subject enrollments + tuition breakdown.
     */
    public function storeContinuing(
        Request $request,
        GeneralSettingsService $settings,
        EnrollmentService $enrollmentService,
    ): RedirectResponse {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'student_id' => ['required', 'string', 'max:20'],
            'academic_year' => ['required', 'integer', 'in:1,2,3,4'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'integer', 'exists:subject,id'],
            'subjects.*.is_modular' => ['boolean'],
            'subjects.*.lecture_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.laboratory_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.enrolled_lecture_units' => ['required', 'integer', 'min:0'],
            'subjects.*.enrolled_laboratory_units' => ['required', 'integer', 'min:0'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'You must confirm that the information is accurate and you agree to the data privacy notice.',
            'subjects.required' => 'Please select at least one subject to enroll in.',
            'subjects.min' => 'Please select at least one subject to enroll in.',
        ]);

        $studentIdNumeric = (int) preg_replace('/\D/', '', (string) $validated['student_id']);

        $student = Student::query()
            ->with('Course')
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $validated['email'])])
            ->where('student_id', $studentIdNumeric)
            ->first();

        if (! $student instanceof Student) {
            return back()->with('flash', [
                'error' => 'We could not verify your record. Please double-check your email and Student ID.',
            ]);
        }

        if (! $student->course_id) {
            return back()->with('flash', [
                'error' => 'Your student record has no course on file. Please contact the registrar.',
            ]);
        }

        // Always write under the GLOBAL current term on the public flow, never a
        // per-user override. The public form has no authenticated session, but we
        // use the system-default helpers explicitly so this cannot drift even if
        // per-user semester overrides are ever resolved here. This also keeps the
        // record visible to admins regardless of their own SemesterSelector
        // preference (which only affects *their* view, not what gets saved).
        $systemSchoolYearStart = $settings->getSystemDefaultSchoolYearStart();
        $schoolYear = $systemSchoolYearStart.' - '.($systemSchoolYearStart + 1);
        $semester = (int) ($validated['semester'] ?? $settings->getSystemDefaultSemester());

        // Duplicate guard 1: any enrollment for this exact student row in the
        // given school year + semester.
        $existing = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->first();

        // Duplicate guard 2: catch duplicate Student records that share the
        // same identity as this student (same email OR same name + birth_date).
        // Blocks re-enrollment submitted under a different student row.
        if (! $existing) {
            $relatedStudentIds = Student::query()
                ->where('id', '!=', $student->id)
                ->where(function ($query) use ($student): void {
                    if ($student->email !== null && $student->email !== '') {
                        $query->orWhereRaw('LOWER(email) = ?', [Str::lower((string) $student->email)]);
                    }
                    if ($student->birth_date !== null) {
                        $query->orWhere(function ($q) use ($student): void {
                            $q->whereRaw('LOWER(first_name) = ?', [mb_strtolower((string) $student->first_name)])
                                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower((string) $student->last_name)])
                                ->whereDate('birth_date', Carbon::parse($student->birth_date)->toDateString());
                        });
                    }
                })
                ->pluck('id');

            if ($relatedStudentIds->isNotEmpty()) {
                $existing = StudentEnrollment::query()
                    ->whereIn('student_id', $relatedStudentIds)
                    ->where('school_year', $schoolYear)
                    ->where('semester', $semester)
                    ->first();
            }
        }

        if ($existing) {
            return redirect()->route('enrollment.create')->with('flash', [
                'error' => sprintf(
                    'An enrollment is already on file for SY %s, Semester %d matching your student record. Please contact the registrar if you need changes.',
                    $schoolYear,
                    $semester
                ),
            ]);
        }

        try {
            [$enrollment, $tuition] = DB::transaction(function () use ($validated, $student, $schoolYear, $semester, $enrollmentService): array {
                $schoolId = $this->resolveSiteSchoolId($student);

                $enrollment = StudentEnrollment::query()->create([
                    'school_id' => $schoolId,
                    'student_id' => $student->id,
                    'course_id' => $student->course_id,
                    'semester' => $semester,
                    'academic_year' => (int) $validated['academic_year'],
                    'school_year' => $schoolYear,
                ]);

                foreach ($validated['subjects'] as $subjectData) {
                    $enrollment->subjectsEnrolled()->create([
                        'school_id' => $schoolId,
                        'subject_id' => $subjectData['subject_id'],
                        'class_id' => null,
                        'student_id' => $student->id,
                        'is_modular' => $subjectData['is_modular'] ?? false,
                        'lecture_fee' => $subjectData['lecture_fee'],
                        'laboratory_fee' => $subjectData['laboratory_fee'],
                        'enrolled_lecture_units' => $subjectData['enrolled_lecture_units'],
                        'enrolled_laboratory_units' => $subjectData['enrolled_laboratory_units'],
                        'academic_year' => (int) $validated['academic_year'],
                        'school_year' => $schoolYear,
                        'semester' => $semester,
                    ]);
                }

                $tuition = $enrollmentService->createStudentTuition($enrollment, [
                    'subjectsEnrolled' => $validated['subjects'],
                    'discount' => 0,
                    'downpayment' => 0,
                    'additionalFees' => [],
                ]);

                return [$enrollment, $tuition];
            });
        } catch (Exception $exception) {
            return back()->with('flash', [
                'error' => 'We could not save your re-enrollment: '.$exception->getMessage(),
            ]);
        }

        // Build a rich summary payload for the success screen.
        $subjectIds = collect($validated['subjects'])->pluck('subject_id')->all();
        $subjectModels = Subject::query()->whereIn('id', $subjectIds)->get()->keyBy('id');

        $subjectsSummary = collect($validated['subjects'])->map(function (array $row) use ($subjectModels): array {
            $model = $subjectModels->get($row['subject_id']);

            return [
                'code' => $model?->code ?? '—',
                'title' => $model?->title ?? 'Subject',
                'lecture_units' => (int) ($row['enrolled_lecture_units'] ?? 0),
                'laboratory_units' => (int) ($row['enrolled_laboratory_units'] ?? 0),
                'is_modular' => (bool) ($row['is_modular'] ?? false),
                'lecture_fee' => (float) ($row['lecture_fee'] ?? 0),
                'laboratory_fee' => (float) ($row['laboratory_fee'] ?? 0),
            ];
        })->values()->all();

        $totalUnits = array_sum(array_map(
            static fn (array $s): int => $s['lecture_units'] + $s['laboratory_units'],
            $subjectsSummary
        ));

        $semesterLabel = match ($semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            default => 'Semester '.$semester,
        };

        $yearLevelLabel = match ((int) $validated['academic_year']) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            default => 'Year '.$validated['academic_year'],
        };

        return redirect()->route('enrollment.create')->with('flash', [
            'success' => sprintf(
                'Re-enrollment submitted for %s %s (Student ID: %s). The registrar will review your subjects and confirm your final tuition.',
                (string) $student->first_name,
                (string) $student->last_name,
                (string) $student->student_id
            ),
            'studentId' => (string) $student->student_id,
            'studentName' => sprintf('%s %s', (string) $student->first_name, (string) $student->last_name),
            'course' => $student->Course?->title,
            'courseCode' => $student->Course?->code,
            'continuing' => true,
            'schoolYear' => $schoolYear,
            'semester' => $semester,
            'semesterLabel' => $semesterLabel,
            'academicYear' => (int) $validated['academic_year'],
            'yearLevelLabel' => $yearLevelLabel,
            'subjects' => $subjectsSummary,
            'totalUnits' => $totalUnits,
            'tuition' => $tuition ? [
                'total_lectures' => (float) $tuition->total_lectures,
                'total_laboratory' => (float) $tuition->total_laboratory,
                'total_tuition' => (float) $tuition->total_tuition,
                'miscellaneous' => (float) $tuition->total_miscelaneous_fees,
                'overall' => (float) $tuition->overall_tuition,
                'balance' => (float) $tuition->total_balance,
            ] : null,
        ]);
    }

    /**
     * Resolve the tenant (school) id that new public enrollment records should
     * belong to. On public routes there is no authenticated user and no session
     * school, so the BelongsToSchool global `creating` hook cannot auto-assign
     * one — which causes rows to save with `school_id = NULL` and then get
     * filtered out of admin views by `SchoolScope`.
     *
     * Order of resolution:
     *   1. The student's own `school_id` (only known for returning students).
     *   2. The single active `School` in the install.
     *   3. null (fail-open so inserts don't blow up; caller may decide).
     */
    private function resolveSiteSchoolId(?Student $student = null): ?int
    {
        if ($student instanceof Student && $student->school_id) {
            return (int) $student->school_id;
        }

        return School::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Process and store uploaded documents.
     *
     * @return array<int, array{type: string, path: string, original_name: string, size: int, mime_type: string, uploaded_at: string}>
     */
    private function processDocumentUploads(StoreEnrollmentRegistrationRequest $request, string $studentId): array
    {
        $uploadedDocuments = [];

        if (! $request->hasFile('documents')) {
            return $uploadedDocuments;
        }

        $documents = $request->file('documents');

        if (! is_array($documents)) {
            return $uploadedDocuments;
        }

        $disk = Storage::disk('private');
        $basePath = sprintf('enrollment-documents/%s', $studentId);

        foreach ($documents as $index => $documentData) {
            if (! is_array($documentData)) {
                continue;
            }
            if (! isset($documentData['file'])) {
                continue;
            }
            /** @var UploadedFile|null $file */
            $file = $documentData['file'];
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if (! $file->isValid()) {
                continue;
            }

            $type = is_string($documentData['type'] ?? null) ? $documentData['type'] : 'unknown';
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            // Generate a unique filename: type_timestamp_hash.ext
            $filename = sprintf(
                '%s_%s_%s.%s',
                preg_replace('/[^a-zA-Z0-9-]/', '_', mb_strtolower($type)),
                now()->format('Ymd_His'),
                mb_substr(md5($index.$originalName), 0, 8),
                $extension
            );

            $path = $disk->putFileAs($basePath, $file, $filename);

            if ($path === false) {
                continue;
            }

            $uploadedDocuments[] = [
                'type' => $type,
                'path' => $path,
                'original_name' => $originalName,
                'size' => $file->getSize() ?: 0,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'uploaded_at' => format_timestamp_now(),
            ];
        }

        return $uploadedDocuments;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function onlyExistingColumns(string $table, array $attributes): array
    {
        $filtered = [];

        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($value === '') {
                continue;
            }
            if (! Schema::hasColumn($table, (string) $key)) {
                continue;
            }

            $filtered[(string) $key] = $value;
        }

        return $filtered;
    }
}
