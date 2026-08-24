<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use App\Services\PdfGenerationService;
use App\Settings\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdministratorRegistrarDocumentController extends Controller
{
    /**
     * Return the selected registrar document data for the interactive preview.
     */
    public function preview(
        Request $request,
        GeneralSettingsService $settingsService,
        EnrollmentPipelineService $enrollmentPipelineService,
    ): JsonResponse {
        $validated = $this->validateRequest($request);
        $payload = $this->buildPayload($validated, $settingsService, $enrollmentPipelineService);

        return response()->json($payload);
    }

    /**
     * Generate a directly downloadable PDF for a student-facing registrar document.
     */
    public function pdf(
        Request $request,
        GeneralSettingsService $settingsService,
        EnrollmentPipelineService $enrollmentPipelineService,
        PdfGenerationService $pdfGenerationService,
    ): BinaryFileResponse {
        $validated = $this->validateRequest($request);
        $payload = $this->buildPayload($validated, $settingsService, $enrollmentPipelineService);
        $template = (string) $payload['template'];
        $variant = (string) $payload['variant'];
        $studentNumber = Str::slug((string) data_get($payload, 'student.student_number', 'student'));
        $filename = sprintf('registrar-%s-%s-%s-%s.pdf', $template, $variant, $studentNumber, now()->format('YmdHis'));
        $temporaryPath = tempnam(sys_get_temp_dir(), 'registrar_document_');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary registrar document file.');
        }

        $pdfGenerationService->generatePdfFromView('pdf.registrar-document', ['data' => $payload], $temporaryPath, [
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

    /**
     * @return array{template: string, variant: string, student_id: int, purpose: string|null}
     */
    private function validateRequest(Request $request): array
    {
        Gate::authorize('viewAny', StudentEnrollment::class);
        Gate::authorize('exportDetailed', StudentEnrollment::class);

        /** @var array{template: string, variant?: string, student_id: int, purpose: string|null} $validated */
        $validated = $request->validate([
            'template' => ['required', 'string', 'in:certificate_of_enrollment,registration_form,grade_report'],
            'variant' => ['nullable', 'string', 'max:64'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'purpose' => ['nullable', 'string', 'max:160'],
        ]);

        $variant = (string) ($validated['variant'] ?? $this->defaultVariant($validated['template']));
        if (! in_array($variant, $this->variantKeys($validated['template']), true)) {
            throw ValidationException::withMessages(['variant' => 'The selected document format is not available.']);
        }

        $validated['variant'] = $variant;

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function variantKeys(string $template): array
    {
        return match ($template) {
            'certificate_of_enrollment' => ['full_certificate', 'verification_letter', 'units_certificate'],
            'registration_form' => ['student_copy', 'adviser_copy', 'receipt_copy'],
            'grade_report' => ['official_record', 'transcript_style', 'grade_slip'],
            default => [],
        };
    }

    private function defaultVariant(string $template): string
    {
        return match ($template) {
            'certificate_of_enrollment' => 'full_certificate',
            'registration_form' => 'student_copy',
            'grade_report' => 'official_record',
            default => '',
        };
    }

    /**
     * @param  array{template: string, variant: string, student_id: int, purpose: string|null}  $validated
     * @return array<string, mixed>
     */
    private function buildPayload(
        array $validated,
        GeneralSettingsService $settingsService,
        EnrollmentPipelineService $enrollmentPipelineService,
    ): array {
        $student = Student::query()
            ->with('Course.department')
            ->findOrFail($validated['student_id']);
        $schoolYear = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();
        $semesterLabel = $settingsService->getAvailableSemesters()[$semester] ?? "Semester {$semester}";
        $enrollmentQuery = StudentEnrollment::query()
            ->withTrashed()
            ->where('student_id', $student->id)
            ->whereIn('school_year', $settingsService->getCurrentSchoolYearVariants())
            ->where('semester', $semester);

        if ($validated['template'] === 'certificate_of_enrollment') {
            $enrollmentQuery->where('status', data_get($enrollmentPipelineService->getCompletionStep(), 'status'));
        }

        $enrollment = $enrollmentQuery
            ->with([
                'course.department',
                'subjectsEnrolled.subject',
                'subjectsEnrolled.class.schedules.room',
            ])
            ->latest('id')
            ->first();

        if ($validated['template'] === 'certificate_of_enrollment' && $enrollment === null) {
            throw ValidationException::withMessages([
                'student_id' => 'A certificate of enrollment can only be generated for a student with a completed current enrollment.',
            ]);
        }

        $classEnrollments = $student->classEnrollments()
            ->with(['class.subject', 'class.schedules.room'])
            ->where('status', true)
            ->whereHas('class', function ($query) use ($settingsService, $semester): void {
                $query->whereIn('school_year', $settingsService->getCurrentSchoolYearVariants())
                    ->where('semester', $semester);
            })
            ->get();

        $school = $this->schoolPayload($settingsService);
        $subjects = $this->subjectRows($enrollment);
        $grades = $this->gradeRows($classEnrollments, $subjects);
        $template = $validated['template'];
        $titles = [
            'certificate_of_enrollment' => ['Certificate of Enrollment', 'Official confirmation of current enrollment'],
            'registration_form' => ['Registration / Enrollment Form', 'Academic registration record for the selected period'],
            'grade_report' => ['Grade Report', 'Academic performance record for the selected period'],
        ];

        return [
            'template' => $template,
            'variant' => $validated['variant'],
            'title' => $titles[$template][0],
            'subtitle' => $titles[$template][1],
            'school' => $school,
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'course_code' => $student->Course?->code,
                'course_title' => $student->Course?->title,
                'department' => $student->Course?->department?->code,
                'year_level' => $student->academic_year,
            ],
            'enrollment' => [
                'id' => $enrollment?->id,
                'school_year' => $enrollment?->school_year ?? $schoolYear,
                'semester' => $enrollment?->semester ?? $semester,
                'semester_label' => $semesterLabel,
                'status' => $enrollment?->status ?? 'Not enrolled',
                'subjects' => $subjects,
                'total_units' => collect($subjects)->sum('units'),
            ],
            'grades' => [
                'subjects' => $grades,
                'term_average' => $this->weightedAverage($grades),
            ],
            'purpose' => $validated['purpose'] ?? null,
            'generated_at' => now()->format('F d, Y'),
            'generated_by' => Auth::user() instanceof User ? Auth::user()->name : 'System',
        ];
    }

    /**
     * @return array{name: string, logo: string, contact: string, email: string, address: string}
     */
    private function schoolPayload(GeneralSettingsService $settingsService): array
    {
        $settings = $settingsService->getGlobalSettingsModel();
        $siteSettings = app(SiteSettings::class);
        $logo = $settings?->school_portal_logo ?: $siteSettings->logo;

        if (is_string($logo) && $logo !== '' && ! filter_var($logo, FILTER_VALIDATE_URL) && ! str_starts_with($logo, '/')) {
            $logo = \Illuminate\Support\Facades\Storage::url($logo);
        }

        return [
            'name' => $settings?->school_portal_title ?? $settings?->site_name ?? $siteSettings->getOrganizationName(),
            'logo' => is_string($logo) ? $logo : '',
            'contact' => $settings?->support_phone ?? $siteSettings->getSupportPhone() ?? '',
            'email' => $settings?->support_email ?? $siteSettings->getSupportEmail() ?? '',
            'address' => $siteSettings->getOrganizationAddress() ?? '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subjectRows(?StudentEnrollment $enrollment): array
    {
        if (! $enrollment) {
            return [];
        }

        return $enrollment->subjectsEnrolled->map(function ($subjectEnrollment): array {
            $subject = $subjectEnrollment->subject;
            $class = $subjectEnrollment->class;

            return [
                'code' => $subject?->code ?? $subjectEnrollment->external_subject_code ?? '—',
                'title' => $subject?->title ?? $subjectEnrollment->external_subject_title ?? '—',
                'units' => (int) ($subject?->units ?? $subjectEnrollment->external_subject_units ?? 0),
                'section' => $class?->section ?? $subjectEnrollment->section ?? '—',
                'schedule' => $class?->schedules?->map(fn ($schedule): string => sprintf('%s %s–%s', $schedule->day_of_week, $schedule->start_time, $schedule->end_time))->implode(', ') ?: 'TBA',
                'grade' => $subjectEnrollment->grade,
            ];
        })->values()->all();
    }

    /**
     * @param  iterable<int, mixed>  $classEnrollments
     * @param  list<array<string, mixed>>  $subjects
     * @return list<array<string, mixed>>
     */
    private function gradeRows(iterable $classEnrollments, array $subjects): array
    {
        $rows = collect($classEnrollments)->map(function ($classEnrollment): array {
            $class = $classEnrollment->class;
            $subject = $class?->subject;

            return [
                'code' => $subject?->code ?? $class?->subject_code ?? '—',
                'title' => $subject?->title ?? $class?->subject_title ?? '—',
                'units' => (int) ($subject?->units ?? 0),
                'prelim' => $classEnrollment->prelim_grade,
                'midterm' => $classEnrollment->midterm_grade,
                'finals' => $classEnrollment->finals_grade,
                'average' => $classEnrollment->total_average,
                'status' => $classEnrollment->is_grades_verified ? 'Verified' : ($classEnrollment->is_grades_finalized ? 'Finalized' : 'Pending'),
            ];
        })->values();

        if ($rows->isEmpty()) {
            $rows = collect($subjects)->map(fn (array $subject): array => [
                'code' => $subject['code'],
                'title' => $subject['title'],
                'units' => $subject['units'],
                'prelim' => null,
                'midterm' => null,
                'finals' => null,
                'average' => $subject['grade'],
                'status' => 'Pending',
            ]);
        }

        return $rows->all();
    }

    /**
     * @param  list<array<string, mixed>>  $grades
     */
    private function weightedAverage(array $grades): ?float
    {
        $graded = collect($grades)->filter(fn (array $grade): bool => is_numeric($grade['average']) && (int) $grade['units'] > 0);
        $units = (int) $graded->sum('units');

        if ($units === 0) {
            return null;
        }

        return round((float) $graded->sum(fn (array $grade): float => (float) $grade['average'] * (int) $grade['units']) / $units, 2);
    }
}
