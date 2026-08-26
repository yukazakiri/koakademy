<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Administrators\StoreUserRequest;
use App\Http\Requests\Administrators\UpdateUserRequest;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use App\Services\OnlineUserPresenceService;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class AdministratorUserManagementController extends Controller
{
    public function __construct(private readonly OnlineUserPresenceService $onlineUserPresence) {}

    public function index(Request $request): Response
    {
        $this->authorizeUserManagementAccess();

        $query = User::query()
            ->with(['school', 'department', 'roles'])
            ->when($request->search, function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->role, function ($query, $role): void {
                $query->where('role', $role);
            })
            ->when($request->school_id, function ($query, $schoolId): void {
                $query->where('school_id', $schoolId);
            })
            ->when($request->department_id, function ($query, $departmentId): void {
                $query->where('department_id', $departmentId);
            })
            ->when($request->email_verified, function ($query, $verified): void {
                if ($verified === 'true') {
                    $query->whereNotNull('email_verified_at');
                } elseif ($verified === 'false') {
                    $query->whereNull('email_verified_at');
                }
            })
            ->when($request->trashed, function ($query, $trashed): void {
                if ($trashed === 'with') {
                    $query->withTrashed();
                } elseif ($trashed === 'only') {
                    $query->onlyTrashed();
                }
            });

        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Get all users for client-side pagination with TanStack Table
        $users = $query->get()->each(function (User $user): void {
            $user->setAttribute('last_login_at', $user->getAttribute('last_login_at'));
            $user->setAttribute('security_two_factor_enabled', (bool) ($user->getAttribute('security_two_factor_enabled') ?? true));
        });

        $onlineUserIds = $this->onlineUserPresence->onlineUserIds();
        $onlineUsers = count($onlineUserIds);

        return Inertia::render('administrators/users/index', [
            'users' => [
                'data' => $users,
                'total' => $users->count(),
            ],
            'analytics' => $this->getUserAnalytics($onlineUsers),
            'online_user_ids' => $onlineUserIds,
            'filters' => $request->all(['search', 'role', 'school_id', 'department_id', 'email_verified', 'trashed', 'sort', 'direction']),
            'options' => [
                'roles' => array_map(fn (UserRole $role) => $role->value, UserRole::cases()),
                'schools' => School::all(['id', 'name']),
                'departments' => Department::all(['id', 'name']),
            ],
            'user' => $this->getUserProps(),
        ]);
    }

    public function create(): Response
    {
        $this->authorizeUserManagementAccess();

        return Inertia::render('administrators/users/create', [
            'roles' => $this->getAvailableRoles(),
            'schools' => School::all(['id', 'name']),
            'departments' => Department::all(['id', 'name', 'school_id']),
            'permissions' => Role::all(['id', 'name']),
            'user' => $this->getUserProps(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorizeUserManagementAccess();

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        // Handle roles separately
        $permissionRoles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create($data);

        if (! empty($permissionRoles)) {
            $user->roles()->sync($permissionRoles);
        }

        return redirect()->route('administrators.users.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'User created successfully.',
            ]);
    }

    public function edit(User $user): Response
    {
        $this->authorizeUserManagementAccess();

        $user->load(['roles']);

        return Inertia::render('administrators/users/edit', [
            'user' => $user,
            'roles' => $this->getAvailableRoles(),
            'schools' => School::all(['id', 'name']),
            'departments' => Department::all(['id', 'name', 'school_id']),
            'permissions' => Role::all(['id', 'name']),
            'auth_user' => $this->getUserProps(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeUserManagementAccess();

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $permissionRoles = $data['roles'] ?? [];
        unset($data['roles']);

        $user->update($data);

        if (isset($request->roles)) { // Only sync if roles key is present in request
            $user->roles()->sync($permissionRoles);
        }

        return redirect()->route('administrators.users.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'User updated successfully.',
            ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeUserManagementAccess();

        // Authorization check logic could go here
        $user->delete();

        return redirect()->back()
            ->with('flash', [
                'type' => 'success',
                'message' => 'User deleted successfully.',
            ]);
    }

    public function impersonate(User $user): RedirectResponse
    {
        $this->authorizeUserManagementAccess();

        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->hasHigherAuthorityThan($user) && ! $currentUser->isSuperAdmin()) {
            abort(403, 'You cannot impersonate this user.');
        }

        // Basic impersonation logic
        // Ideally use a library like lab404/laravel-impersonate if available
        // For now, using session-based manual implementation or just direct login if no package

        // Check if using stechstudio/filament-impersonate (which uses STS\FilamentImpersonate)
        // Since we are in a standard controller, we can use Auth::login

        // Save original ID
        session()->put('impersonator_id', $currentUser->id);

        Auth::login($user);

        if ($user->isStudentRole()) {
            return redirect()->route('student.dashboard');
        }

        if ($user->isFaculty()) {
            return redirect()->route('faculty.dashboard');
        }

        return redirect()->route('administrators.dashboard'); // Redirect to dashboard as new user
    }

    public function stopImpersonating(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        if ($impersonatorId) {
            Auth::loginUsingId($impersonatorId);
            session()->forget('impersonator_id');

            return redirect()->route('administrators.users.index')
                ->with('flash', [
                    'type' => 'success',
                    'message' => 'Impersonation stopped. Welcome back.',
                ]);
        }

        return redirect()->back();
    }

    public function verifyEmail(User $user): RedirectResponse
    {
        $this->authorizeUserManagementAccess();

        $user->email_verified_at = now();
        $user->save();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Email verified successfully.',
        ]);
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorizeUserManagementAccess();

        $status = Password::sendResetLink(['email' => $user->email]);

        return redirect()->back()->with('flash', [
            'type' => $status === Password::RESET_LINK_SENT ? 'success' : 'error',
            'message' => __($status),
        ]);
    }

    /**
     * @return array{
     *     total_users: int,
     *     all_time_users: int,
     *     trashed_users: int,
     *     new_users_today: int,
     *     new_users_30_days: int,
     *     previous_30_days_users: int,
     *     growth_rate: float,
     *     verified_users: int,
     *     unverified_users: int,
     *     verification_rate: float,
     *     online_users: int,
     *     online_rate: float,
     *     two_factor_enabled_users: int,
     *     two_factor_rate: float,
     *     assigned_users: int,
     *     assignment_rate: float,
     *     top_active_users: array<int, array{id: string, name: string, email: string, requests: int, avatar: string|null}>,
     *     registrations_chart: array<int, array{date: string, label: string, count: int, cumulative: int}>,
     *     role_distribution: array<int, array{role: string, label: string, count: int, percentage: float}>,
     *     school_distribution: array<int, array{id: int|null, name: string, count: int, percentage: float}>,
     *     department_distribution: array<int, array{id: int|null, name: string, count: int, percentage: float}>,
     *     recent_users: array<int, array{id: int, name: string, email: string, role: string, role_label: string, avatar: string|null, verified: bool, created_at: string|null}>,
     *     last_updated_at: string,
     * }
     */
    private function getUserAnalytics(int $onlineUsers): array
    {
        $today = Carbon::today();
        $currentPeriodStart = $today->copy()->subDays(29)->startOfDay();
        $previousPeriodStart = $currentPeriodStart->copy()->subDays(30);
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $newUsers30Days = User::where('created_at', '>=', $currentPeriodStart)->count();
        $previous30DaysUsers = User::whereBetween('created_at', [$previousPeriodStart, $currentPeriodStart->copy()->subSecond()])->count();
        $twoFactorEnabledUsers = User::where('security_two_factor_enabled', true)->count();
        $assignedUsers = User::where(function ($query): void {
            $query->whereNotNull('school_id')
                ->orWhereNotNull('department_id');
        })->count();

        return [
            'total_users' => $totalUsers,
            'all_time_users' => User::withTrashed()->count(),
            'trashed_users' => User::onlyTrashed()->count(),
            'new_users_today' => User::whereDate('created_at', $today)->count(),
            'new_users_30_days' => $newUsers30Days,
            'previous_30_days_users' => $previous30DaysUsers,
            'growth_rate' => $this->percentageChange($newUsers30Days, $previous30DaysUsers),
            'verified_users' => $verifiedUsers,
            'unverified_users' => max($totalUsers - $verifiedUsers, 0),
            'verification_rate' => $this->percentageOf($verifiedUsers, $totalUsers),
            'online_users' => $onlineUsers,
            'online_rate' => $this->percentageOf($onlineUsers, $totalUsers),
            'two_factor_enabled_users' => $twoFactorEnabledUsers,
            'two_factor_rate' => $this->percentageOf($twoFactorEnabledUsers, $totalUsers),
            'assigned_users' => $assignedUsers,
            'assignment_rate' => $this->percentageOf($assignedUsers, $totalUsers),
            'top_active_users' => $this->getTopActiveUsers(),
            'registrations_chart' => $this->getRegistrationTrend($currentPeriodStart, $today),
            'role_distribution' => $this->getRoleDistribution($totalUsers),
            'school_distribution' => $this->getSchoolDistribution($totalUsers),
            'department_distribution' => $this->getDepartmentDistribution($totalUsers),
            'recent_users' => $this->getRecentUsers(),
            'last_updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, email: string, requests: int, avatar: string|null}>
     */
    private function getTopActiveUsers(): array
    {
        if (! Schema::hasTable('pulse_aggregates') || ! config('pulse.enabled')) {
            return [];
        }

        $activity = DB::table('pulse_aggregates')
            ->select('key')
            ->selectRaw('SUM(value) as requests')
            ->where('type', 'user_request')
            ->where('period', 60)
            ->where('aggregate', 'count')
            ->where('bucket', '>=', now()->subMinutes(60)->timestamp)
            ->groupBy('key')
            ->orderByDesc('requests')
            ->limit(5)
            ->get();

        $users = User::whereIn('id', $activity->pluck('key')->map(fn ($key): int => (int) $key))
            ->get(['id', 'name', 'email', 'avatar_url'])
            ->keyBy('id');

        return $activity
            ->map(function ($item) use ($users): array {
                $user = $users->get((int) $item->key);

                return [
                    'id' => (string) $item->key,
                    'name' => $user?->name ?? 'Unknown User',
                    'email' => $user?->email ?? '',
                    'requests' => (int) $item->requests,
                    'avatar' => $user?->avatar_url,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{date: string, label: string, count: int, cumulative: int}>
     */
    private function getRegistrationTrend(Carbon $startDate, Carbon $endDate): array
    {
        $countsByDate = User::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($item): array => [(string) $item->date => (int) $item->count]);

        $cumulative = User::where('created_at', '<', $startDate)->count();

        return collect(CarbonPeriod::create($startDate->toDateString(), $endDate->toDateString()))
            ->map(function ($date) use ($countsByDate, &$cumulative): array {
                $count = (int) ($countsByDate[$date->toDateString()] ?? 0);
                $cumulative += $count;

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('M j'),
                    'count' => $count,
                    'cumulative' => $cumulative,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{role: string, label: string, count: int, percentage: float}>
     */
    private function getRoleDistribution(int $totalUsers): array
    {
        return DB::table('users')
            ->select('role')
            ->selectRaw('COUNT(*) as count')
            ->whereNull('deleted_at')
            ->groupBy('role')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item): array => [
                'role' => (string) $item->role,
                'label' => UserRole::tryFrom((string) $item->role)?->getLabel() ?? str((string) $item->role)->replace('_', ' ')->title()->toString(),
                'count' => (int) $item->count,
                'percentage' => $this->percentageOf((int) $item->count, $totalUsers),
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{id: int|null, name: string, count: int, percentage: float}>
     */
    private function getSchoolDistribution(int $totalUsers): array
    {
        return DB::table('users')
            ->leftJoin('schools', 'schools.id', '=', 'users.school_id')
            ->select('schools.id', DB::raw("COALESCE(schools.name, 'Unassigned') as name"))
            ->selectRaw('COUNT(users.id) as count')
            ->whereNull('users.deleted_at')
            ->groupBy('schools.id', 'schools.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn ($item): array => [
                'id' => $item->id ? (int) $item->id : null,
                'name' => (string) $item->name,
                'count' => (int) $item->count,
                'percentage' => $this->percentageOf((int) $item->count, $totalUsers),
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{id: int|null, name: string, count: int, percentage: float}>
     */
    private function getDepartmentDistribution(int $totalUsers): array
    {
        return DB::table('users')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select('departments.id', DB::raw("COALESCE(departments.name, 'Unassigned') as name"))
            ->selectRaw('COUNT(users.id) as count')
            ->whereNull('users.deleted_at')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn ($item): array => [
                'id' => $item->id ? (int) $item->id : null,
                'name' => (string) $item->name,
                'count' => (int) $item->count,
                'percentage' => $this->percentageOf((int) $item->count, $totalUsers),
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string, role: string, role_label: string, avatar: string|null, verified: bool, created_at: string|null}>
     */
    private function getRecentUsers(): array
    {
        return User::latest()
            ->limit(6)
            ->get(['id', 'name', 'email', 'role', 'avatar_url', 'email_verified_at', 'created_at'])
            ->map(function (User $user): array {
                $role = $user->role;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role->value,
                    'role_label' => $role->getLabel() ?? $role->value,
                    'avatar' => $user->avatar_url,
                    'verified' => $user->email_verified_at !== null,
                    'created_at' => $user->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function percentageOf(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function percentageChange(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function getAvailableRoles(): array
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser || ! $currentUser->role) {
            return [UserRole::User->value => UserRole::User->getLabel()];
        }

        $manageableRoles = $currentUser->role->getManageableRoles();
        $roles = [];
        foreach ($manageableRoles as $role) {
            $roles[$role->value] = $role->getLabel();
        }

        if ($roles === []) {
            $roles[UserRole::User->value] = UserRole::User->getLabel();
        }

        return $roles;
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

    private function authorizeUserManagementAccess(): void
    {
        $user = Auth::user();

        $this->abortUnlessUserHasAnyPermission($user instanceof User ? $user : null, 'ViewAny:User');
    }
}
