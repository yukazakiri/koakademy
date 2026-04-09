<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

final class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create System Developer account
        $this->createUserWithRole(
            'System Developer',
            'developer@koakademy.edu',
            UserRole::Developer
        );

        // Create System Administrators
        $this->createUserWithRole(
            'System Administrator',
            'admin@koakademy.edu',
            UserRole::Admin
        );

        $this->createUserWithRole(
            'IT Administrator',
            'it.admin@koakademy.edu',
            UserRole::Admin
        );

        // Create University Leadership
        $this->createUserWithRole(
            'Dr. Maria Santos',
            'president@koakademy.edu',
            UserRole::President
        );

        $this->createUserWithRole(
            'Dr. Juan dela Cruz',
            'vp.academic@koakademy.edu',
            UserRole::VicePresident
        );

        // Create Academic Administration
        $this->createUserWithRole(
            'Dr. Ana Reyes',
            'dean.engineering@koakademy.edu',
            UserRole::Dean
        );

        $this->createUserWithRole(
            'Dr. Pedro Martinez',
            'dean.business@koakademy.edu',
            UserRole::Dean
        );

        $this->createUserWithRole(
            'Prof. Lisa Garcia',
            'associate.dean@koakademy.edu',
            UserRole::AssociateDean
        );

        // Create Department Heads
        $this->createUserWithRole(
            'Prof. Mark Johnson',
            'it.head@koakademy.edu',
            UserRole::DepartmentHead
        );

        $this->createUserWithRole(
            'Prof. Sarah Wilson',
            'ba.head@koakademy.edu',
            UserRole::DepartmentHead
        );

        $this->createUserWithRole(
            'Prof. Robert Brown',
            'hm.head@koakademy.edu',
            UserRole::DepartmentHead
        );

        // Create Program Chairs
        $this->createUserWithRole(
            'Prof. Emily Davis',
            'cs.chair@koakademy.edu',
            UserRole::ProgramChair
        );

        $this->createUserWithRole(
            'Prof. Michael Lee',
            'it.chair@koakademy.edu',
            UserRole::ProgramChair
        );

        // Create Faculty Members
        $this->createUserWithRole(
            'Dr. Jennifer Adams',
            'j.adams@koakademy.edu',
            UserRole::Professor
        );

        $this->createUserWithRole(
            'Dr. David Miller',
            'd.miller@koakademy.edu',
            UserRole::AssociateProfessor
        );

        $this->createUserWithRole(
            'Prof. Amanda Taylor',
            'a.taylor@koakademy.edu',
            UserRole::AssistantProfessor
        );

        $this->createUserWithRole(
            'Mr. Kevin White',
            'k.white@koakademy.edu',
            UserRole::Instructor
        );

        $this->createUserWithRole(
            'Ms. Rachel Green',
            'r.green@koakademy.edu',
            UserRole::PartTimeFaculty
        );

        // Create Student Services Staff
        $this->createUserWithRole(
            'Ms. Grace Santos',
            'registrar@koakademy.edu',
            UserRole::Registrar
        );

        $this->createUserWithRole(
            'Ms. Helen Cruz',
            'asst.registrar@koakademy.edu',
            UserRole::AssistantRegistrar
        );

        $this->createUserWithRole(
            'Ms. Joyce Lopez',
            'student.affairs@koakademy.edu',
            UserRole::StudentAffairsOfficer
        );

        $this->createUserWithRole(
            'Ms. Patricia Morales',
            'guidance@koakademy.edu',
            UserRole::GuidanceCounselor
        );

        $this->createUserWithRole(
            'Mr. Daniel Torres',
            'librarian@koakademy.edu',
            UserRole::Librarian
        );

        // Create Finance & Administration Staff
        $this->createUserWithRole(
            'Ms. Carmen Rodriguez',
            'cashier@koakademy.edu',
            UserRole::Cashier
        );

        $this->createUserWithRole(
            'Mr. Antonio Perez',
            'accounting@koakademy.edu',
            UserRole::AccountingOfficer
        );

        $this->createUserWithRole(
            'Ms. Rosa Gonzalez',
            'bursar@koakademy.edu',
            UserRole::BursarOfficer
        );

        $this->createUserWithRole(
            'Ms. Isabel Flores',
            'hr.manager@koakademy.edu',
            UserRole::HRManager
        );

        // Create Support Staff
        $this->createUserWithRole(
            'Mr. Carlos Mendoza',
            'it.support@koakademy.edu',
            UserRole::ITSupport
        );

        $this->createUserWithRole(
            'Mr. Ricardo Herrera',
            'security1@koakademy.edu',
            UserRole::SecurityGuard
        );

        $this->createUserWithRole(
            'Mr. Fernando Silva',
            'maintenance1@koakademy.edu',
            UserRole::MaintenanceStaff
        );

        $this->createUserWithRole(
            'Ms. Victoria Ramos',
            'admin.assistant1@koakademy.edu',
            UserRole::AdministrativeAssistant
        );

        $this->createUserWithRole(
            'Ms. Luz Castillo',
            'admin.assistant2@koakademy.edu',
            UserRole::AdministrativeAssistant
        );

        // Create Sample Students
        $this->createUserWithRole(
            'John Student',
            'john.student@student.koakademy.edu',
            UserRole::Student
        );

        $this->createUserWithRole(
            'Jane Doe',
            'jane.doe@student.koakademy.edu',
            UserRole::Student
        );

        $this->createUserWithRole(
            'Master Student',
            'master.student@student.koakademy.edu',
            UserRole::GraduateStudent
        );

        // Keep legacy users for backward compatibility
        $this->createUserWithRole(
            'Legacy User 1',
            'legacy1@koakademy.edu',
            UserRole::User
        );

        $this->createUserWithRole(
            'Legacy User 2',
            'legacy2@koakademy.edu',
            UserRole::User
        );

        $this->command->info('University users seeded successfully!');
        $this->command->info('Total users created: '.User::count());
        $this->command->line('');
        $this->command->info('Sample login credentials:');
        $this->command->line('Developer: developer@koakademy.edu / password');
        $this->command->line('Admin: admin@koakademy.edu / password');
        $this->command->line('President: president@koakademy.edu / password');
        $this->command->line('Registrar: registrar@koakademy.edu / password');
        $this->command->line('Cashier: cashier@koakademy.edu / password');
        $this->command->line('Student: john.student@student.koakademy.edu / password');
    }

    private function createUserWithRole(string $name, string $email, UserRole $role): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);

        $spatieRole = Role::findByName($role->value, 'web');
        $user->assignRole($spatieRole);

        return $user;
    }
}
