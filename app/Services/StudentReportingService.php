<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ExportStudentDataJob;
use App\Models\Course;
use App\Models\ExportJob;
use App\Models\ShsStudent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final readonly class StudentReportingService
{
    public function __construct(private GeneralSettingsService $generalSettingsService) {}

    public function generateDashboardReport(): array
    {
        try {
            // Get current academic period from settings
            $currentSchoolYear = $this->generalSettingsService->getCurrentSchoolYearString();
            $currentSemester = $this->generalSettingsService->getCurrentSemester();

            Log::info('Generating dashboard report', [
                'school_year' => $currentSchoolYear,
                'semester' => $currentSemester,
            ]);

            $report = [
                'overview' => $this->generateOverviewStats($currentSchoolYear, $currentSemester),
                'courses' => $this->generateCourseBreakdown($currentSchoolYear, $currentSemester),
                'year_levels' => $this->generateYearLevelBreakdown($currentSchoolYear, $currentSemester),
                'demographics' => $this->generateDemographics($currentSchoolYear, $currentSemester),
                'academic_period' => [
                    'school_year' => $currentSchoolYear,
                    'semester' => $currentSemester,
                    'semester_label' => $this->generalSettingsService->getAvailableSemesters()[$currentSemester] ?? 'Unknown',
                ],
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ];

            Log::info('Dashboard report generated successfully', [
                'total_students' => $report['overview']['total_students'],
                'total_courses' => count($report['courses']),
            ]);

            return $report;
        } catch (Exception $exception) {
            Log::error('Failed to generate dashboard report', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function exportToExcel(): string
    {
        $reportData = $this->generateDashboardReport();

        // Create CSV content
        $csvContent = "Student Analytics Report\n";
        $csvContent .= 'Generated: '.$reportData['generated_at']."\n";
        $csvContent .= 'Academic Period: '.$reportData['academic_period']['school_year'].' - '.$reportData['academic_period']['semester_label']."\n\n";

        // Overview section
        $csvContent .= "OVERVIEW\n";
        $csvContent .= 'Total Students,'.$reportData['overview']['total_students']."\n";
        $csvContent .= 'Total SHS Students,'.$reportData['overview']['total_shs_students']."\n";
        $csvContent .= 'Male Students,'.$reportData['overview']['gender_distribution']['male']."\n";
        $csvContent .= 'Female Students,'.$reportData['overview']['gender_distribution']['female']."\n\n";

        // Courses section
        $csvContent .= "COURSES\n";
        $csvContent .= "Course Code,Course Title,Total Students,Male,Female,Average Age\n";
        foreach ($reportData['courses'] as $course) {
            $csvContent .= $course['course_code'].','.$course['course_title'].','.$course['total_students'].','.$course['gender_distribution']['male'].','.$course['gender_distribution']['female'].','.number_format($course['average_age'], 1)."\n";
        }

        $fileName = 'student_analytics_'.date('Y-m-d_H-i-s').'.csv';
        $filePath = 'exports/'.$fileName;

        Storage::put($filePath, $csvContent);

        return $filePath;
    }

    public function generateExportPreview(array $filters): array
    {
        $students = $this->getFilteredStudents($filters, 10); // Limit to 10 for preview

        return [
            'students' => $students->map(fn ($student): array => [
                'student_id' => $student->id,
                'full_name' => mb_trim($student->first_name.' '.($student->middle_name ? $student->middle_name.' ' : '').$student->last_name),
                'course_code' => $student->course->code ?? 'N/A',
                'year_level' => $student->academic_year,
            ])->toArray(),
            'total_count' => $this->getFilteredStudents($filters)->count(),
            'filters_applied' => $filters,
        ];
    }

    public function queueExport(array $filters, string $format, int $userId): int
    {
        try {
            Log::info('Queueing student data export', [
                'user_id' => $userId,
                'format' => $format,
                'filters' => $filters,
            ]);

            // Create export job record in database
            $exportJob = ExportJob::query()->create([
                'job_id' => uniqid('export_', true),
                'user_id' => $userId,
                'export_type' => 'student_data',
                'filters' => $filters,
                'format' => $format,
                'status' => 'pending',
            ]);

            // Dispatch job with export job ID
            ExportStudentDataJob::dispatch($exportJob->id);

            Log::info('Student data export queued successfully', [
                'export_job_id' => $exportJob->id,
                'job_id' => $exportJob->job_id,
            ]);

            return $exportJob->id;
        } catch (Exception $exception) {
            Log::error('Failed to queue student data export', [
                'user_id' => $userId,
                'format' => $format,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function generateFilteredExportContent(array $filters, string $format): string
    {
        $students = $this->getFilteredStudents($filters);

        if ($format === 'pdf') {
            return $this->generatePdfExportContent($students, $filters);
        }

        return $this->generateCsvExportContent($students, $filters);
    }

    public function generateFilteredExport(array $filters, string $format): string
    {
        $students = $this->getFilteredStudents($filters);

        if ($format === 'pdf') {
            return $this->generatePdfExport($students, $filters);
        }

        return $this->generateCsvExport($students, $filters);
    }

    private function generateOverviewStats(string $schoolYear, int $semester): array
    {
        // Get enrolled student IDs for current period
        $enrolledStudentIds = StudentEnrollment::query()->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->withTrashed()

            ->pluck('student_id')
            ->toArray();

        // Total students
        $totalStudents = Student::query()->whereIn('id', $enrolledStudentIds)

            ->count();

        // Gender distribution
        $genderStats = Student::query()->whereIn('id', $enrolledStudentIds)

            ->selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        // Enrollment status
        $enrollmentStats = StudentEnrollment::query()->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->withTrashed()

            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_students' => $totalStudents,
            'total_shs_students' => ShsStudent::query()->count(),
            'gender_distribution' => [
                'male' => $genderStats['Male'] ?? 0,
                'female' => $genderStats['Female'] ?? 0,
                'total' => array_sum($genderStats),
            ],
            'enrollment_status' => $enrollmentStats,
        ];
    }

    private function generateCourseBreakdown(string $schoolYear, int $semester): array
    {
        // Get enrolled student IDs for current period
        $enrolledStudentIds = StudentEnrollment::query()->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->withTrashed()

            ->pluck('student_id')
            ->toArray();

        // Get all courses with their enrolled students
        $courses = Course::with(['students' => function ($query) use ($enrolledStudentIds): void {
            $query->whereIn('id', $enrolledStudentIds);
        }])
            ->orderBy('code')
            ->get()
            ->map(function ($course): array {
                $students = $course->students;

                // Year level breakdown
                $yearLevelBreakdown = $students->groupBy('academic_year')->map->count()->toArray();

                // Gender breakdown
                $genderBreakdown = $students->groupBy('gender')->map->count()->toArray();

                return [
                    'course_code' => $course->code,
                    'course_title' => $course->title,
                    'total_students' => $students->count(),
                    'year_levels' => $yearLevelBreakdown,
                    'gender_distribution' => [
                        'male' => $genderBreakdown['Male'] ?? 0,
                        'female' => $genderBreakdown['Female'] ?? 0,
                    ],
                    'average_age' => $students->avg('age') ?? 0,
                ];
            })
            ->filter(fn ($course): bool => $course['total_students'] > 0); // Only show courses with students

        return $courses->all();
    }

    private function generateYearLevelBreakdown(string $schoolYear, int $semester): array
    {
        // Get enrolled student IDs for current period
        $enrolledStudentIds = StudentEnrollment::query()->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->withTrashed()

            ->pluck('student_id')
            ->toArray();

        $yearLevels = [1, 2, 3, 4];

        $yearLevelData = collect($yearLevels)->map(function (int $year) use ($enrolledStudentIds): array {
            $students = Student::query()->where('academic_year', $year)
                ->whereIn('id', $enrolledStudentIds)

                ->with('course')
                ->get();

            // Program breakdown
            $programBreakdown = $students->groupBy('course.code')->map->count()->toArray();

            // Gender breakdown
            $genderBreakdown = $students->groupBy('gender')->map->count()->toArray();

            return [
                'year_level' => $year,
                'year_label' => $this->getYearLevelLabel($year),
                'total_students' => $students->count(),
                'programs' => $programBreakdown,
                'gender_distribution' => [
                    'male' => $genderBreakdown['Male'] ?? 0,
                    'female' => $genderBreakdown['Female'] ?? 0,
                ],
                'average_age' => $students->avg('age') ?? 0,
            ];
        })->filter(fn ($yearLevel): bool => $yearLevel['total_students'] > 0);

        return $yearLevelData->all();
    }

    private function generateDemographics(string $schoolYear, int $semester): array
    {
        // Get enrolled student IDs for current period
        $enrolledStudentIds = StudentEnrollment::query()->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->withTrashed()

            ->pluck('student_id')
            ->toArray();

        $students = Student::query()->whereIn('id', $enrolledStudentIds)

            ->get();

        // Age statistics
        $ages = $students->pluck('age')->filter();

        // Age groups
        $ageGroups = $students->groupBy(function ($student): string {
            $age = $student->age;
            if ($age < 18) {
                return 'Under 18';
            }

            if ($age <= 20) {
                return '18-20';
            }

            if ($age <= 22) {
                return '21-22';
            }

            if ($age <= 25) {
                return '23-25';
            }

            return 'Over 25';
        })->map->count()->toArray();

        return [
            'age_statistics' => [
                'average' => $ages->avg() ?? 0,
                'median' => $ages->median() ?? 0,
                'min' => $ages->min() ?? 0,
                'max' => $ages->max() ?? 0,
            ],
            'age_groups' => $ageGroups,
        ];
    }

    private function getYearLevelLabel(int $year): string
    {
        return match ($year) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            default => 'Unknown Year',
        };
    }

    private function getFilteredStudents(array $filters, ?int $limit = null): Collection
    {
        $currentSchoolYear = $this->generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $this->generalSettingsService->getCurrentSemester();

        // Get enrolled student IDs for current period
        $enrolledStudentIds = StudentEnrollment::query()->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->withTrashed()

            ->pluck('student_id')
            ->toArray();

        $query = Student::query()->whereIn('id', $enrolledStudentIds)

            ->with('course');

        // Apply course filter
        if (isset($filters['course_filter']) && $filters['course_filter'] !== 'all') {
            $query->whereHas('course', function ($q) use ($filters): void {
                $q->where('code', 'LIKE', $filters['course_filter'].'%');
            });
        }

        // Apply year level filter
        if (isset($filters['year_level_filter']) && $filters['year_level_filter'] !== 'all') {
            $query->where('academic_year', $filters['year_level_filter']);
        }

        if ($limit !== null && $limit !== 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function generateCsvExport(Collection $students, array $filters): string
    {
        $csvContent = "Student Export Report\n";
        $csvContent .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";
        $csvContent .= 'Academic Period: '.$this->generalSettingsService->getCurrentSchoolYearString().' - '.$this->generalSettingsService->getAvailableSemesters()[$this->generalSettingsService->getCurrentSemester()]."\n";

        // Add filter information
        if ($filters['course_filter'] !== 'all') {
            $csvContent .= 'Course Filter: '.$filters['course_filter']."\n";
        }

        if ($filters['year_level_filter'] !== 'all') {
            $csvContent .= 'Year Level Filter: '.$filters['year_level_filter']."\n";
        }

        $csvContent .= "\nSTUDENT LIST\n";
        $csvContent .= "Student ID,Full Name,Course Code,Year Level\n";

        foreach ($students as $student) {
            $fullName = mb_trim($student->first_name.' '.($student->middle_name ? $student->middle_name.' ' : '').$student->last_name);
            $csvContent .= $student->id.',"'.$fullName.'",'.($student->course->code ?? 'N/A').','.$student->academic_year."\n";
        }

        // Add summary
        $csvContent .= "\nSUMMARY\n";
        $csvContent .= 'Total Students,'.$students->count()."\n";

        // Course breakdown
        $courseBreakdown = $students->groupBy('course.code')->map->count();
        $csvContent .= "\nCOURSE BREAKDOWN\n";
        $csvContent .= "Course,Count\n";
        foreach ($courseBreakdown as $course => $count) {
            $csvContent .= ($course ?? 'N/A').','.$count."\n";
        }

        // Year level breakdown
        $yearBreakdown = $students->groupBy('academic_year')->map->count();
        $csvContent .= "\nYEAR LEVEL BREAKDOWN\n";
        $csvContent .= "Year Level,Count\n";
        foreach ($yearBreakdown as $year => $count) {
            $csvContent .= $year.','.$count."\n";
        }

        $fileName = 'student_export_'.date('Y-m-d_H-i-s').'.csv';
        $filePath = 'exports/'.$fileName;

        Storage::put($filePath, $csvContent);

        return $filePath;
    }

    private function generatePdfExport(Collection $students, array $filters): string
    {
        $htmlContent = '<!DOCTYPE html><html><head><title>Student Export Report</title>';
        $htmlContent .= '<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .filters { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            table { border-collapse: collapse; width: 100%; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .summary { margin-top: 30px; }
            .summary-table { width: 50%; }
        </style>';
        $htmlContent .= '</head><body>';

        $htmlContent .= "<div class='header'>";
        $htmlContent .= '<h1>Student Export Report</h1>';
        $htmlContent .= '<p>Generated: '.now()->format('Y-m-d H:i:s').'</p>';
        $htmlContent .= '<p>Academic Period: '.$this->generalSettingsService->getCurrentSchoolYearString().' - '.$this->generalSettingsService->getAvailableSemesters()[$this->generalSettingsService->getCurrentSemester()].'</p>';
        $htmlContent .= '</div>';

        // Filters applied
        if ($filters['course_filter'] !== 'all' || $filters['year_level_filter'] !== 'all') {
            $htmlContent .= "<div class='filters'>";
            $htmlContent .= '<h3>Filters Applied:</h3>';
            if ($filters['course_filter'] !== 'all') {
                $htmlContent .= '<p><strong>Course:</strong> '.$filters['course_filter'].'</p>';
            }

            if ($filters['year_level_filter'] !== 'all') {
                $htmlContent .= '<p><strong>Year Level:</strong> '.$filters['year_level_filter'].'</p>';
            }

            $htmlContent .= '</div>';
        }

        // Student list
        $htmlContent .= '<h2>Student List</h2>';
        $htmlContent .= '<table>';
        $htmlContent .= '<tr><th>Student ID</th><th>Full Name</th><th>Course Code</th><th>Year Level</th></tr>';

        foreach ($students as $student) {
            $fullName = mb_trim($student->first_name.' '.($student->middle_name ? $student->middle_name.' ' : '').$student->last_name);
            $htmlContent .= '<tr>';
            $htmlContent .= '<td>'.$student->id.'</td>';
            $htmlContent .= '<td>'.htmlspecialchars($fullName).'</td>';
            $htmlContent .= '<td>'.($student->course->code ?? 'N/A').'</td>';
            $htmlContent .= '<td>'.$student->academic_year.'</td>';
            $htmlContent .= '</tr>';
        }

        $htmlContent .= '</table>';

        // Summary
        $htmlContent .= "<div class='summary'>";
        $htmlContent .= '<h2>Summary</h2>';
        $htmlContent .= '<p><strong>Total Students:</strong> '.$students->count().'</p>';

        // Course breakdown
        $courseBreakdown = $students->groupBy('course.code')->map->count();
        if ($courseBreakdown->count() > 1) {
            $htmlContent .= '<h3>Course Breakdown</h3>';
            $htmlContent .= "<table class='summary-table'>";
            $htmlContent .= '<tr><th>Course</th><th>Count</th></tr>';
            foreach ($courseBreakdown as $course => $count) {
                $htmlContent .= '<tr><td>'.($course ?? 'N/A').'</td><td>'.$count.'</td></tr>';
            }

            $htmlContent .= '</table>';
        }

        // Year level breakdown
        $yearBreakdown = $students->groupBy('academic_year')->map->count();
        if ($yearBreakdown->count() > 1) {
            $htmlContent .= '<h3>Year Level Breakdown</h3>';
            $htmlContent .= "<table class='summary-table'>";
            $htmlContent .= '<tr><th>Year Level</th><th>Count</th></tr>';
            foreach ($yearBreakdown as $year => $count) {
                $htmlContent .= '<tr><td>'.$year.'</td><td>'.$count.'</td></tr>';
            }

            $htmlContent .= '</table>';
        }

        $htmlContent .= '</div>';
        $htmlContent .= '</body></html>';

        $fileName = 'student_export_'.date('Y-m-d_H-i-s').'.html';
        $filePath = 'exports/'.$fileName;

        Storage::put($filePath, $htmlContent);

        return $filePath;
    }

    private function generateCsvExportContent(Collection $students, array $filters): string
    {
        $csvContent = "Student Export Report\n";
        $csvContent .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";
        $csvContent .= 'Academic Period: '.$this->generalSettingsService->getCurrentSchoolYearString().' - '.$this->generalSettingsService->getAvailableSemesters()[$this->generalSettingsService->getCurrentSemester()]."\n";

        // Add filter information
        if ($filters['course_filter'] !== 'all') {
            $csvContent .= 'Course Filter: '.$filters['course_filter']."\n";
        }

        if ($filters['year_level_filter'] !== 'all') {
            $csvContent .= 'Year Level Filter: '.$filters['year_level_filter']."\n";
        }

        $csvContent .= "\nSTUDENT LIST\n";
        $csvContent .= "Student ID,Full Name,Course Code,Year Level\n";

        foreach ($students as $student) {
            $fullName = mb_trim($student->first_name.' '.($student->middle_name ? $student->middle_name.' ' : '').$student->last_name);
            $csvContent .= $student->id.',"'.$fullName.'",'.($student->course->code ?? 'N/A').','.$student->academic_year."\n";
        }

        // Add summary
        $csvContent .= "\nSUMMARY\n";
        $csvContent .= 'Total Students,'.$students->count()."\n";

        // Course breakdown
        $courseBreakdown = $students->groupBy('course.code')->map->count();
        $csvContent .= "\nCOURSE BREAKDOWN\n";
        $csvContent .= "Course,Count\n";
        foreach ($courseBreakdown as $course => $count) {
            $csvContent .= ($course ?? 'N/A').','.$count."\n";
        }

        // Year level breakdown
        $yearBreakdown = $students->groupBy('academic_year')->map->count();
        $csvContent .= "\nYEAR LEVEL BREAKDOWN\n";
        $csvContent .= "Year Level,Count\n";
        foreach ($yearBreakdown as $year => $count) {
            $csvContent .= $year.','.$count."\n";
        }

        return $csvContent;
    }

    private function generatePdfExportContent(Collection $students, array $filters): string
    {
        $htmlContent = '<!DOCTYPE html><html><head><title>Student Export Report</title>';
        $htmlContent .= '<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .filters { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            table { border-collapse: collapse; width: 100%; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .summary { margin-top: 30px; }
            .summary-table { width: 50%; }
        </style>';
        $htmlContent .= '</head><body>';

        $htmlContent .= "<div class='header'>";
        $htmlContent .= '<h1>Student Export Report</h1>';
        $htmlContent .= '<p>Generated: '.now()->format('Y-m-d H:i:s').'</p>';
        $htmlContent .= '<p>Academic Period: '.$this->generalSettingsService->getCurrentSchoolYearString().' - '.$this->generalSettingsService->getAvailableSemesters()[$this->generalSettingsService->getCurrentSemester()].'</p>';
        $htmlContent .= '</div>';

        // Filters applied
        if ($filters['course_filter'] !== 'all' || $filters['year_level_filter'] !== 'all') {
            $htmlContent .= "<div class='filters'>";
            $htmlContent .= '<h3>Filters Applied:</h3>';
            if ($filters['course_filter'] !== 'all') {
                $htmlContent .= '<p><strong>Course:</strong> '.$filters['course_filter'].'</p>';
            }

            if ($filters['year_level_filter'] !== 'all') {
                $htmlContent .= '<p><strong>Year Level:</strong> '.$filters['year_level_filter'].'</p>';
            }

            $htmlContent .= '</div>';
        }

        // Student list
        $htmlContent .= '<h2>Student List</h2>';
        $htmlContent .= '<table>';
        $htmlContent .= '<tr><th>Student ID</th><th>Full Name</th><th>Course Code</th><th>Year Level</th></tr>';

        foreach ($students as $student) {
            $fullName = mb_trim($student->first_name.' '.($student->middle_name ? $student->middle_name.' ' : '').$student->last_name);
            $htmlContent .= '<tr>';
            $htmlContent .= '<td>'.$student->id.'</td>';
            $htmlContent .= '<td>'.htmlspecialchars($fullName).'</td>';
            $htmlContent .= '<td>'.($student->course->code ?? 'N/A').'</td>';
            $htmlContent .= '<td>'.$student->academic_year.'</td>';
            $htmlContent .= '</tr>';
        }

        $htmlContent .= '</table>';

        // Summary
        $htmlContent .= "<div class='summary'>";
        $htmlContent .= '<h2>Summary</h2>';
        $htmlContent .= '<p><strong>Total Students:</strong> '.$students->count().'</p>';

        // Course breakdown
        $courseBreakdown = $students->groupBy('course.code')->map->count();
        if ($courseBreakdown->count() > 1) {
            $htmlContent .= '<h3>Course Breakdown</h3>';
            $htmlContent .= "<table class='summary-table'>";
            $htmlContent .= '<tr><th>Course</th><th>Count</th></tr>';
            foreach ($courseBreakdown as $course => $count) {
                $htmlContent .= '<tr><td>'.($course ?? 'N/A').'</td><td>'.$count.'</td></tr>';
            }

            $htmlContent .= '</table>';
        }

        // Year level breakdown
        $yearBreakdown = $students->groupBy('academic_year')->map->count();
        if ($yearBreakdown->count() > 1) {
            $htmlContent .= '<h3>Year Level Breakdown</h3>';
            $htmlContent .= "<table class='summary-table'>";
            $htmlContent .= '<tr><th>Year Level</th><th>Count</th></tr>';
            foreach ($yearBreakdown as $year => $count) {
                $htmlContent .= '<tr><td>'.$year.'</td><td>'.$count.'</td></tr>';
            }

            $htmlContent .= '</table>';
        }

        $htmlContent .= '</div>';

        return $htmlContent.'</body></html>';
    }
}
