<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class FacultySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;

        $faculties = [
            // Information Technology Faculty
            [
                'faculty_id_number' => 'FAC-IT-001',
                'first_name' => 'Roberto',
                'last_name' => 'Cruz',
                'middle_name' => 'Santos',
                'email' => 'roberto.cruz@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09171234567',
                'department' => 'Information Technology',
                'office_hours' => 'Monday-Friday 8:00AM-5:00PM',
                'birth_date' => '1980-05-15',
                'address_line1' => '123 Main St, Quezon City',
                'biography' => 'Experienced IT professional with 15+ years in software development.',
                'education' => 'PhD in Computer Science, Master in Information Technology',
                'courses_taught' => 'Programming, Database Systems, Software Engineering',
                'status' => 'active',
                'gender' => 'Male',
                'age' => 44,
            ],
            [
                'faculty_id_number' => 'FAC-IT-002',
                'first_name' => 'Maria',
                'last_name' => 'Gonzales',
                'middle_name' => 'Reyes',
                'email' => 'maria.gonzales@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09181234568',
                'department' => 'Information Technology',
                'office_hours' => 'Monday-Friday 9:00AM-6:00PM',
                'birth_date' => '1985-08-20',
                'address_line1' => '456 Oak Ave, Makati City',
                'biography' => 'Network security specialist and web development expert.',
                'education' => 'Master in Computer Science, Bachelor in Information Technology',
                'courses_taught' => 'Network Security, Web Development, System Administration',
                'status' => 'active',
                'gender' => 'Female',
                'age' => 39,
            ],
            [
                'faculty_id_number' => 'FAC-IT-003',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_name' => 'Pablo',
                'email' => 'juan.delacruz@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09191234569',
                'department' => 'Information Technology',
                'office_hours' => 'Tuesday-Saturday 10:00AM-7:00PM',
                'birth_date' => '1982-03-10',
                'address_line1' => '789 Pine St, Pasig City',
                'biography' => 'Mobile app developer and UI/UX design specialist.',
                'education' => 'Master in Information Systems, Bachelor in Computer Science',
                'courses_taught' => 'Mobile Development, UI/UX Design, Human Computer Interaction',
                'status' => 'active',
                'gender' => 'Male',
                'age' => 42,
            ],

            // Business Administration Faculty
            [
                'faculty_id_number' => 'FAC-BA-001',
                'first_name' => 'Ana',
                'last_name' => 'Rodriguez',
                'middle_name' => 'Luna',
                'email' => 'ana.rodriguez@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09201234570',
                'department' => 'Business Administration',
                'office_hours' => 'Monday-Friday 8:30AM-5:30PM',
                'birth_date' => '1978-11-25',
                'address_line1' => '321 Elm St, Manila',
                'biography' => 'Business strategy consultant with extensive corporate experience.',
                'education' => 'MBA in Strategic Management, Bachelor in Business Administration',
                'courses_taught' => 'Strategic Management, Business Planning, Entrepreneurship',
                'status' => 'active',
                'gender' => 'Female',
                'age' => 46,
            ],
            [
                'faculty_id_number' => 'FAC-BA-002',
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza',
                'middle_name' => 'Torres',
                'email' => 'carlos.mendoza@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09211234571',
                'department' => 'Business Administration',
                'office_hours' => 'Monday-Friday 9:00AM-6:00PM',
                'birth_date' => '1983-07-12',
                'address_line1' => '654 Maple Ave, Taguig City',
                'biography' => 'Financial analyst and accounting expert.',
                'education' => 'CPA, Master in Business Administration, Bachelor in Accounting',
                'courses_taught' => 'Financial Management, Accounting, Business Mathematics',
                'status' => 'active',
                'gender' => 'Male',
                'age' => 41,
            ],

            // Hotel Management Faculty
            [
                'faculty_id_number' => 'FAC-HM-001',
                'first_name' => 'Isabella',
                'last_name' => 'Santos',
                'middle_name' => 'Garcia',
                'email' => 'isabella.santos@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09221234572',
                'department' => 'Hotel Management',
                'office_hours' => 'Monday-Friday 8:00AM-5:00PM',
                'birth_date' => '1981-09-18',
                'address_line1' => '987 Cedar St, Alabang',
                'biography' => 'Former hotel operations manager with international experience.',
                'education' => 'Master in Hotel Management, Bachelor in Tourism',
                'courses_taught' => 'Hotel Operations, Front Office Management, Hospitality Marketing',
                'status' => 'active',
                'gender' => 'Female',
                'age' => 43,
            ],
            [
                'faculty_id_number' => 'FAC-HM-002',
                'first_name' => 'Miguel',
                'last_name' => 'Fernandez',
                'middle_name' => 'Ramos',
                'email' => 'miguel.fernandez@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09231234573',
                'department' => 'Hotel Management',
                'office_hours' => 'Tuesday-Saturday 10:00AM-7:00PM',
                'birth_date' => '1979-12-05',
                'address_line1' => '147 Birch Ave, BGC',
                'biography' => 'Executive chef with culinary arts expertise.',
                'education' => 'Culinary Arts Degree, Bachelor in Hotel Management',
                'courses_taught' => 'Culinary Arts, Food Service Management, Kitchen Operations',
                'status' => 'active',
                'gender' => 'Male',
                'age' => 45,
            ],

            // Additional Faculty for variety
            [
                'faculty_id_number' => 'FAC-GEN-001',
                'first_name' => 'Patricia',
                'last_name' => 'Villanueva',
                'middle_name' => 'Cruz',
                'email' => 'patricia.villanueva@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09241234574',
                'department' => 'General Education',
                'office_hours' => 'Monday-Friday 7:00AM-4:00PM',
                'birth_date' => '1984-04-30',
                'address_line1' => '258 Willow St, San Juan',
                'biography' => 'Mathematics and Statistics professor.',
                'education' => 'Master in Mathematics, Bachelor in Mathematics Education',
                'courses_taught' => 'Mathematics, Statistics, Research Methods',
                'status' => 'active',
                'gender' => 'Female',
                'age' => 40,
            ],
            [
                'faculty_id_number' => 'FAC-GEN-002',
                'first_name' => 'Eduardo',
                'last_name' => 'Morales',
                'middle_name' => 'Silva',
                'email' => 'eduardo.morales@koakademy.edu',
                'password' => Hash::make('password'),
                'phone_number' => '09251234575',
                'department' => 'General Education',
                'office_hours' => 'Monday-Friday 8:00AM-5:00PM',
                'birth_date' => '1977-01-22',
                'address_line1' => '369 Spruce St, Mandaluyong',
                'biography' => 'English and Communication professor.',
                'education' => 'Master in English Literature, Bachelor in English Education',
                'courses_taught' => 'English, Communication Skills, Technical Writing',
                'status' => 'active',
                'gender' => 'Male',
                'age' => 47,
            ],
        ];

        foreach ($faculties as $faculty) {
            Faculty::query()->create(array_merge($faculty, [
                'id' => Str::uuid(),
                'school_id' => $schoolId,
            ]));
        }

        $this->command->info('Faculty seeded successfully!');
    }
}
