<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserSystemRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AdministratorRolesController extends Controller
{
    /**
     * Display a listing of roles and permissions.
     */
    public function index(): Response
    {
        $this->authorizeRolesAccess('ViewAny:Role');

        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        // Get all roles with their permissions
        $roles = Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'permissions_count' => $role->permissions->count(),
                'users_count' => DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->count(),
            ]);

        // Get all permissions grouped by model name
        $permissions = Permission::orderBy('name')
            ->get()
            ->groupBy(function (Permission $permission): string {
                $parts = explode(':', $permission->name);

                // If has colon, group by the second part (model name)
                if (count($parts) > 1) {
                    return $parts[1];
                }

                // For permissions without colon, group by prefix
                $prefixes = ['manage_', 'view_', 'process_', 'generate_', 'export_', 'import_', 'verify_', 'quick_', 'send_', 'borrow_', 'approve_'];

                foreach ($prefixes as $prefix) {
                    if (str_starts_with($permission->name, $prefix)) {
                        return ucfirst(str_replace('_', ' ', mb_substr($prefix, 0, -1)));
                    }
                }

                return 'Other';
            })
            ->map(function ($group, string $category): array {
                $descriptions = $this->getPermissionDescriptions();

                return [
                    'category' => $category,
                    'permissions' => $group->map(function (Permission $permission) use ($descriptions): array {
                        $parts = explode(':', $permission->name);
                        $action = $parts[0] ?? $permission->name;

                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $action,
                            'description' => $descriptions[$action] ?? $descriptions[$permission->name] ?? null,
                            'guard_name' => $permission->guard_name,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();

        // Get available roles from UserRole enum for reference
        $availableRoles = array_map(fn (UserRole $role): array => [
            'value' => $role->value,
            'label' => $role->getLabel(),
        ], UserRole::cases());

        // Get users with their roles for the role assignment modal only if user directory access is granted
        $usersWithRoles = $this->userHasAnyPermission($user, 'ViewAny:User')
            ? User::select(['id', 'name', 'email', 'role'])
                ->orderBy('name')
                ->limit(100)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'role_label' => $user->role?->getLabel(),
                ])
                ->toArray()
            : [];

        return Inertia::render('administrators/roles/index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'available_roles' => $availableRoles,
            'users_with_roles' => $usersWithRoles,
            'user' => $this->getUserProps(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRolesAccess('Create:Role');

        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        abort_if(UserSystemRoleService::fromName($validated['name']) !== null, 422, 'Cannot create role with reserved system role name.');

        if (! empty($validated['permissions'])) {
            foreach ($validated['permissions'] as $permissionName) {
                abort_unless($this->userHasAnyPermission($actor, $permissionName), 403);
            }
        }

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (! empty($validated['permissions'])) {
            $permissions = Permission::whereIn('name', $validated['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        return redirect()->route('administrators.roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Update the specified role and its permissions.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeRolesAccess('Update:Role');

        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $systemRole = UserSystemRoleService::fromName($role->name);
        if ($systemRole !== null) {
            abort_if($validated['name'] !== $role->name, 403, "Cannot rename system role '{$role->name}'.");
            abort_unless($actor->role !== null && in_array($systemRole, $actor->role->getManageableRoles(), true), 403, "Cannot modify system role '{$role->name}'.");
        } else {
            abort_if(UserSystemRoleService::fromName($validated['name']) !== null, 422, 'Cannot rename role to reserved system role name.');
        }

        $memberIds = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->pluck('model_id');
        if ($memberIds->isNotEmpty()) {
            $members = User::whereIn('id', $memberIds)->get();
            foreach ($members as $member) {
                abort_unless($actor->hasHigherAuthorityThan($member), 403, "Cannot modify role assigned to user '{$member->name}' of equal or higher authority.");
            }
        }

        if (isset($validated['permissions'])) {
            foreach ($validated['permissions'] as $permissionName) {
                abort_unless($this->userHasAnyPermission($actor, $permissionName), 403);
            }
        }

        $role->update([
            'name' => $validated['name'],
        ]);

        if (isset($validated['permissions'])) {
            $permissions = Permission::whereIn('name', $validated['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        return redirect()->route('administrators.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): Response
    {
        $this->authorizeRolesAccess('Update:Role');

        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $systemRole = UserSystemRoleService::fromName($role->name);
        if ($systemRole !== null) {
            abort_unless($actor->role !== null && in_array($systemRole, $actor->role->getManageableRoles(), true), 403, "Cannot edit system role '{$role->name}'.");
        }

        $memberIds = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->pluck('model_id');
        if ($memberIds->isNotEmpty()) {
            $members = User::whereIn('id', $memberIds)->get();
            foreach ($members as $member) {
                abort_unless($actor->hasHigherAuthorityThan($member), 403, "Cannot edit role assigned to user '{$member->name}' of equal or higher authority.");
            }
        }

        $role->load('permissions');

        $permissions = Permission::orderBy('name')
            ->get()
            ->groupBy(function (Permission $permission): string {
                $parts = explode(':', $permission->name);

                // If has colon, group by the second part (model name)
                if (count($parts) > 1) {
                    return $parts[1];
                }

                // For permissions without colon, group by prefix
                $prefixes = ['manage_', 'view_', 'process_', 'generate_', 'export_', 'import_', 'verify_', 'quick_', 'send_', 'borrow_', 'approve_'];

                foreach ($prefixes as $prefix) {
                    if (str_starts_with($permission->name, $prefix)) {
                        return ucfirst(str_replace('_', ' ', mb_substr($prefix, 0, -1)));
                    }
                }

                return 'Other';
            })
            ->map(function ($group, string $category): array {
                $descriptions = $this->getPermissionDescriptions();

                return [
                    'category' => $category,
                    'permissions' => $group->map(function (Permission $permission) use ($descriptions): array {
                        $parts = explode(':', $permission->name);
                        $action = $parts[0] ?? $permission->name;

                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $action,
                            'description' => $descriptions[$action] ?? $descriptions[$permission->name] ?? null,
                            'guard_name' => $permission->guard_name,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();

        $currentPermissions = $role->permissions->pluck('name')->toArray();

        return Inertia::render('administrators/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $currentPermissions,
            ],
            'permissions' => $permissions,
            'user' => $this->getUserProps(),
        ]);
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->authorizeRolesAccess('Delete:Role');

        abort_if(UserSystemRoleService::fromName($role->name) !== null, 403, "Cannot delete system role '{$role->name}'.");

        // Check if role has users
        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        if ($usersCount > 0) {
            return redirect()->route('administrators.roles.index')
                ->with('error', "Cannot delete role '{$role->name}' because it has {$usersCount} user(s) assigned to it.");
        }

        $role->delete();

        return redirect()->route('administrators.roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * Assign a role to the specified user.
     */
    public function assignRole(Request $request): RedirectResponse
    {
        $this->authorizeRolesAccess('Assign:Role');

        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_name' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $role = Role::where('name', $validated['role_name'])->firstOrFail();

        abort_unless($actor->hasHigherAuthorityThan($user), 403);

        $systemRole = UserSystemRoleService::fromName($role->name);
        if ($systemRole instanceof UserRole) {
            abort_unless($actor->role !== null && in_array($systemRole, $actor->role->getManageableRoles(), true), 403);
            app(UserSystemRoleService::class)->assign($user, $systemRole);
            if ($role->name !== $systemRole->value) {
                foreach ($role->permissions as $permission) {
                    abort_unless($this->userHasAnyPermission($actor, $permission->name), 403);
                }
                $user->assignRole($role);
                $user->unsetRelation('roles');
            }
        } else {
            foreach ($role->permissions as $permission) {
                abort_unless($this->userHasAnyPermission($actor, $permission->name), 403);
            }

            if ($user->role !== null) {
                app(UserSystemRoleService::class)->assign($user, $user->role, [$role->id]);
            } else {
                $user->syncRoles([$role]);
            }
        }

        return redirect()->route('administrators.roles.index')
            ->with('success', "Role '{$role->name}' assigned to {$user->name}.");
    }

    /**
     * Create a new permission.
     */
    public function createPermission(Request $request): RedirectResponse
    {
        $this->authorizeRolesAccess('Create:Permission');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        return redirect()->route('administrators.roles.index')
            ->with('success', 'Permission created successfully.');
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroyPermission(Permission $permission): RedirectResponse
    {
        $this->authorizeRolesAccess('Delete:Permission');

        $permission->delete();

        return redirect()->route('administrators.roles.index')
            ->with('success', 'Permission deleted successfully.');
    }

    /**
     * Retrieve shared user properties for Inertia responses.
     *
     * @return array<string, mixed>
     */
    private function getUserProps(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        /** @var User $user */
        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->value ?? 'user',
            'role_label' => $user->role?->getLabel() ?? 'User',
            'permissions' => $permissions,
        ];
    }

    /**
     * Authorize access against the required permission or permissions.
     *
     * @param  string|array<int, string>  $permission
     */
    private function authorizeRolesAccess(string|array $permission = 'ViewAny:Role'): void
    {
        $user = Auth::user();

        $this->abortUnlessUserHasAnyPermission($user instanceof User ? $user : null, $permission);
    }

    /**
     * Get permission action and name descriptions for display.
     *
     * @return array<string, string>
     */
    private function getPermissionDescriptions(): array
    {
        return [
            // Standard actions
            'ViewAny' => 'View the list of all records',
            'View' => 'View individual record details',
            'Create' => 'Create new records',
            'Update' => 'Edit existing records',
            'Delete' => 'Delete records (soft delete)',
            'Restore' => 'Restore deleted records',
            'ForceDelete' => 'Permanently delete records',
            'ForceDeleteAny' => 'Permanently delete any records',
            'RestoreAny' => 'Restore any deleted records',
            'Replicate' => 'Duplicate records',
            'Reorder' => 'Change display order of records',
            'Assign' => 'Assign records or roles',
            'Assign:Role' => 'Assign roles to users',
            // Custom permissions
            'manage_settings' => 'Access and modify system settings',
            'manage_school' => 'Manage school information and configuration',
            'manage_enrollments' => 'Handle student enrollment processes',
            'quick_enroll' => 'Enroll students quickly without full process',
            'view_tuition_fees' => 'View tuition and fee information',
            'manage_tuition_fees' => 'Create and modify tuition fee structures',
            'process_payments' => 'Process payment transactions',
            'view_payments' => 'View payment history and records',
            'manage_clearance' => 'Manage student clearance status',
            'view_clearance' => 'View clearance information',
            'generate_reports' => 'Generate various system reports',
            'export_data' => 'Export data from the system',
            'import_data' => 'Import data into the system',
            'manage_inventory' => 'Manage inventory items',
            'borrow_inventory' => 'Borrow inventory items',
            'approve_borrowing' => 'Approve inventory borrowing requests',
            'manage_mail' => 'Manage email communications',
            'view_mail' => 'View email messages',
            'send_mail' => 'Send email messages',
            'manage_announcements' => 'Create and manage announcements',
            'view_announcements' => 'View announcements',
            'manage_events' => 'Create and manage events',
            'view_events' => 'View events',
            'manage_class_schedules' => 'Manage class schedules',
            'view_class_schedules' => 'View class schedules',
            'manage_subjects' => 'Manage subject/course catalog',
            'view_subjects' => 'View subject information',
            'manage_courses' => 'Manage course offerings',
            'view_courses' => 'View course information',
            'manage_faculty' => 'Manage faculty/staff records',
            'view_faculty' => 'View faculty information',
            'manage_departments' => 'Manage departments',
            'view_departments' => 'View department information',
            'manage_rooms' => 'Manage room/location resources',
            'view_rooms' => 'View room availability',
            'manage_account' => 'Manage user accounts',
            'view_account' => 'View account details',
            'view_id_card' => 'View student ID card information',
            'manage_id_card' => 'Manage student ID cards',
            'verify_id_card' => 'Verify ID card authenticity',
            'view_onboarding' => 'View onboarding content',
            'manage_onboarding' => 'Manage onboarding features',
            'manage_tokens' => 'Manage access tokens',
            'view_tokens' => 'View access tokens',
            'view_dashboard' => 'Access the dashboard',
            'view_audit_logs' => 'View system audit logs',
        ];
    }
}
