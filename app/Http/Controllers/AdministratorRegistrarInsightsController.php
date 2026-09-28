<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\RegistrarAnalyticsExport;
use App\Http\Requests\Administrators\ConfirmRegistrarStudentProfileImportRequest;
use App\Http\Requests\Administrators\StoreRegistrarStudentProfileImportRequest;
use App\Http\Requests\RegistrarAnalyticsFilterRequest;
use App\Models\Course;
use App\Models\RegistrarStudentProfileImport;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\AssessmentExportPayloadService;
use App\Services\GeneralSettingsService;
use App\Services\QueueRegulatoryReportExportService;
use App\Services\RegistrarAnalyticsService;
use App\Services\RegistrarStudentProfileImportService;
use App\Services\RegulatoryReportRegistry;
use App\Services\TenantContext;
use App\Support\ChedProgramGroup;
use App\Support\ChedProgramGrouper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdministratorRegistrarInsightsController extends Controller
{
    public function __construct(
        private readonly RegistrarAnalyticsService $analyticsService,
        private readonly RegistrarStudentProfileImportService $studentProfileImportService,
        private readonly RegulatoryReportRegistry $regulatoryReports,
        private readonly QueueRegulatoryReportExportService $regulatoryExportQueue,
        private readonly AssessmentExportPayloadService $assessmentExportPayloads,
        private readonly TenantContext $tenantContext,
        private readonly ChedProgramGrouper $programGrouper,
    ) {}

    public function analytics(RegistrarAnalyticsFilterRequest $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        Gate::authorize('viewAny', StudentEnrollment::class);

        return Inertia::render('administrators/registrar/analytics', [
            'user' => $this->userProps($user),
            'canImportStudentProfiles' => Gate::allows('exportDetailed', StudentEnrollment::class)
                && Gate::allows('update', StudentEnrollment::class)
                && Gate::allows('viewAny', Student::class)
                && Gate::allows('update', Student::class),
            ...$this->analyticsService->build($request->filters()),
        ]);
    }

    public function export(RegistrarAnalyticsFilterRequest $request): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        Gate::authorize('viewAny', StudentEnrollment::class);
        Gate::authorize('exportDetailed', StudentEnrollment::class);

        $schoolId = $this->tenantContext->getCurrentSchoolId();
        abort_if($schoolId === null, 403, 'A school must be selected before exporting registrar analytics.');

        $data = $this->analyticsService->build($request->filters(), includeDetails: true);

        $analytics = $data['analytics'];
        $analytics['quality'] = $data['quality'];

        $fileName = sprintf(
            'registrar-analytics-%s.xlsx',
            now()->format('Y-m-d_His')
        );

        $generatedAt = now()->toIso8601String();

        return Excel::download(
            new RegistrarAnalyticsExport($analytics, $data['report'], $schoolId, $generatedAt),
            $fileName
        );
    }

    public function storeStudentProfileImport(StoreRegistrarStudentProfileImportRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $file = $request->file('file');
        abort_if($file === null, 422, 'An Excel workbook is required.');

        $import = $this->studentProfileImportService->stage($user, $file);

        return response()->json([
            'import' => $this->studentProfileImportService->serialize($import),
        ], 201);
    }

    public function confirmStudentProfileImport(
        ConfirmRegistrarStudentProfileImportRequest $request,
        RegistrarStudentProfileImport $studentProfileImport,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var list<int> $studentIds */
        $studentIds = $request->validated('student_ids');
        $import = $this->studentProfileImportService->confirm($studentProfileImport, $user, $studentIds);

        return response()->json([
            'import' => $this->studentProfileImportService->serialize($import),
        ]);
    }

    public function reports(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        Gate::authorize('viewAny', StudentEnrollment::class);

        $currentSchool = $this->tenantContext->getCurrentSchool();
        $regulatoryContext = $this->regulatoryReports->context($currentSchool);

        return Inertia::render('administrators/registrar/reports', [
            'user' => $this->userProps($user),
            'filters' => $this->analyticsService->semesterContext(),
            'regulatory_reports' => $regulatoryContext,
            'jurisdiction' => [
                'country_code' => $regulatoryContext['country_code'],
                'agencies' => $regulatoryContext['agencies'],
                // Kept for consumers that still read the previous response shape.
                'is_philippines' => $regulatoryContext['country_code'] === 'PH',
                'regulatory_agency' => $regulatoryContext['agencies'][0] ?? null,
            ],
            'assessment_export_options' => [
                'student_limits' => config('assessment-exports.student_limit_options'),
            ],
        ]);
    }

    /**
     * Programs offered by the school, grouped so the inspector can tick one
     * entry per program instead of one per curriculum-year course row.
     */
    public function regulatoryCourseOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('viewAny', StudentEnrollment::class);

        $school = $this->tenantContext->getCurrentSchool();

        $query = Course::query()
            ->with(['department:id,code,name', 'courseType:id,name'])
            ->where('is_active', true)
            ->orderBy('code');

        if (! empty($school)) {
            $query->where('school_id', $school->id);
        }

        $departmentId = $this->numericFilter($request->query('department_filter', 'all'));
        if ($departmentId !== 'all') {
            $query->where('department_id', $departmentId);
        }

        $courses = $query->get();

        $programs = $this->programGrouper->group($courses)
            ->map(fn (ChedProgramGroup $program): array => [
                'key' => implode(',', $program->courseIds),
                'title' => $program->title,
                'program_code' => $program->programCode,
                'course_ids' => $program->courseIds,
                'course_codes' => $program->courses
                    ->map(static fn (Course $member): string => (string) $member->code)
                    ->unique()
                    ->values()
                    ->all(),
                'curriculum_years' => $program->courses
                    ->map(static fn (Course $member): string => mb_trim((string) $member->curriculum_year))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'department_id' => $program->representative->department_id,
                'department' => $program->representative->department?->code,
                'label' => $program->programCode !== ''
                    ? sprintf('%s - %s', $program->programCode, $program->title)
                    : $program->title,
            ])
            ->values();

        return response()->json(['programs' => $programs]);
    }

    public function chedPreview(Request $request): JsonResponse
    {
        return $this->regulatoryPreview($request, RegulatoryReportRegistry::CHED_EFORM_BC);
    }

    public function regulatoryPreview(Request $request, string $reportKey): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('viewAny', StudentEnrollment::class);

        $school = $this->tenantContext->getCurrentSchool();
        abort_unless($this->regulatoryReports->definition($reportKey) !== null, 404);
        abort_unless($this->regulatoryReports->isAvailable($reportKey, $school), 403);

        $filters = $this->validatedReportFilters($request, $school);

        $data = $this->regulatoryReports->adapter($reportKey)->buildPreviewData([
            ...$filters,
            'report_key' => $reportKey,
        ]);

        return response()->json([
            ...$data,
            ...$this->reportPreviewMetadata($school, $filters, $user),
        ]);
    }

    public function chedExport(Request $request): JsonResponse
    {
        return $this->regulatoryExport($request, RegulatoryReportRegistry::CHED_EFORM_BC);
    }

    public function regulatoryExport(Request $request, string $reportKey): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('viewAny', StudentEnrollment::class);
        Gate::authorize('exportDetailed', StudentEnrollment::class);

        $school = $this->tenantContext->getCurrentSchool();
        abort_unless($this->regulatoryReports->definition($reportKey) !== null, 404);
        abort_unless($this->regulatoryReports->isAvailable($reportKey, $school), 403);

        $filters = $this->validatedReportFilters($request, $school);

        $definition = $this->regulatoryReports->definition($reportKey) ?? [];
        $export = $this->regulatoryExportQueue->queue($user, $school->id, $reportKey, $filters, $definition);

        return response()->json([
            'message' => 'Your Excel export has been queued. You will receive a download when it is ready.',
            'job' => $this->assessmentExportPayloads->make($export),
        ], 202);
    }

    /**
     * @return array{name: string, email: string, avatar: string|null, role: string}
     */
    private function userProps(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->getFilamentAvatarUrl(),
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }

    /**
     * @return array{school_year: string|null, semester: int|null, department_id: int|string, course_ids: list<int>, merge_programs: bool, only_with_data: bool, delivery_mode: string, program_status: string, school_id: int}
     */
    private function validatedReportFilters(Request $request, School $school): array
    {
        /** @var array{school_year?: string|null, semester?: int|string|null, department_filter?: string|null, course_filter?: string|array|null, merge_programs?: mixed, only_with_data?: mixed, delivery_mode?: string|null, program_status?: string|null} $validated */
        $validated = $request->validate([
            'school_year' => ['nullable', 'string', 'regex:/^\d{4}(?: - |-)\d{4}$/'],
            'semester' => ['nullable', 'integer', 'between:1,3'],
            'department_filter' => ['nullable', 'string', 'regex:/^(all|[1-9]\d*)$/'],
            // Comma separated ids let the inspector show only the ticked programs.
            'course_filter' => ['nullable', 'regex:/^(all|[1-9]\d*(?:,[1-9]\d*)*)$/'],
            'course_filter.*' => ['integer', 'min:1'],
            'merge_programs' => ['nullable'],
            'only_with_data' => ['nullable'],
            'delivery_mode' => ['nullable', 'string', 'max:50'],
            'program_status' => ['nullable', 'string', 'max:50'],
        ]);

        return [
            'school_year' => isset($validated['school_year'])
                ? GeneralSettingsService::normalizeSchoolYear($validated['school_year'])
                : null,
            'semester' => isset($validated['semester']) ? (int) $validated['semester'] : null,
            'department_id' => $this->numericFilter($validated['department_filter'] ?? 'all'),
            'course_ids' => $this->courseIdList($validated['course_filter'] ?? 'all'),
            'merge_programs' => $this->toggleFilter($validated['merge_programs'] ?? true, default: true),
            'only_with_data' => $this->toggleFilter($validated['only_with_data'] ?? false, default: false),
            'delivery_mode' => $this->stringFilter($validated['delivery_mode'] ?? null),
            'program_status' => $this->stringFilter($validated['program_status'] ?? null),
            'school_id' => $school->id,
        ];
    }

    /**
     * Read a checkbox toggle that may arrive as "1"/"0", true/false, or on/off.
     */
    private function toggleFilter(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(
            mb_strtolower(mb_trim((string) $value)),
            ['0', 'false', 'off', 'no'],
            true,
        );
    }

    private function stringFilter(mixed $value): string
    {
        $normalised = mb_strtoupper(mb_trim((string) $value));

        return $normalised === '' || $normalised === 'ALL' ? 'all' : $normalised;
    }

    /**
     * Normalise a course selection into a list of ids. An empty list means
     * "every program", which is the default scope for the report.
     *
     * @return list<int>
     */
    private function courseIdList(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        if ($values === ['all']) {
            return [];
        }

        return collect($values)
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function numericFilter(string $value): int|string
    {
        return $value === 'all' ? 'all' : (int) $value;
    }

    /**
     * @param  array{school_year: string|null, semester: int|null, department_id: int|string, course_id: int|string, school_id: int}  $filters
     * @return array{school: array{name: string, code: string, logo: string, contact: string, phone: string|null, email: string|null, address: string, location: string|null}, school_year: string, semester: string, semester_label: string, semester_value: int|null, generated_at: string, generated_by: string}
     */
    private function reportPreviewMetadata(School $school, array $filters, User $user): array
    {
        $semesterValue = $filters['semester'];
        $semesterLabel = $semesterValue !== null ? $this->semesterLabel($semesterValue) : 'All semesters';

        return [
            'school' => [
                'name' => $school->name,
                'code' => $school->code,
                'logo' => '',
                'contact' => $school->phone ?? '',
                'phone' => $school->phone,
                'email' => $school->email,
                'address' => $school->location ?? '',
                'location' => $school->location,
            ],
            'school_year' => $filters['school_year'] ?? 'All school years',
            'semester' => $semesterLabel,
            'semester_label' => $semesterLabel,
            'semester_value' => $semesterValue,
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $user->name,
        ];
    }

    private function semesterLabel(int $semester): string
    {
        return match ($semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            3 => 'Summer',
        };
    }
}
