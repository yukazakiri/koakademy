<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * IMPORTANT: Run seeders in the correct order to maintain referential integrity
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive database seeding...');

        // Phase 1: Foundation Data (Independent tables)
        $this->command->info('📊 Phase 1: Seeding foundation data...');
        $this->call([
            BrandingSettingsSeeder::class,   // Branding defaults
            GeneralSettingSeeder::class,     // General settings (system configuration)
            SchoolDepartmentSeeder::class,   // Schools and departments (must run first for school_id)
            StemCurriculumSeeder::class,     // SHS STEM Curriculum (foundational academic data)
            ABMCurriculumSeeder::class,      // SHS ABM Curriculum (foundational academic data)
            HUMSSCurriculumSeeder::class,    // SHS HUMSS Curriculum (foundational academic data)
            HECurriculumSeeder::class,       // SHS Home Economics Curriculum (foundational academic data)
            ICTCurriculumSeeder::class,      // SHS ICT Curriculum (foundational academic data)
            CGCurriculumSeeder::class,       // SHS Caregiving Curriculum (foundational academic data)
            RolesSeeder::class,              // Sync UserRole enum with Spatie roles
            UserSeeder::class,               // Users (admins, staff)
            CourseSeeder::class,             // Courses
            RoomSeeder::class,               // Rooms
            FacultySeeder::class,            // faculty members
            SubjectSeeder::class,            // Subjects (depends on courses)
        ]);

        // Phase 2: Student Data (Depends on courses and supporting tables)
        $this->command->info('👥 Phase 2: Seeding student-related data...');
        $this->call([
            StudentRelatedTablesSeeder::class,  // Document locations, contacts, etc.
            StudentSeeder::class,              // Students (depends on courses and related tables)
            AccountSeeder::class,              // Accounts (depends on students and faculty)
        ]);

        // // Phase 3: Academic Structure (Depends on subjects, faculty, rooms)
        $this->command->info('🏫 Phase 3: Seeding academic structure...');
        $this->call([
            ClassSeeder::class,         // Classes (depends on subjects, faculty, rooms)
            ScheduleSeeder::class,      // Schedules (depends on classes and rooms)
        ]);

        // Phase 4: Enrollments (Depends on students, courses, subjects, classes)
        $this->command->info('📝 Phase 4: Seeding enrollment data...');
        $this->call([
            StudentEnrollmentSeeder::class,    // Student enrollments
            StudentEnrollmentCurrentSemesterSeeder::class, // Student Enrollment in current sem
            SubjectEnrollmentSeeder::class,    // Subject enrollments (depends on student enrollments)
            ClassEnrollmentSeeder::class,      // Class enrollments (depends on classes and students)
        ]);

        // // Phase 5: Financial Data (Depends on enrollments)
        $this->command->info('💰 Phase 5: Seeding financial data...');
        $this->call([
            StudentTuitionSeeder::class,       // Student tuition records
            TransactionSeeder::class,          // Transactions and payments
        ]);

        // // Phase 6: Administrative Data (Depends on students)
        $this->command->info('📋 Phase 6: Seeding administrative data...');
        $this->call([
            StudentClearanceSeeder::class,     // Student clearances
        ]);

        // // Create the original developer account
        $developer = User::factory()->create([
            'name' => 'Achyut Neupane',
            'email' => 'achyutkneupane@gmail.com',
            'role' => UserRole::Developer,
            'password' => bcrypt('Achyut@123'),
        ]);
        $developer->assignRole(Role::findByName(UserRole::Developer->value, 'web'));

        $this->command->info('✅ Database seeding completed successfully!');
    }
}
