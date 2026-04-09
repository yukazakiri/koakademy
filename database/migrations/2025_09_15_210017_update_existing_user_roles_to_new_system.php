<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing users based on their names and current roles
        // This migration maps old role patterns to new specific roles

        $users = User::all();

        foreach ($users as $user) {
            $newRole = $this->determineNewRole($user);

            if ($newRole && $newRole !== $user->role) {
                $user->update(['role' => $newRole]);

                echo "Updated user '{$user->name}' from '{$user->role->value}' to '{$newRole->value}'\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert all users back to the original three roles
        DB::table('users')->update([
            'role' => DB::raw("CASE
                WHEN role IN ('developer') THEN 'developer'
                WHEN role IN ('admin', 'president', 'vice_president', 'dean', 'associate_dean') THEN 'admin'
                ELSE 'user'
            END"),
        ]);
    }

    /**
     * Determine the new role for a user based on their current role and name
     */
    private function determineNewRole(User $user): ?UserRole
    {
        $name = mb_strtolower($user->name);
        $email = mb_strtolower($user->email);

        // Keep existing Developer and Admin roles
        if ($user->role === UserRole::Developer) {
            return UserRole::Developer;
        }

        if ($user->role === UserRole::Admin) {
            return UserRole::Admin;
        }

        // Map based on name and email patterns for User role
        if ($user->role === UserRole::User) {

            // Department Heads
            if (str_contains($name, 'it head department') ||
                str_contains($name, 'it-head-dept') ||
                str_contains($email, 'it.head')) {
                return UserRole::DepartmentHead;
            }

            if (str_contains($name, 'ba head department') ||
                str_contains($name, 'ba-head-dept') ||
                str_contains($email, 'ba.head')) {
                return UserRole::DepartmentHead;
            }

            if (str_contains($name, 'hm head department') ||
                str_contains($name, 'hm-head-dept') ||
                str_contains($email, 'hm.head')) {
                return UserRole::DepartmentHead;
            }

            // Registrar
            if (str_contains($name, 'registrar') ||
                str_contains($email, 'registrar')) {
                return UserRole::Registrar;
            }

            // Cashier
            if (str_contains($name, 'cashier') ||
                str_contains($email, 'cashier')) {
                return UserRole::Cashier;
            }

            // Other specific roles based on email patterns
            if (str_contains($email, 'president')) {
                return UserRole::President;
            }

            if (str_contains($email, 'dean')) {
                return UserRole::Dean;
            }

            if (str_contains($email, 'vp.') || str_contains($email, 'vice')) {
                return UserRole::VicePresident;
            }

            if (str_contains($email, 'accounting')) {
                return UserRole::AccountingOfficer;
            }

            if (str_contains($email, 'bursar')) {
                return UserRole::BursarOfficer;
            }

            if (str_contains($email, 'hr.manager') || str_contains($name, 'hr manager')) {
                return UserRole::HRManager;
            }

            if (str_contains($email, 'student.affairs') || str_contains($name, 'student affairs')) {
                return UserRole::StudentAffairsOfficer;
            }

            if (str_contains($email, 'guidance') || str_contains($name, 'guidance')) {
                return UserRole::GuidanceCounselor;
            }

            if (str_contains($email, 'librarian') || str_contains($name, 'librarian')) {
                return UserRole::Librarian;
            }

            if (str_contains($email, 'it.support') || str_contains($name, 'it support')) {
                return UserRole::ITSupport;
            }

            if (str_contains($email, 'security') || str_contains($name, 'security')) {
                return UserRole::SecurityGuard;
            }

            if (str_contains($email, 'maintenance') || str_contains($name, 'maintenance')) {
                return UserRole::MaintenanceStaff;
            }

            if (str_contains($email, 'admin.assistant') || str_contains($name, 'administrative assistant')) {
                return UserRole::AdministrativeAssistant;
            }

            // Faculty roles based on name patterns
            if (str_contains($name, 'dr.') && ! str_contains($name, 'head')) {
                return UserRole::Professor; // Assume doctors are professors unless they're heads
            }

            if (str_contains($name, 'prof.') && ! str_contains($name, 'head')) {
                return UserRole::AssociateProfessor; // Assume professors are associate level
            }

            // Could be instructor or administrative assistant
            if ((str_contains($name, 'mr.') || str_contains($name, 'ms.')) && (str_contains($email, 'faculty') || str_contains($name, 'instructor'))) {
                return UserRole::Instructor;
            }

            // Students
            if (str_contains($email, 'student') || str_contains($name, 'student')) {
                if (str_contains($name, 'graduate') || str_contains($name, 'master')) {
                    return UserRole::GraduateStudent;
                }

                return UserRole::Student;
            }

            // Default legacy users remain as User
            return UserRole::User;
        }

        // Return null if no mapping is needed (role doesn't change)
        return null;
    }
};
