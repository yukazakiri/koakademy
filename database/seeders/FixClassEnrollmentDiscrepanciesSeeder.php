<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\GeneralSetting;
use App\Models\SubjectEnrollment;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Seeder;

final class FixClassEnrollmentDiscrepanciesSeeder extends Seeder
{
    public function run(): void
    {
        $settings = GeneralSetting::query()->first();
        if (! $settings) {
            $this->command->error('General Settings not found.');

            return;
        }

        $generalSettingsService = app(GeneralSettingsService::class);
        $schoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $semester = $generalSettingsService->getCurrentSemester();

        $this->command->info("Fixing Class Enrollment discrepancies for AY $schoolYear, Semester $semester...");

        // Fetch all SubjectEnrollments for the current term that have a specific class assigned
        $subjectEnrollments = SubjectEnrollment::query()
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->whereNotNull('class_id')
            ->get();

        $bar = $this->command->getOutput()->createProgressBar($subjectEnrollments->count());
        $fixedCount = 0;
        $createdCount = 0;
        $deletedCount = 0;

        foreach ($subjectEnrollments as $se) {
            $targetClassId = $se->class_id;
            $studentId = $se->student_id;

            $targetClass = Classes::find($targetClassId);
            if (! $targetClass) {
                // Class no longer exists, skipping
                $bar->advance();

                continue;
            }

            // 1. Check if the student is already correctly enrolled in this exact class
            $exactMatch = ClassEnrollment::where('student_id', $studentId)
                ->where('class_id', $targetClassId)
                ->exists();

            if ($exactMatch) {
                // Already correct
                $bar->advance();

                continue;
            }

            // 2. Find enrollments for the same subject (by code) in this term
            // This handles cases where they are enrolled in the wrong section
            $conflictingEnrollments = ClassEnrollment::where('student_id', $studentId)
                ->whereHas('class', function ($query) use ($targetClass): void {
                    $query->where('subject_code', $targetClass->subject_code)
                        ->where('school_year', $targetClass->school_year)
                        ->where('semester', $targetClass->semester);
                })
                ->get();

            if ($conflictingEnrollments->isEmpty()) {
                // No enrollment exists at all for this subject -> Create it
                ClassEnrollment::create([
                    'student_id' => $studentId,
                    'class_id' => $targetClassId,
                    'status' => true, // Default to active/true
                ]);
                $createdCount++;
            } else {
                // Enrollment(s) exist but point to wrong class(es)

                // Update the first one to the correct class
                $first = $conflictingEnrollments->shift();
                $first->update(['class_id' => $targetClassId]);
                $fixedCount++;

                // Delete any duplicates (extra enrollments for the same subject)
                if ($conflictingEnrollments->isNotEmpty()) {
                    foreach ($conflictingEnrollments as $duplicate) {
                        $duplicate->delete();
                        $deletedCount++;
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('Completed.');
        $this->command->info("Fixed/Updated: $fixedCount");
        $this->command->info("Created: $createdCount");
        $this->command->info("Deleted Duplicates: $deletedCount");
    }
}
