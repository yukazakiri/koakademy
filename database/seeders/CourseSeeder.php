<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\School;
use Illuminate\Database\Seeder;

final class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;

        $courses = [
            // Information Technology Courses
            [
                'id' => 1,
                'code' => 'BSIT',
                'title' => 'Bachelor of Science in Information Technology',
                'description' => 'A comprehensive program covering software development, networking, and system administration.',
                'department' => 'Information Technology',
                'units' => 120,
                'lec_per_unit' => '150.00',
                'lab_per_unit' => '200.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3700.00',
                'is_active' => true,
            ],
            [
                'id' => 6,
                'code' => 'BSCS',
                'title' => 'Bachelor of Science in Computer Science',
                'description' => 'Focus on theoretical foundations of computing and software engineering.',
                'department' => 'Information Technology',
                'units' => 120,
                'lec_per_unit' => '150.00',
                'lab_per_unit' => '200.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3700.00',
                'is_active' => true,
            ],
            [
                'id' => 10,
                'code' => 'BSIS',
                'title' => 'Bachelor of Science in Information Systems',
                'description' => 'Combines business and technology to manage information systems.',
                'department' => 'Information Technology',
                'units' => 120,
                'lec_per_unit' => '150.00',
                'lab_per_unit' => '200.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3700.00',
                'is_active' => true,
            ],
            [
                'id' => 13,
                'code' => 'BSEMC',
                'title' => 'Bachelor of Science in Entertainment and Multimedia Computing',
                'description' => 'Focuses on game development, animation, and multimedia applications.',
                'department' => 'Information Technology',
                'units' => 120,
                'lec_per_unit' => '150.00',
                'lab_per_unit' => '200.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3700.00',
                'is_active' => true,
            ],

            // Business Administration Courses
            [
                'id' => 4,
                'code' => 'BSBA-MM',
                'title' => 'Bachelor of Science in Business Administration - Marketing Management',
                'description' => 'Specializes in marketing strategies and consumer behavior.',
                'department' => 'Business Administration',
                'units' => 120,
                'lec_per_unit' => '120.00',
                'lab_per_unit' => '150.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3500.00',
                'is_active' => true,
            ],
            [
                'id' => 5,
                'code' => 'BSBA-FM',
                'title' => 'Bachelor of Science in Business Administration - Financial Management',
                'description' => 'Focuses on financial planning, analysis, and investment strategies.',
                'department' => 'Business Administration',
                'units' => 120,
                'lec_per_unit' => '120.00',
                'lab_per_unit' => '150.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3500.00',
                'is_active' => true,
            ],
            [
                'id' => 8,
                'code' => 'BSBA-HRM',
                'title' => 'Bachelor of Science in Business Administration - Human Resource Management',
                'description' => 'Specializes in managing human resources and organizational behavior.',
                'department' => 'Business Administration',
                'units' => 120,
                'lec_per_unit' => '120.00',
                'lab_per_unit' => '150.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3500.00',
                'is_active' => true,
            ],
            [
                'id' => 9,
                'code' => 'BSBA-OM',
                'title' => 'Bachelor of Science in Business Administration - Operations Management',
                'description' => 'Focuses on business operations and supply chain management.',
                'department' => 'Business Administration',
                'units' => 120,
                'lec_per_unit' => '120.00',
                'lab_per_unit' => '150.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3500.00',
                'is_active' => true,
            ],

            // Hotel Management Courses
            [
                'id' => 2,
                'code' => 'BSHM',
                'title' => 'Bachelor of Science in Hotel Management',
                'description' => 'Comprehensive hospitality management program.',
                'department' => 'Hotel Management',
                'units' => 120,
                'lec_per_unit' => '130.00',
                'lab_per_unit' => '180.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3600.00',
                'is_active' => true,
            ],
            [
                'id' => 3,
                'code' => 'BSTM',
                'title' => 'Bachelor of Science in Tourism Management',
                'description' => 'Focuses on tourism industry and travel management.',
                'department' => 'Hotel Management',
                'units' => 120,
                'lec_per_unit' => '130.00',
                'lab_per_unit' => '180.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3600.00',
                'is_active' => true,
            ],
            [
                'id' => 11,
                'code' => 'BSHRM',
                'title' => 'Bachelor of Science in Hotel and Restaurant Management',
                'description' => 'Specialized program in hotel and restaurant operations.',
                'department' => 'Hotel Management',
                'units' => 120,
                'lec_per_unit' => '130.00',
                'lab_per_unit' => '180.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3600.00',
                'is_active' => true,
            ],
            [
                'id' => 12,
                'code' => 'BSCA',
                'title' => 'Bachelor of Science in Culinary Arts',
                'description' => 'Professional culinary arts and food service management.',
                'department' => 'Hotel Management',
                'units' => 120,
                'lec_per_unit' => '130.00',
                'lab_per_unit' => '180.00',
                'year_level' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'curriculum_year' => '2024-2025',
                'miscellaneous' => '3600.00',
                'is_active' => true,
            ],
        ];

        foreach ($courses as $course) {
            Course::query()->create(array_merge($course, [
                'school_id' => $schoolId,
            ]));
        }

        $this->command->info('Courses seeded successfully!');
    }
}
