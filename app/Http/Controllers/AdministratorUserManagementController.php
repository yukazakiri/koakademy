<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Administrators\StoreUserRequest;
use App\Http\Requests\Administrators\UpdateUserRequest;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class AdministratorUserManagementController extends Controller
{
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
        $users = $query->get();

        $onlineUsers = 0;
        $onlineUserIds = [];
        $sessionDriver = config('session.driver');
        $onlineThreshold = now()->subMinutes(15)->timestamp;

        if ($sessionDriver === 'database' && Schema::hasTable('sessions')) {
            $onlineUserIds = DB::table('sessions')
                ->whereNotNull('user_id')
                ->whereRaw('to_timestamp(last_activity) >= ?', [now()->subMinutes(15)])
                ->distinct('user_id')
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->toArray();
            $onlineUsers = count($onlineUserIds);
        }

        if ($sessionDriver === 'redis') {
            $redisConnection = config('session.connection') ?? 'default';
            $key = config('cache.prefix', '').'online-users';
            $onlineUserIds = Redis::connection($redisConnection)
                ->zrangebyscore($key, $onlineThreshold, '+inf');
            $onlineUserIds = array_values(array_map(intval(...), $onlineUserIds));
            $onlineUsers = count($onlineUserIds);
        }

        $topActiveUsers = [];

        if (Schema::hasTable('pulse_aggregates') && config('pulse.enabled')) {
            $topActiveUsers = DB::table('pulse_aggregates')
                ->where('type', 'user_request')
                ->where('period', 60)
                ->where('aggregate', 'count')
                ->where('bucket', '>=', now()->subMinutes(60)->timestamp)
                ->orderByDesc('value')
                ->limit(5)
                ->get()
                ->map(function ($item): array {
                    $user = User::find((int) $item->key);

                    return [
                        'id' => $item->key,
                        'name' => $user?->name ?? 'Unknown User',
                        'email' => $user?->email ?? '',
                        'requests' => (int) $item->value,
                        'avatar' => $user?->avatar_url,
                    ];
                })
                ->values()
                ->toArray();
        }

        // Analytics Data
        $analytics = [
            'total_users' => User::count(),
            'new_users_today' => User::whereDate('created_at', Carbon::today())->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'online_users' => $onlineUsers,
            'top_active_users' => $topActiveUsers,
            'registrations_chart' => User::query()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($item): array => ['date' => $item->date, 'count' => $item->count])
                ->values()
                ->toArray(),
        ];

        return Inertia::render('administrators/users/index', [
            'users' => [
                'data' => $users,
                'total' => $users->count(),
            ],
            'analytics' => $analytics,
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
