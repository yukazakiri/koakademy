<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Support\SystemManagementPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

    public function run(): void
    {
        $this->command->info('Syncing UserRole enum with Spatie roles table...');

        foreach (UserRole::cases() as $role) {
            $roleName = $role->value;
            $roleModel = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($roleModel->wasRecentlyCreated) {
                $this->command->info("Created role: {$roleName}");
            }
        }

        $this->command->info('Roles synced successfully.');

        $this->command->info('Generating permissions from Policies...');
        $permissions = $this->generatePermissionsFromPolicies();
        $this->command->info("Generated {$permissions->count()} permissions.");

        $this->command->info('Assigning permissions to roles...');
        $this->assignPermissionsToRoles();
        $this->command->info('Permissions assigned successfully.');
    }

    private function generatePermissionsFromPolicies(): \Illuminate\Support\Collection
    {
        $policiesPath = app_path('Policies');
        $policyFiles = File::files($policiesPath);

        $createdPermissions = collect();

        foreach ($policyFiles as $file) {
            $policyName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $entityName = str_replace('Policy', '', $policyName);

            foreach (self::PERMISSION_ACTIONS as $action) {
                $permissionName = "{$action}:{$entityName}";
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'web']
                );
                $createdPermissions->push($permission);
            }
        }

        $customPermissions = $this->getCustomPermissions();
        foreach ($customPermissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );
            $createdPermissions->push($permission);
        }

        return $createdPermissions;
    }

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
            ...SystemManagementPermissions::all(),
        ];
    }

    private function assignPermissionsToRoles(): void
    {
        $rolePermissionMap = $this->getRolePermissionMap();

        foreach ($rolePermissionMap as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            $role->syncPermissions($permissions);
            $this->command->info("Assigned {$roleName}: ".count($permissions).' permissions');
        }
    }

    private function getRolePermissionMap(): array
    {
        $allPermissions = Permission::pluck('name')->toArray();
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
        $filtered = array_filter($permissions, fn ($p): bool => array_reduce($includes, fn ($carry, $i): bool => $carry || str_contains((string) $p, (string) $i), false)
        );

        if ($excludes !== []) {
            $filtered = array_filter($filtered, fn ($p): bool => ! array_reduce($excludes, fn ($carry, $e): bool => $carry || str_contains((string) $p, (string) $e), false)
            );
        }

        return array_values($filtered);
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
        ]);
    }

    private function getDeanPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student', 'Faculty', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement', 'AuditLog', 'Inventory',
            'Department', 'Room', 'Class', 'Mail',
            'ViewDashboard', 'GenerateReports',
        ], ['Delete', 'ForceDelete', 'Restore']);
    }

    private function getAssociateDeanPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student', 'Faculty', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement', 'Inventory',
            'Department', 'Room', 'Class',
            'ViewDashboard',
        ], ['Delete', 'ForceDelete']);
    }

    private function getDepartmentHeadPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Student', 'Faculty', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement',
            'Room', 'Class',
            'ViewDashboard',
        ], ['Delete', 'ForceDelete']);
    }

    private function getProgramChairPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'Course', 'Subject',
            'Enrollment', 'Event', 'Announcement',
            'Class',
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
            'QuickEnroll', 'ManageEnrollments',
            'ViewIdCard', 'VerifyIdCard',
            'ViewClearance', 'ManageClearance',
            'ViewDashboard', 'GenerateReports', 'ExportData',
        ]);
    }

    private function getAssistantRegistrarPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'ShsStudent', 'Enrollment',
            'Course', 'Subject', 'Class', 'Room',
            'Event', 'Announcement',
            'ViewIdCard', 'VerifyIdCard',
            'ViewDashboard', 'ExportData',
        ]);
    }

    private function getStudentAffairsPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student', 'Enrollment', 'Event', 'Announcement',
            'ViewIdCard', 'VerifyIdCard',
            'ViewDashboard',
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
            'View:Inventory',
            'BorrowInventory', 'ViewInventory',
            'View:Announcement', 'View:Event',
            'ViewDashboard',
        ]);
    }

    private function getCashierPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student',
            'ViewTuitionFees', 'ManageTuitionFees',
            'ProcessPayments', 'ViewPayments',
            'View:Announcement', 'View:Event',
            'ViewDashboard',
        ]);
    }

    private function getAccountingOfficerPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student',
            'ViewTuitionFees', 'ManageTuitionFees',
            'ViewPayments', 'ProcessPayments',
            'View:Announcement', 'View:Event',
            'ViewDashboard', 'GenerateReports',
        ]);
    }

    private function getBursarOfficerPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'Student',
            'ViewTuitionFees', 'ManageTuitionFees',
            'ProcessPayments', 'ViewPayments',
            'View:Announcement', 'View:Event',
            'ViewDashboard', 'GenerateReports', 'ExportData',
        ]);
    }

    private function getHRManagerPermissions(array $all): array
    {
        return $this->filterPermissions($all, [
            'User', 'Faculty',
            'View:Department',
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
