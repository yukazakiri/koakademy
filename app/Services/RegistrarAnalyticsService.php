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
                'total_all_time_enrollments' => StudentEnrollment::query()
                    ->withTrashed()
                    ->count(),
                'by_department' => (clone $currentSemesterQuery)
                    ->where('student_enrollment.status', '!=', $pendingStatus)
                    ->join('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
                    ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
                    ->selectRaw('TRIM(departments.code) as department, count(*) as count')
                    ->groupByRaw('TRIM(departments.code)')
                    ->get(),
                'by_year_level' => (clone $currentSemesterQuery)
                    ->where('student_enrollment.status', '!=', $pendingStatus)
                    ->selectRaw('academic_year as year_level, count(*) as count')
                    ->groupBy('academic_year')
                    ->get(),
                'by_student_type' => (clone $currentSemesterQuery)
                    ->where('student_enrollment.status', '!=', $pendingStatus)
                    ->join('students', function ($join): void {
                        $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                    })
                    ->selectRaw('students.student_type, count(*) as count')
                    ->groupBy('students.student_type')
                    ->get(),
                'by_gender' => (clone $currentSemesterQuery)
                    ->where('student_enrollment.status', '!=', $pendingStatus)
                    ->join('students', function ($join): void {
                        $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                    })
                    ->selectRaw('LOWER(students.gender) as gender, count(*) as count')
                    ->groupBy('students.gender')
                    ->get(),
                'by_course' => (clone $currentSemesterQuery)
                    ->where('student_enrollment.status', '!=', $pendingStatus)
                    ->join('courses', DB::raw('CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), \'\') AS BIGINT)'), '=', 'courses.id')
                    ->selectRaw('courses.code as course_code, courses.title as course_title, count(*) as count')
                    ->groupBy('courses.code', 'courses.title')
                    ->orderByDesc('count')
                    ->limit(20)
                    ->get(),
                'by_status' => (clone $currentSemesterQuery)
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->get(),
                'trashed_count' => (clone $currentSemesterQuery)->onlyTrashed()->count(),
                'active_count' => (clone $currentSemesterQuery)->whereNull('deleted_at')->count(),
                'daily_trend' => (clone $currentSemesterQuery)
                    ->selectRaw('DATE(created_at) as date, count(*) as count')
                    ->groupByRaw('DATE(created_at)')
                    ->orderBy('date')
                    ->get(),
                'by_submission_channel' => (clone $currentSemesterQuery)
                    ->selectRaw("COALESCE(NULLIF(submission_channel, ''), 'direct') as channel, count(*) as count")
                    ->groupBy('submission_channel')
                    ->get(),
            ],
            'applicantsCount' => Student::query()
                ->withTrashed()
                ->where('status', StudentStatus::Applicant)
                ->count(),
            'total_students' => Student::query()->withTrashed()->count(),
            'total_college_students' => Student::query()->withTrashed()->where('student_type', 'college')->count(),
            'total_shs_students' => Student::query()->withTrashed()->where('student_type', 'shs')->count(),
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
                'missing_student_record_count' => (clone $currentSemesterQuery)
                    ->leftJoin('students', function ($join): void {
                        $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                    })
                    ->whereNull('students.id')
                    ->count(),
                'without_gender_count' => (clone $currentSemesterQuery)
                    ->where('student_enrollment.status', '!=', $pendingStatus)
                    ->join('students', function ($join): void {
                        $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                    })
                    ->whereNull('students.gender')
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
