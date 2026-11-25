<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\BatchCohortEnrollmentRequest;
use App\Models\Course;
use App\Models\User;
use App\Services\BatchCohortEnrollmentService;
use App\Services\EnrollmentPeriodService;
use App\Services\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class AdministratorBatchEnrollmentController extends Controller
{
    public function __construct(
        private readonly BatchCohortEnrollmentService $batchService,
        private readonly GeneralSettingsService $settingsService,
        private readonly EnrollmentPeriodService $periodService,
    ) {}

    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/login');
        }

        $currentSemester = $this->settingsService->getCurrentSemester();
        $currentSchoolYear = $this->settingsService->getCurrentSchoolYearString();

        return Inertia::render('administrators/enrollments/batch', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'courses' => Course::query()
                ->orderBy('code')
                ->get(['id', 'code', 'title'])
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ])
                ->values(),
            'settings' => [
                'currentSemester' => $currentSemester,
                'currentSchoolYear' => $currentSchoolYear,
                'availableSemesters' => $this->settingsService->getAvailableSemesters(),
            ],
            'enrollment_period' => $this->periodService->describe($currentSchoolYear, $currentSemester),
            'flash' => session('flash'),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'source_year_level' => ['required', 'integer', 'min:1', 'max:5'],
            'target_year_level' => ['required', 'integer', 'min:1', 'max:5'],
            'target_semester' => ['required', 'integer', 'in:1,2'],
            'school_year' => ['nullable', 'string', 'max:32'],
        ]);

        return response()->json(
            $this->batchService->preview(
                courseId: (int) $validated['course_id'],
                sourceYearLevel: (int) $validated['source_year_level'],
                targetYearLevel: (int) $validated['target_year_level'],
                targetSemester: (int) $validated['target_semester'],
                schoolYear: isset($validated['school_year']) ? (string) $validated['school_year'] : null,
            ),
        );
    }

    public function store(BatchCohortEnrollmentRequest $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        try {
            $summary = $this->batchService->execute($request->validated(), $actor);

            return response()->json($summary);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
