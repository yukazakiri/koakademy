<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;

final class RegistrarAnalyticsService
{
    public function __construct(
        private readonly EnrollmentPipelineService $enrollmentPipelineService,
        private readonly GeneralSettingsService $settingsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $currentSemester = $this->settingsService->getCurrentSemester();
        $currentSchoolYearStart = $this->settingsService->getCurrentSchoolYearStart();
        $currentSchoolYearString = $this->settingsService->getCurrentSchoolYearString();
        $previousSemester = $currentSemester === 1 ? 2 : 1;
        $previousSchoolYearStart = $currentSemester === 1 ? $currentSchoolYearStart - 1 : $currentSchoolYearStart;
        $previousSchoolYearString = $previousSchoolYearStart.' - '.($previousSchoolYearStart + 1);
        $pendingStatus = $this->enrollmentPipelineService->getPendingStatus();

        $currentSemesterQuery = StudentEnrollment::query()
            ->withTrashed()
            ->where('student_enrollment.school_year', $currentSchoolYearString)
            ->where('student_enrollment.semester', $currentSemester);

        return [
            'analytics' => [
                'current_semester_count' => (clone $currentSemesterQuery)
                    ->where('status', '!=', $pendingStatus)
                    ->count(),
                'current_school_year_count' => StudentEnrollment::query()
                    ->withTrashed()
                    ->where('school_year', $currentSchoolYearString)
                    ->where('status', '!=', $pendingStatus)
                    ->count(),
                'previous_semester_count' => StudentEnrollment::query()
                    ->withTrashed()
                    ->where('school_year', $previousSchoolYearString)
                    ->where('semester', $previousSemester)
                    ->where('status', '!=', $pendingStatus)
                    ->count(),
                'by_department' => (clone $currentSemesterQuery)
                    ->where('student_enrollment.status', '!=', $pendingStatus)
                    ->join('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
                    ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                    ->selectRaw('TRIM(departments.code) as department, count(*) as count')
                    ->groupByRaw('TRIM(departments.code)')
                    ->get(),
                'by_year_level' => (clone $currentSemesterQuery)
                    ->where('status', '!=', $pendingStatus)
                    ->selectRaw('academic_year as year_level, count(*) as count')
                    ->groupBy('academic_year')
                    ->get(),
                'trashed_count' => StudentEnrollment::query()
                    ->onlyTrashed()
                    ->where('school_year', $currentSchoolYearString)
                    ->where('semester', $currentSemester)
                    ->count(),
                'active_count' => StudentEnrollment::query()
                    ->where('school_year', $currentSchoolYearString)
                    ->where('semester', $currentSemester)
                    ->count(),
                'by_status' => (clone $currentSemesterQuery)
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->get(),
            ],
            'applicantsCount' => Student::query()
                ->withTrashed()
                ->where('status', StudentStatus::Applicant)
                ->count(),
            'quality' => [
                'missing_department_count' => (clone $currentSemesterQuery)
                    ->leftJoin('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
                    ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                    ->whereNull('departments.id')
                    ->count(),
                'missing_course_count' => (clone $currentSemesterQuery)
                    ->leftJoin('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
                    ->whereNull('courses.id')
                    ->count(),
            ],
            'filters' => $this->semesterContext(),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function semesterContext(): array
    {
        return [
            'currentSemester' => $this->settingsService->getCurrentSemester(),
            'currentSchoolYear' => $this->settingsService->getCurrentSchoolYearStart(),
            'systemSemester' => $this->settingsService->getSystemDefaultSemester(),
            'systemSchoolYear' => $this->settingsService->getSystemDefaultSchoolYearStart(),
            'availableSemesters' => $this->settingsService->getAvailableSemesters(),
            'availableSchoolYears' => $this->settingsService->getAvailableSchoolYears(),
        ];
    }
}
