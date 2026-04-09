<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SchoolDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->createSchoolsAndDepartments();
        });
    }

    /**
     * Create schools and their departments
     */
    private function createSchoolsAndDepartments(): void
    {
        $schoolsData = [
            [
                'name' => 'School of Information Technology',
                'code' => 'SIT',
                'description' => 'The School of Information Technology offers comprehensive programs in computer science, information systems, and emerging technologies.',
                'dean_name' => 'Dr. Maria Santos',
                'dean_email' => 'maria.santos@university.edu',
                'location' => 'IT Building, Main Campus',
                'phone' => '+63 2 123-4567',
                'email' => 'sit@university.edu',
                'departments' => [
                    ['name' => 'Computer Science', 'code' => 'CS'],
                    ['name' => 'Information Systems', 'code' => 'IS'],
                    ['name' => 'Information Technology', 'code' => 'IT'],
                    ['name' => 'Software Engineering', 'code' => 'SE'],
                    ['name' => 'Cybersecurity', 'code' => 'CYBER'],
                ],
            ],
            [
                'name' => 'School of Business Administration',
                'code' => 'SBA',
                'description' => 'The School of Business Administration provides quality education in business management, entrepreneurship, and commerce.',
                'dean_name' => 'Dr. Roberto Cruz',
                'dean_email' => 'roberto.cruz@university.edu',
                'location' => 'Business Building, Main Campus',
                'phone' => '+63 2 123-4568',
                'email' => 'sba@university.edu',
                'departments' => [
                    ['name' => 'Management', 'code' => 'MGT'],
                    ['name' => 'Marketing', 'code' => 'MKT'],
                    ['name' => 'Finance', 'code' => 'FIN'],
                    ['name' => 'Accounting', 'code' => 'ACC'],
                    ['name' => 'Human Resources', 'code' => 'HR'],
                    ['name' => 'Entrepreneurship', 'code' => 'ENTR'],
                ],
            ],
            [
                'name' => 'School of Engineering',
                'code' => 'SOE',
                'description' => 'The School of Engineering offers cutting-edge engineering programs with emphasis on innovation and practical application.',
                'dean_name' => 'Engr. Ana Reyes',
                'dean_email' => 'ana.reyes@university.edu',
                'location' => 'Engineering Building, Main Campus',
                'phone' => '+63 2 123-4569',
                'email' => 'soe@university.edu',
                'departments' => [
                    ['name' => 'Civil Engineering', 'code' => 'CE'],
                    ['name' => 'Mechanical Engineering', 'code' => 'ME'],
                    ['name' => 'Electrical Engineering', 'code' => 'EE'],
                    ['name' => 'Chemical Engineering', 'code' => 'CHE'],
                    ['name' => 'Industrial Engineering', 'code' => 'IE'],
                ],
            ],
            [
                'name' => 'School of Arts and Sciences',
                'code' => 'SAS',
                'description' => 'The School of Arts and Sciences provides liberal arts education and scientific research opportunities.',
                'dean_name' => 'Dr. Carmen Garcia',
                'dean_email' => 'carmen.garcia@university.edu',
                'location' => 'Arts and Sciences Building, Main Campus',
                'phone' => '+63 2 123-4570',
                'email' => 'sas@university.edu',
                'departments' => [
                    ['name' => 'Mathematics', 'code' => 'MATH'],
                    ['name' => 'Physics', 'code' => 'PHYS'],
                    ['name' => 'Chemistry', 'code' => 'CHEM'],
                    ['name' => 'Biology', 'code' => 'BIO'],
                    ['name' => 'English', 'code' => 'ENG'],
                    ['name' => 'History', 'code' => 'HIST'],
                    ['name' => 'Psychology', 'code' => 'PSYCH'],
                ],
            ],
            [
                'name' => 'School of Education',
                'code' => 'SOED',
                'description' => 'The School of Education prepares future educators with modern teaching methodologies and educational leadership.',
                'dean_name' => 'Dr. Linda Fernandez',
                'dean_email' => 'linda.fernandez@university.edu',
                'location' => 'Education Building, Main Campus',
                'phone' => '+63 2 123-4571',
                'email' => 'soed@university.edu',
                'departments' => [
                    ['name' => 'Elementary Education', 'code' => 'ELEM'],
                    ['name' => 'Secondary Education', 'code' => 'SEC'],
                    ['name' => 'Special Education', 'code' => 'SPED'],
                    ['name' => 'Educational Leadership', 'code' => 'EDLEAD'],
                ],
            ],
            [
                'name' => 'School of Health Sciences',
                'code' => 'SHS',
                'description' => 'The School of Health Sciences offers healthcare programs focused on quality patient care and medical excellence.',
                'dean_name' => 'Dr. Patricia Mendoza',
                'dean_email' => 'patricia.mendoza@university.edu',
                'location' => 'Health Sciences Building, Main Campus',
                'phone' => '+63 2 123-4572',
                'email' => 'shs@university.edu',
                'departments' => [
                    ['name' => 'Nursing', 'code' => 'NURS'],
                    ['name' => 'Physical Therapy', 'code' => 'PT'],
                    ['name' => 'Medical Technology', 'code' => 'MEDTECH'],
                    ['name' => 'Pharmacy', 'code' => 'PHARM'],
                    ['name' => 'Nutrition and Dietetics', 'code' => 'NUTRI'],
                ],
            ],
            [
                'name' => 'School of Hospitality Management',
                'code' => 'SHM',
                'description' => 'The School of Hospitality Management provides world-class training in hotel, restaurant, and tourism management.',
                'dean_name' => 'Chef Marco Villanueva',
                'dean_email' => 'marco.villanueva@university.edu',
                'location' => 'Hospitality Building, Main Campus',
                'phone' => '+63 2 123-4573',
                'email' => 'shm@university.edu',
                'departments' => [
                    ['name' => 'Hotel Management', 'code' => 'HM'],
                    ['name' => 'Culinary Arts', 'code' => 'CA'],
                    ['name' => 'Tourism Management', 'code' => 'TM'],
                    ['name' => 'Event Management', 'code' => 'EM'],
                ],
            ],
        ];

        foreach ($schoolsData as $schoolData) {
            $departments = $schoolData['departments'];
            unset($schoolData['departments']);

            // Create school
            $school = School::create([
                ...$schoolData,
                'is_active' => true,
                'metadata' => [
                    'established_year' => 2010,
                    'accreditation_status' => 'Accredited',
                    'student_capacity' => random_int(1000, 3000),
                ],
            ]);

            // Create departments for this school
            foreach ($departments as $departmentData) {
                Department::create([
                    'school_id' => $school->id,
                    'name' => $departmentData['name'],
                    'code' => $departmentData['code'],
                    'description' => "The {$departmentData['name']} department provides comprehensive education and research opportunities in {$departmentData['name']}.",
                    'head_name' => fake()->name(),
                    'head_email' => mb_strtolower($departmentData['code']).'.head@university.edu',
                    'location' => 'Room '.fake()->numberBetween(100, 500),
                    'phone' => fake()->phoneNumber(),
                    'email' => mb_strtolower($departmentData['code']).'@university.edu',
                    'is_active' => true,
                    'metadata' => [
                        'faculty_count' => random_int(8, 25),
                        'student_count' => random_int(150, 800),
                        'programs_offered' => random_int(2, 5),
                    ],
                ]);
            }
        }

        $this->command->info('Created '.School::count().' schools and '.Department::count().' departments.');
    }
}
