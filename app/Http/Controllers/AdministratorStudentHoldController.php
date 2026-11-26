<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Administrators\StoreStudentHoldRequest;
use App\Models\Student;
use App\Models\StudentHold;
use App\Models\User;
use App\Services\StudentHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class AdministratorStudentHoldController extends Controller
{
    public function __construct(
        private readonly StudentHoldService $holdService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/login');
        }

        $statusFilter = (string) $request->query('status', 'active');
        $officeFilter = (string) $request->query('office', 'all');
        $search = mb_trim((string) $request->query('search', ''));

        $query = StudentHold::query()
            ->with(['student.Course', 'placedBy:id,name', 'releasedBy:id,name', 'overriddenBy:id,name']);

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($officeFilter !== 'all' && array_key_exists($officeFilter, StudentHold::OFFICES)) {
            $query->where('office', $officeFilter);
        }

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($sub) use ($like): void {
                $sub->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(reason) LIKE ?', [$like])
                    ->orWhereHas('student', function ($studentQuery) use ($like): void {
                        $studentQuery->whereRaw('LOWER(first_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                            ->orWhere('student_id', 'like', $like);
                    });
            });
        }

        $holds = $query
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN severity = 'blocking' THEN 0 ELSE 1 END")
            ->orderByDesc('placed_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StudentHold $hold): array => $this->holdService->serializeHold($hold));

        $activeCount = StudentHold::query()->active()->count();
        $blockingCount = StudentHold::query()->blockingEnrollment()->count();
        $overriddenCount = StudentHold::query()->where('status', StudentHold::StatusOverridden)->count();
        $releasedCount = StudentHold::query()->where('status', StudentHold::StatusReleased)->count();

        $byOffice = StudentHold::query()
            ->active()
            ->selectRaw('office, count(*) as count')
            ->groupBy('office')
            ->pluck('count', 'office')
            ->all();

        return Inertia::render('administrators/enrollments/holds', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->getFilamentAvatarUrl(),
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'holds' => $holds,
            'offices' => $this->holdService->officeOptions(),
            'stats' => [
                'active' => $activeCount,
                'blocking_enrollment' => $blockingCount,
                'overridden' => $overriddenCount,
                'released' => $releasedCount,
                'by_office' => $byOffice,
            ],
            'filters' => [
                'status' => $statusFilter,
                'office' => $officeFilter,
                'search' => $search,
            ],
            'flash' => session('flash'),
        ]);
    }

    public function forStudent(Student $student): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManageHolds($user), 403);

        $holds = $this->holdService->allHoldsForStudent((int) $student->id);

        return response()->json([
            'holds' => $holds->map(fn (StudentHold $h): array => $this->holdService->serializeHold($h))->values(),
            'active_count' => $holds->where('status', StudentHold::StatusActive)->count(),
            'blocking_count' => $holds
                ->filter(fn (StudentHold $h): bool => $h->status === StudentHold::StatusActive && $h->severity === StudentHold::SeverityBlocking && $h->blocks_enrollment)
                ->count(),
        ]);
    }

    public function store(StoreStudentHoldRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canManageHolds($user), 403);

        $student = Student::query()->findOrFail($request->integer('student_id'));
        $hold = $this->holdService->placeHold($student, $request->validated(), $user);
        $hold->load(['student.Course', 'placedBy:id,name']);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'hold' => $this->holdService->serializeHold($hold),
                'message' => 'Hold placed on '.$student->full_name.'.',
            ], 201);
        }

        return back()->with('flash', [
            'success' => sprintf('Placed %s hold on %s.', $hold->officeLabel(), $student->full_name),
        ]);
    }

    public function release(Request $request, StudentHold $hold): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canManageHolds($user), 403);

        $validated = $request->validate([
            'release_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $updated = $this->holdService->releaseHold($hold, $user, $validated['release_notes'] ?? null);
        } catch (RuntimeException $exception) {
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'hold' => $this->holdService->serializeHold($updated),
                'message' => 'Hold released.',
            ]);
        }

        return back()->with('flash', ['success' => 'Student hold has been released.']);
    }

    public function override(Request $request, StudentHold $hold): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canManageHolds($user), 403);

        $validated = $request->validate([
            'override_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $updated = $this->holdService->overrideHold($hold, $user, (string) $validated['override_reason']);
        } catch (RuntimeException $exception) {
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'hold' => $this->holdService->serializeHold($updated),
                'message' => 'Hold overridden with audit trail.',
            ]);
        }

        return back()->with('flash', ['success' => 'Student hold overridden and recorded in audit log.']);
    }

    private function canManageHolds(User $user): bool
    {
        if ($user->can('Update:StudentEnrollment') || $user->can('View:StudentEnrollment') || $user->hasRole('super_admin')) {
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
            UserRole::Librarian,
            UserRole::GuidanceCounselor,
            UserRole::StudentAffairsOfficer,
        ], true);
    }
}
