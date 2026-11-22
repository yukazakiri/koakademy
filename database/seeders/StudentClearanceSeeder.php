<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentClearance;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Seeder;

final class StudentClearanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = app(GeneralSettingsService::class);
        $currentSchoolYear = $settings->getCurrentSchoolYearString();
        $currentSemester = $settings->getCurrentSemester();
        $previousStartYear = $settings->getCurrentSchoolYearStart() - 1;
        $previousSchoolYear = $previousStartYear.' - '.($previousStartYear + 1);

        $students = Student::withoutSchoolScope()->get();
        $clearedIds = [2021001, 2021003, 2023001, 2020001];

        foreach ($students as $student) {
            $isCleared = in_array($student->id, $clearedIds, true) || $student->clearance_status === 'cleared';

            StudentClearance::query()->firstOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year' => $currentSchoolYear,
                    'semester' => $currentSemester,
                ],
                [
                    'is_cleared' => $isCleared,
                    'cleared_by' => $isCleared ? 'Registrar Office' : null,
                    'cleared_at' => $isCleared ? now()->subDays(random_int(1, 15)) : null,
                    'remarks' => $isCleared
                        ? 'All requirements submitted and verified'
                        : 'Pending document submission',
                ],
            );

            if ((int) $student->academic_year >= 2) {
                StudentClearance::query()->firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_year' => $previousSchoolYear,
                        'semester' => 2,
                    ],
                    [
                        'is_cleared' => true,
                        'cleared_by' => 'Registrar Office',
                        'cleared_at' => now()->subMonths(6)->addDays(random_int(1, 30)),
                        'remarks' => 'Semester completed - all requirements met',
                    ],
                );

                StudentClearance::query()->firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_year' => $previousSchoolYear,
                        'semester' => 1,
                    ],
                    [
                        'is_cleared' => true,
                        'cleared_by' => 'Registrar Office',
                        'cleared_at' => now()->subMonths(12)->addDays(random_int(1, 30)),
                        'remarks' => 'Semester completed - all requirements met',
                    ],
                );
            }
        }

        $specificClearances = [
            [
                'student_id' => 2024002,
                'academic_year' => $currentSchoolYear,
                'semester' => $currentSemester,
                'is_cleared' => false,
                'cleared_by' => null,
                'cleared_at' => null,
                'remarks' => 'Missing: Library clearance, Laboratory clearance',
            ],
            [
                'student_id' => 2024003,
                'academic_year' => $currentSchoolYear,
                'semester' => $currentSemester,
                'is_cleared' => false,
                'cleared_by' => null,
                'cleared_at' => null,
                'remarks' => 'Conditional - pending final grade submission',
            ],
        ];

        foreach ($specificClearances as $specificClearance) {
            if (! Student::withoutSchoolScope()->whereKey($specificClearance['student_id'])->exists()) {
                continue;
            }

            StudentClearance::query()->updateOrCreate(
                [
                    'student_id' => $specificClearance['student_id'],
                    'academic_year' => $specificClearance['academic_year'],
                    'semester' => $specificClearance['semester'],
                ],
                $specificClearance,
            );
        }

        $this->command?->info('Student clearances seeded successfully!');
    }
}
