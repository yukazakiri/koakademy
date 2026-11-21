<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModulePermissionService;
use App\Support\SystemManagementPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;

final class RolesSeeder extends Seeder
{
    private const array PERMISSION_ACTIONS = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'Restore',
        'ForceDelete',
        'ForceDeleteAny',
        'RestoreAny',
        'Replicate',
        'Reorder',
    ];

    /**
     * Legacy include/exclude tokens mapped to the permission names that actually exist.
     *
     * The per-role permission lists below read as intent ("ViewDashboard", "ViewPayments").
     * Those tokens are NOT literal permission names: permissions are generated as either
     * `Action:Entity` (from app/Policies and each module's app/Policies) or snake_case (from
     * getCustomPermissions()). Without this map, str_contains() silently matched nothing, so
     * roles such as cashier, hr_manager and guidance_counselor were granted far fewer
     * permissions than intended.
     *
     * A token mapped to [] can never match, and is reported by self::unresolvedTokens().
     *
     * @var array<string, array<int, string>>
     */
    private const array PERMISSION_ALIASES = [
        'ViewDashboard' => ['view_dashboard'],
        'GenerateReports' => ['generate_reports'],
        'ExportData' => ['export_data'],
        'ImportData' => ['import_data'],
        'ViewPayments' => ['view_payments'],
        'ProcessPayments' => ['process_payments'],
        'ViewClearance' => ['view_clearance'],
        'ManageClearance' => ['manage_clearance'],
        'ManageEnrollments' => ['manage_enrollments'],
        'QuickEnroll' => ['quick_enroll'],
        'ViewAuditLog' => ['view_audit_logs'],
        'ViewSettings' => ['ViewAny:GeneralSetting'],
        'ManageSettings' => ['Update:GeneralSetting'],
        'ViewInventory' => ['ViewAny:InventoryProduct', 'ViewAny:InventoryCategory'],
        'ManageInventory' => ['Create:InventoryProduct', 'Update:InventoryProduct'],
        'BorrowInventory' => ['borrow_inventory'],
        'ViewIdCard' => ['view_id_card'],
        'VerifyIdCard' => ['verify_id_card'],
        // No ResearchPaperPolicy exists in Modules/LibrarySystem, so no permission is
        // generated for it. Borrow records are the enforceable library-research grant.
        'ResearchPaper' => ['ViewAny:BorrowRecord'],
    ];

    /**
     * Include/exclude tokens that resolve to no real permission. Kept explicit so the intent
     * stays visible and the gap is discoverable instead of silently granting nothing.
     *
     * @var array<int, string>
     */
    private const array KNOWN_UNRESOLVED_TOKENS = [
        'View:Inventory',
    ];

    /**
     * Report include tokens that match no permission at all, so a typo like "ViewDashbord"
     * cannot quietly strip a role's access.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    public static function unresolvedTokens(array $permissions, array $tokens): array
    {
        return array_values(array_filter($tokens, function (string $token) use ($permissions): bool {
            if (in_array($token, self::KNOWN_UNRESOLVED_TOKENS, true)) {
                return false;
            }

            foreach ($permissions as $permission) {
                if (str_contains((string) $permission, $token)) {
                    return false;
                }

                if (array_key_exists($token, self::PERMISSION_ALIASES)
                    && in_array((string) $permission, self::PERMISSION_ALIASES[$token], true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function run(): void
    {
        $this->command->info('Syncing UserRole enum with Spatie roles table...');

        $rolesTable = $this->configString('permission.table_names.roles', 'roles');
        $roleNames = array_map(static fn (UserRole $role): string => $role->value, UserRole::cases());

        foreach ($this->insertMissingWebNames($rolesTable, $roleNames) as $roleName) {
            $this->command->info("Created role: {$roleName}");
        }

        $this->command->info('Roles synced successfully.');

        $this->command->info('Generating permissions from Policies...');
        $permissionsCount = $this->generatePermissionsFromPolicies();
        $this->command->info("Generated {$permissionsCount} permissions.");

        $this->command->info('Assigning permissions to roles...');
        [$allPermissions, $rolePermissionMap] = $this->assignPermissionsToRoles();
        $this->command->info('Permissions assigned successfully.');

        $this->reportUnresolvedTokens($allPermissions, $rolePermissionMap);
    }

    private function generatePermissionsFromPolicies(): int
    {
        $policiesPath = app_path('Policies');
        $policyFiles = File::files($policiesPath);

        foreach (app(ModuleManifestRepository::class)->installed() as $module) {
            $modulePolicyPath = $module->path.'/app/Policies';
            if (File::isDirectory($modulePolicyPath)) {
                $policyFiles = array_merge($policyFiles, File::files($modulePolicyPath));
            }
        }

        $candidatePermissions = [];

        foreach ($policyFiles as $file) {
            $policyName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $entityName = str_replace('Policy', '', $policyName);

            foreach (self::PERMISSION_ACTIONS as $action) {
                $candidatePermissions[] = "{$action}:{$entityName}";
            }
        }

        foreach ($this->getCustomPermissions() as $permissionName) {
            $candidatePermissions[] = $permissionName;
        }

        $candidatePermissions = array_values(array_unique($candidatePermissions));

        $permissionsTable = $this->configString('permission.table_names.permissions', 'permissions');
        $this->insertMissingWebNames($permissionsTable, $candidatePermissions);

        $modulePermissions = app(ModulePermissionService::class)->sync();

        return count(array_unique([...$candidatePermissions, ...$modulePermissions]));
    }

    /**
     * @return array<int, string>
     */
    private function getCustomPermissions(): array
    {
        return [
            'view_dashboard',
            'view_audit_logs',
            'manage_settings',
            'manage_school',
            'manage_enrollments',
            'quick_enroll',
            'view_tuition_fees',
            'manage_tuition_fees',
            'process_payments',
            'view_payments',
            'manage_clearance',
            'view_clearance',
            'generate_reports',
            'export_data',
            'import_data',
            'manage_inventory',
            'borrow_inventory',
            'approve_borrowing',
            'manage_mail',
            'view_mail',
            'send_mail',
            'manage_announcements',
            'view_announcements',
            'manage_events',
            'view_events',
            'manage_class_schedules',
            'view_class_schedules',
            'manage_subjects',
            'view_subjects',
            'manage_courses',
            'view_courses',
            'manage_faculty',
            'view_faculty',
            'manage_departments',
            'view_departments',
            'manage_rooms',
            'view_rooms',
            'manage_account',
            'view_account',
            'view_id_card',
            'manage_id_card',
            'verify_id_card',
            'view_onboarding',
            'manage_onboarding',
            'manage_tokens',
            'view_tokens',
            // View:Cashier is referenced by AdministratorFinanceController::authorizeFinanceAccess(),
            // ProfileController, UpdatePaymentWorkspacePreferencesRequest, the MCP
            // GetStatementOfAccountTool and 6 finance nav entries. There is no CashierPolicy, so
            // the Action:Entity generator never produced it and every one of those call sites
            // 403'd for all non-super-admins. Registered here so the finance section works.
            'View:Cashier',
            'page_Cashier',
            'page_GeneralSetting',
            'View:GeneralSettings',
            // Cancellation and reopening are separately authorized administrative operations on an
            // enrollment (EnrollmentCancellationService, EnrollmentTransitionEngine::reopen). No
            // policy generates them, so they are registered here for Registrar roles to hold.
            'Cancel:StudentEnrollment',
            'Reopen:StudentEnrollment',
            'Assign:Role',
            'Update:ClassEnrollment',
            ...SystemManagementPermissions::all(),
        ];
    }

    /**
     * @return array{0: list<string>, 1: array<string, array<mixed>>}
     */
    private function assignPermissionsToRoles(): array
    {
        $rolesTable = $this->configString('permission.table_names.roles', 'roles');
        $permissionsTable = $this->configString('permission.table_names.permissions', 'permissions');
        $pivotTable = $this->configString('permission.table_names.role_has_permissions', 'role_has_permissions');
        $pivotRole = $this->configString('permission.column_names.role_pivot_key', 'role_id');
        $pivotPermission = $this->configString('permission.column_names.permission_pivot_key', 'permission_id');

        /** @var array<string, int> $permissionIdsByName */
        $permissionIdsByName = DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->pluck('id', 'name')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->all();

        $allPermissions = array_map(
            static fn (int|string $name): string => (string) $name,
            array_keys($permissionIdsByName),
        );

        /** @var array<string, int> $roleIdsByName */
        $roleIdsByName = DB::table($rolesTable)
            ->where('guard_name', 'web')
            ->pluck('id', 'name')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->all();

        $rolePermissionMap = $this->getRolePermissionMap($allPermissions);

        $pivotRows = [];
        $roleIdsToSync = [];

        foreach ($rolePermissionMap as $roleName => $permissions) {
            if (! isset($roleIdsByName[$roleName])) {
                continue;
            }

            $roleId = $roleIdsByName[$roleName];
            $roleIdsToSync[] = $roleId;

            $seenPermissions = [];
            foreach ($permissions as $permissionName) {
                if (! is_string($permissionName) || isset($seenPermissions[$permissionName]) || ! isset($permissionIdsByName[$permissionName])) {
                    continue;
                }

                $seenPermissions[$permissionName] = true;
                $pivotRows[] = [
                    $pivotPermission => $permissionIdsByName[$permissionName],
                    $pivotRole => $roleId,
                ];
            }

            $this->command->info("Assigned {$roleName}: ".count($permissions).' permissions');
        }

        if ($roleIdsToSync !== []) {
            DB::transaction(static function () use ($pivotTable, $pivotRole, $roleIdsToSync, $pivotRows): void {
                DB::table($pivotTable)->whereIn($pivotRole, $roleIdsToSync)->delete();

                foreach (array_chunk($pivotRows, 400) as $chunk) {
                    DB::table($pivotTable)->insert($chunk);
                }
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$allPermissions, $rolePermissionMap];
    }

    /**
     * @param  array<int, string>  $allPermissions
     * @return array<string, array<mixed>>
     */
    private function getRolePermissionMap(array $allPermissions): array
    {
        $adminPermissions = $allPermissions;
        $studentPermissions = [];

        return [
            UserRole::Developer->value => $adminPermissions,
            UserRole::SuperAdmin->value => $adminPermissions,
            UserRole::Admin->value => $adminPermissions,
            UserRole::President->value => $this->getPresidentPermissions($allPermissions),
            UserRole::VicePresident->value => $this->getVicePresidentPermissions($allPermissions),
            UserRole::Dean->value => $this->getDeanPermissions($allPermissions),
            UserRole::AssociateDean->value => $this->getAssociateDeanPermissions($allPermissions),
            UserRole::DepartmentHead->value => $this->getDepartmentHeadPermissions($allPermissions),
            UserRole::ProgramChair->value => $this->getProgramChairPermissions($allPermissions),
            UserRole::Professor->value => $this->getFacultyPermissions($allPermissions),
            UserRole::AssociateProfessor->value => $this->getFacultyPermissions($allPermissions),
            UserRole::AssistantProfessor->value => $this->getFacultyPermissions($allPermissions),
            UserRole::Instructor->value => $this->getFacultyPermissions($allPermissions),
            UserRole::PartTimeFaculty->value => $this->getPartTimeFacultyPermissions($allPermissions),
            UserRole::Registrar->value => $this->getRegistrarPermissions($allPermissions),
            UserRole::AssistantRegistrar->value => $this->getAssistantRegistrarPermissions($allPermissions),
            UserRole::StudentAffairsOfficer->value => $this->getStudentAffairsPermissions($allPermissions),
            UserRole::GuidanceCounselor->value => $this->getGuidanceCounselorPermissions($allPermissions),
            UserRole::Librarian->value => $this->getLibrarianPermissions($allPermissions),
            UserRole::Cashier->value => $this->getCashierPermissions($allPermissions),
            UserRole::AccountingOfficer->value => $this->getAccountingOfficerPermissions($allPermissions),
            UserRole::BursarOfficer->value => $this->getBursarOfficerPermissions($allPermissions),
            UserRole::HRManager->value => $this->getHRManagerPermissions($allPermissions),
            UserRole::ITSupport->value => $this->getITSupportPermissions($allPermissions),
            UserRole::SecurityGuard->value => $this->getSecurityGuardPermissions($allPermissions),
            UserRole::MaintenanceStaff->value => $this->getMaintenanceStaffPermissions($allPermissions),
            UserRole::AdministrativeAssistant->value => $this->getAdministrativeAssistantPermissions($allPermissions),
            UserRole::Student->value => $studentPermissions,
            UserRole::GraduateStudent->value => $studentPermissions,
            UserRole::ShsStudent->value => $studentPermissions,
            UserRole::User->value => $studentPermissions,
        ];
    }

    private function filterPermissions(array $permissions, array $includes, array $excludes = []): array
    {
        $filtered = array_filter($permissions, fn ($p): bool => array_reduce($includes, fn ($carry, $i): bool => $carry || $this->tokenMatches((string) $p, (string) $i), false)
        );

        if ($excludes !== []) {
            $filtered = array_filter($filtered, fn ($p): bool => ! array_reduce($excludes, fn ($carry, $e): bool => $carry || $this->tokenMatches((string) $p, (string) $e), false)
            );
        }

        return array_values($filtered);
    }

    /**
     * An aliased token matches by exact permission name, so one token cannot accidentally
     * match several unrelated permissions. Non-aliased tokens keep the original substring
     * behaviour (e.g. 'User' -> ViewAny:User, View:User, Create:User, ...).
     */
    private function tokenMatches(string $permission, string $token): bool
    {
        if (array_key_exists($token, self::PERMISSION_ALIASES)) {
            return in_array($permission, self::PERMISSION_ALIASES[$token], true);
        }

        return str_contains($permission, $token);
    }

    /**
     * Warn about any permission token used by a role that resolves to nothing, so a typo
     * cannot quietly strip access. Reads the real get*Permissions() lists by reflection so
     * this check can never drift from the definitions it audits.
     *
     * @param  array<int, string>  $allPermissions
     * @param  array<string, array<mixed>>  $rolePermissionMap
     */
    private function reportUnresolvedTokens(array $allPermissions, array $rolePermissionMap): void
    {
        foreach ($rolePermissionMap as $roleName => $_) {
            $method = $this->permissionMethodFor((string) $roleName);

            if ($method === null) {
                continue;
            }

            [$includes, $excludes] = $this->extractTokens($method);

            $unresolved = self::unresolvedTokens($allPermissions, $includes);

            if ($unresolved !== []) {
                $this->command->warn(sprintf(
                    '  %s: include tokens matching no permission -> %s',
                    $roleName,
                    implode(', ', $unresolved),
                ));
            }

            $ignored = array_values(array_intersect($excludes, self::KNOWN_UNRESOLVED_TOKENS));

            if ($ignored !== []) {
                $this->command->warn(sprintf(
                    '  %s: unresolved exclude tokens -> %s',
                    $roleName,
                    implode(', ', $ignored),
                ));
            }
        }
    }

    /**
     * Resolve the get*Permissions() builder method backing a role name.
     */
    private function permissionMethodFor(string $roleName): ?string
    {
        $method = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $roleName))).'Permissions';

        return method_exists($this, $method) ? $method : null;
    }

    /**
     * Pull the literal include/exclude token arrays out of a get*Permissions() method.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function extractTokens(string $method): array
    {
        $includes = [];
        $excludes = [];

        foreach ((new ReflectionMethod($this, $method))->getParameters() as $parameter) {
            if (! $parameter->isDefaultValueAvailable()) {
                continue;
            }

            $defaults = $parameter->getDefaultValue();

            if (! is_array($defaults)) {
                continue;
            }

            // get*Permissions(array $all) declares only $includes and $excludes; $all has no
            // default and is skipped above.
            if ($parameter->getPosition() === 1) {
                $excludes = $defaults;
            } else {
                $includes = $defaults;
            }
        }

        return [$includes, $excludes];
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Batch-insert missing guard='web' role or permission names and return the inserted names.
     *
     * @param  array<int, string>  $names
     * @return list<string>
     */
    private function insertMissingWebNames(string $table, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $existing = DB::table($table)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->map(static fn (mixed $name): string => is_string($name) ? $name : '')
            ->all();

        $missing = array_values(array_diff(array_unique($names), $existing));

        if ($missing === []) {
            return [];
        }

        $now = now();
        $rows = array_map(
            static fn (string $name): array => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $missing,
        );

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        return $missing;
    }

    private function getPresidentPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'ViewAny:User', 'View:User',
            'ViewAny:Student', 'View:Student',
            'ViewAny:Faculty', 'View:Faculty',
            'ViewAny:Department', 'View:Department',
            'ViewAny:Course', 'View:Course',
            'ViewAny:Subject', 'View:Subject',
            'ViewAny:Enrollment', 'View:Enrollment',
            'ViewAny:Event', 'View:Event',
            'ViewAny:Announcement', 'View:Announcement',
            'ViewAny:AuditLog',
            'ViewAny:Inventory',
            'ViewAny:Mail',
            'ViewAny:Role',
            'GenerateReports', 'ViewDashboard',
            'ExportData',
        ]);
    }

    private function getVicePresidentPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'ViewAny:User', 'View:User',
            'ViewAny:Student', 'View:Student',
            'ViewAny:Faculty', 'View:Faculty',
            'ViewAny:Department', 'View:Department',
            'ViewAny:Course', 'View:Course',
            'ViewAny:Subject', 'View:Subject',
            'ViewAny:Enrollment', 'View:Enrollment',
            'ViewAny:Event', 'View:Event',
            'ViewAny:Announcement', 'View:Announcement',
            'ViewAny:AuditLog',
            'ViewAny:Inventory',
            'ViewAny:Mail',
            'ViewAny:Role',
            'GenerateReports', 'ViewDashboard',
            'ExportData',
        ]);
    }

    private function getDeanPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student', 'Faculty', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement', 'AuditLog', 'Inventory',
            'Department', 'Room', 'Class', 'Mail',
            'IndustryCourseCode', 'CodeAuthority',
            'ViewDashboard', 'GenerateReports',
        ], ['Delete', 'ForceDelete', 'Restore']);
    }

    private function getAssociateDeanPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student', 'Faculty', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement', 'Inventory',
            'Department', 'Room', 'Class',
            'IndustryCourseCode', 'CodeAuthority',
            'ViewDashboard', 'GenerateReports',
        ], ['Delete', 'ForceDelete']);
    }

    private function getDepartmentHeadPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student', 'Faculty', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement',
            'Room', 'Class',
            'IndustryCourseCode', 'CodeAuthority',
            'ViewDashboard',
        ], ['Delete', 'ForceDelete']);
    }

    private function getProgramChairPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement',
            'Faculty', 'Class',
            'IndustryCourseCode', 'CodeAuthority',
            'ViewDashboard',
        ]);
    }

    private function getFacultyPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'View:Student', 'View:Subject', 'View:Course',
            'View:Enrollment', 'View:Event', 'View:Announcement',
            'View:Class', 'View:Room',
            'ViewDashboard',
        ]);
    }

    private function getPartTimeFacultyPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'View:Student', 'View:Subject', 'View:Course',
            'View:Enrollment', 'View:Event', 'View:Announcement',
            'View:Class', 'View:Room',
            'ViewDashboard',
        ]);
    }

    private function getRegistrarPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'ShsStudent', 'Enrollment',
            'Course', 'Subject', 'Class', 'Room',
            'Event', 'Announcement',
            'IndustryCourseCode', 'CodeAuthority',
            'QuickEnroll', 'ManageEnrollments',
            'ViewIdCard', 'VerifyIdCard',
            'ViewClearance', 'ManageClearance',
            'ViewDashboard', 'GenerateReports', 'ExportData', 'ImportData',
        ]);
    }

    private function getAssistantRegistrarPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'ShsStudent', 'Enrollment',
            'Course', 'Subject', 'Class', 'Room',
            'Event', 'Announcement',
            'IndustryCourseCode', 'CodeAuthority',
            'ViewIdCard', 'VerifyIdCard',
            'ViewClearance',
            'ViewDashboard', 'ExportData', 'ImportData',
        ]);
    }

    private function getStudentAffairsPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'Enrollment', 'Event', 'Announcement',
            'ViewIdCard', 'VerifyIdCard',
            'ViewClearance', 'ManageClearance',
            'ViewDashboard',
        ], [
            'ClassEnrollment',
        ]);
    }

    private function getGuidanceCounselorPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'View:Enrollment',
            'View:Announcement', 'ViewClearance', 'ManageClearance',
            'ViewDashboard',
        ]);
    }

    private function getLibrarianPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student',
            'Book', 'Author', 'Category', 'BorrowRecord', 'ResearchPaper',
            'View:Inventory',
            'BorrowInventory', 'ViewInventory',
            'View:Announcement', 'View:Event',
            'ViewDashboard',
        ]);
    }

    /**
     * Get permission list for the cashier role.
     *
     * @param  array<int, string>  $all
     * @return array<int, string>
     */
    private function getCashierPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student',
            'view_tuition_fees', 'manage_tuition_fees',
            'ProcessPayments', 'ViewPayments',
            'View:Cashier', 'page_Cashier',
            'View:Announcement', 'View:Event',
            'ViewDashboard',
        ]);
    }

    /**
     * Get permission list for the accounting officer role.
     *
     * @param  array<int, string>  $all
     * @return array<int, string>
     */
    private function getAccountingOfficerPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student',
            'view_tuition_fees', 'manage_tuition_fees',
            'ViewPayments', 'ProcessPayments',
            'View:Cashier', 'page_Cashier',
            'View:Announcement', 'View:Event',
            'ViewDashboard', 'GenerateReports',
        ]);
    }

    /**
     * Get permission list for the bursar officer role.
     *
     * @param  array<int, string>  $all
     * @return array<int, string>
     */
    private function getBursarOfficerPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student',
            'view_tuition_fees', 'manage_tuition_fees',
            'ProcessPayments', 'ViewPayments',
            'View:Cashier', 'page_Cashier',
            'View:Announcement', 'View:Event',
            'ViewDashboard', 'GenerateReports', 'ExportData',
        ]);
    }

    private function getHRManagerPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Faculty',
            'ViewAny:Department', 'View:Department',
            'View:Announcement', 'Manage:Announcement',
            'View:Event', 'Manage:Event',
            'ViewAuditLog',
            'ViewDashboard', 'GenerateReports',
        ]);
    }

    private function getITSupportPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User',
            'View:Inventory', 'ManageInventory',
            'View:Announcement', 'Manage:Announcement',
            'View:Event', 'Manage:Event',
            'ViewSettings', 'ManageSettings',
            'ViewDashboard',
        ]);
    }

    private function getSecurityGuardPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'View:Student', 'VerifyIdCard',
            'View:Announcement',
            'ViewDashboard',
        ]);
    }

    private function getMaintenanceStaffPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'View:Inventory',
            'View:Announcement',
            'ViewDashboard',
        ]);
    }

    private function getAdministrativeAssistantPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student',
            'View:Faculty', 'View:Department',
            'View:Course', 'View:Subject', 'View:Class', 'View:Room',
            'View:Event', 'Manage:Event',
            'View:Announcement', 'Manage:Announcement',
            'View:Mail', 'Manage:Mail',
            'ViewDashboard',
        ]);
    }
}
