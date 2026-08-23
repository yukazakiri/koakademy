<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\RegistrarAnalyticsExport;
use App\Http\Requests\RegistrarAnalyticsFilterRequest;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\RegistrarAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdministratorRegistrarInsightsController extends Controller
{
    public function __construct(private readonly RegistrarAnalyticsService $analyticsService) {}

    public function analytics(RegistrarAnalyticsFilterRequest $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        Gate::authorize('viewAny', StudentEnrollment::class);

        return Inertia::render('administrators/registrar/analytics', [
            'user' => $this->userProps($user),
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

        $data = $this->analyticsService->build($request->filters(), includeDetails: true);

        $analytics = $data['analytics'];
        $analytics['quality'] = $data['quality'];

        $fileName = sprintf(
            'registrar-analytics-%s.xlsx',
            now()->format('Y-m-d_His')
        );

        return Excel::download(
            new RegistrarAnalyticsExport($analytics, $data['report']),
            $fileName
        );
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
