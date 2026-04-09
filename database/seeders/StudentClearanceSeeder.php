<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentClearance;
use Illuminate\Database\Seeder;

final class StudentClearanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::all();

        foreach ($students as $student) {
            // Create clearance for current semester (2024-2025, Semester 1)
            StudentClearance::query()->create([
                'student_id' => $student->id,
                'academic_year' => '2024-2025',
                'semester' => 1,
                'is_cleared' => in_array($student->id, [2021001, 2021003, 2023001, 2020001]), // Some students are cleared
                'cleared_by' => in_array($student->id, [2021001, 2021003, 2023001, 2020001]) ? 'Registrar Office' : null,
                'cleared_at' => in_array($student->id, [2021001, 2021003, 2023001, 2020001]) ? now()->subDays(random_int(1, 15)) : null,
                'remarks' => in_array($student->id, [2021001, 2021003, 2023001, 2020001])
                    ? 'All requirements submitted and verified'
                    : 'Pending document submission',
            ]);

            // Create clearance for previous semester (2023-2024, Semester 2) for continuing students
            if (in_array($student->id, [2021001, 2021002, 2021003, 2023001, 2020001, 2022001])) {
                StudentClearance::query()->create([
                    'student_id' => $student->id,
                    'academic_year' => '2023-2024',
                    'semester' => 2,
                    'is_cleared' => true, // All previous semesters should be cleared
                    'cleared_by' => 'Registrar Office',
                    'cleared_at' => now()->subMonths(6)->addDays(random_int(1, 30)),
                    'remarks' => 'Semester completed - all requirements met',
                ]);
            }

            // Create clearance for 2023-2024, Semester 1 for older students
            if (in_array($student->id, [2021001, 2021002, 2021003, 2020001])) {
                StudentClearance::query()->create([
                    'student_id' => $student->id,
                    'academic_year' => '2023-2024',
                    'semester' => 1,
                    'is_cleared' => true,
                    'cleared_by' => 'Registrar Office',
                    'cleared_at' => now()->subMonths(12)->addDays(random_int(1, 30)),
                    'remarks' => 'Semester completed - all requirements met',
                ]);
            }
        }

        // Create some specific clearance scenarios for testing
        $specificClearances = [
            // Student with clearance issues
            [
                'student_id' => 2024002,
                'academic_year' => '2024-2025',
                'semester' => 1,
                'is_cleared' => false,
                'cleared_by' => null,
                'cleared_at' => null,
                'remarks' => 'Missing: Library clearance, Laboratory clearance',
            ],
            // Student with conditional clearance
            [
                'student_id' => 2024003,
                'academic_year' => '2024-2025',
                'semester' => 1,
                'is_cleared' => false,
                'cleared_by' => null,
                'cleared_at' => null,
                'remarks' => 'Conditional - pending final grade submission',
            ],
        ];

        foreach ($specificClearances as $specificClearance) {
            // Update existing clearance or create new one
            StudentClearance::query()->updateOrCreate([
                'student_id' => $specificClearance['student_id'],
                'academic_year' => $specificClearance['academic_year'],
                'semester' => $specificClearance['semester'],
            ], $specificClearance);
        }

        $this->command->info('Student clearances seeded successfully!');
    }
}
