<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\RegistrarAnalyticsExport;
use App\Http\Requests\Administrators\ConfirmRegistrarStudentProfileImportRequest;
use App\Http\Requests\Administrators\StoreRegistrarStudentProfileImportRequest;
use App\Http\Requests\RegistrarAnalyticsFilterRequest;
use App\Models\RegistrarStudentProfileImport;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\RegistrarAnalyticsService;
use App\Services\RegistrarStudentProfileImportService;
use App\Services\TenantContext;
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
        private readonly TenantContext $tenantContext,
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

        return Inertia::render('administrators/registrar/reports', [
            'user' => $this->userProps($user),
            'filters' => $this->analyticsService->semesterContext(),
            'assessment_export_options' => [
                'student_limits' => config('assessment-exports.student_limit_options'),
            ],
        ]);
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
}
