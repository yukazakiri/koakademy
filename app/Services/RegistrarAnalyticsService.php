<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Course;
use App\Models\Department;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the registrar's aggregate-only Form B/C reporting population.
 * Restricted student-level rows are appended only for an authorized export.
 */
final class RegistrarAnalyticsService
{
    public function __construct(
        private readonly EnrollmentPipelineService $enrollmentPipelineService,
        private readonly GeneralSettingsService $settingsService,
    ) {}

    /**
     * @param  array<string, int|string|null>  $requestedFilters
     * @return array<string, mixed>
     */
    public function build(array $requestedFilters = [], bool $includeDetails = false): array
    {
        $report = $this->reportContext($requestedFilters);
        $filters = $report['values'];
        $pendingStatus = $this->enrollmentPipelineService->getPendingStatus();
        $base = $this->enrollmentQuery($filters);
        $reporting = $this->reportingPopulation($base, $filters, $pendingStatus);
        $previousFilters = $this->previousPeriodFilters($filters);
        $previous = $this->reportingPopulation($this->enrollmentQuery($previousFilters), $previousFilters, $pendingStatus);

        $analytics = [
            'current_semester_count' => (clone $reporting)->count(),
            'current_school_year_count' => $this->schoolYearPopulation($filters, $pendingStatus)->count(),
            'previous_semester_count' => $previous->count(),
            'active_count' => (clone $reporting)->whereNull('student_enrollment.deleted_at')->count(),
            'trashed_count' => (clone $reporting)->onlyTrashed()->count(),
            'by_department' => $this->byDepartment($reporting),
            'by_program' => $this->byProgram($reporting),
            'by_year_level' => $this->byYearLevel($reporting),
            'by_student_type' => $this->byStudentType($reporting),
            'by_gender' => $this->byGender($reporting),
            'by_status' => $this->byStatus($reporting),
            'pipeline_breakdown' => $this->byStatus($reporting),
            'daily_trend' => $this->dailyTrend($reporting),
            'by_origin' => $this->groupStudentField($reporting, 'students.region_of_origin', 'origin'),
            'by_scholarship' => $this->groupStudentField($reporting, 'students.scholarship_type', 'scholarship'),
            'by_income_bracket' => $this->groupStudentField($reporting, 'students.family_income_bracket', 'income_bracket'),
            'by_attrition' => $this->groupStudentField($reporting, 'students.attrition_category', 'attrition'),
            'by_equity_group' => $this->equityGroups($reporting),
            'form_bc_matrix' => $this->formBcMatrix($reporting),
            'annual_graduates' => $this->annualGraduates($filters),
        ];

        if ($includeDetails) {
            $analytics['detailed_enrollments'] = $this->details($reporting);
        }

        return [
            'analytics' => $analytics,
            'quality' => $this->quality($reporting),
            'report' => $report,
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
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

    /** @param array<string, int|string|null> $requested */
    private function reportContext(array $requested): array
    {
        $currentYear = $this->settingsService->getCurrentSchoolYearString();
        $values = [
            'school_year' => $requested['school_year'] ?? $currentYear,
            'semester' => (int) ($requested['semester'] ?? $this->settingsService->getCurrentSemester()),
            'department_id' => $requested['department_id'] ?? null,
            'course_id' => $requested['course_id'] ?? null,
            'academic_year' => $requested['academic_year'] ?? null,
            'gender' => $requested['gender'] ?? null,
            'student_type' => $requested['student_type'] ?? null,
            'intake_category' => $requested['intake_category'] ?? null,
            'status' => $requested['status'] ?? null,
        ];

        return [
            'values' => $values,
            'label' => sprintf('%s · %s', $values['school_year'], $this->semesterLabel((int) $values['semester'])),
            'options' => [
                'school_years' => collect($this->settingsService->getAvailableSchoolYears())->map(fn ($year): array => [
                    'value' => is_numeric($year) ? ((int) $year).' - '.((int) $year + 1) : (string) $year,
                    'label' => is_numeric($year) ? ((int) $year).' - '.((int) $year + 1) : (string) $year,
                ])->values(),
                'semesters' => collect([1, 2, 3])->map(fn (int $semester): array => ['value' => $semester, 'label' => $this->semesterLabel($semester)]),
                'departments' => Department::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (Department $department): array => ['value' => $department->id, 'label' => mb_trim($department->code.' — '.$department->name)]),
                'programs' => Course::query()->orderBy('code')->get(['id', 'department_id', 'code', 'title'])->map(fn (Course $course): array => ['value' => $course->id, 'department_id' => $course->department_id, 'label' => mb_trim($course->code.' — '.$course->title)]),
                'year_levels' => collect(range(1, 7))->map(fn (int $year): array => ['value' => $year, 'label' => "Year {$year}"]),
                'genders' => [['value' => 'male', 'label' => 'Male'], ['value' => 'female', 'label' => 'Female'], ['value' => 'unspecified', 'label' => 'Unspecified']],
                'student_types' => collect(StudentType::cases())->map(fn (StudentType $type): array => ['value' => $type->value, 'label' => $type->getLabel()]),
                'intake_categories' => [['value' => 'new_freshman', 'label' => 'New freshman'], ['value' => 'continuing_first_year', 'label' => 'Continuing first-year'], ['value' => 'unclassified', 'label' => 'Unclassified']],
                'statuses' => collect($this->enrollmentPipelineService->getSteps())->map(fn (array $step): array => ['value' => $step['status'], 'label' => $step['label']])->values(),
            ],
        ];
    }

    /** @param array<string, int|string|null> $filters */
    private function enrollmentQuery(array $filters): Builder
    {
        $query = StudentEnrollment::query()->withTrashed()
            ->where('student_enrollment.school_year', $filters['school_year'])
            ->leftJoin('students', function ($join): void {
                $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id')
                    ->whereRaw('(students.school_id = student_enrollment.school_id OR (students.school_id IS NULL AND student_enrollment.school_id IS NULL))');
            })
            ->leftJoin('courses', function ($join): void {
                $join->whereRaw("CAST(NULLIF(CAST(student_enrollment.course_id AS TEXT), '') AS BIGINT) = courses.id")
                    ->whereRaw('(courses.school_id = student_enrollment.school_id OR (courses.school_id IS NULL AND student_enrollment.school_id IS NULL))');
            })
            ->leftJoin('departments', function ($join): void {
                $join->on('courses.department_id', '=', 'departments.id')
                    ->whereRaw('(departments.school_id = student_enrollment.school_id OR (departments.school_id IS NULL AND student_enrollment.school_id IS NULL))');
            });

        if ($filters['semester'] !== null) {
            $query->where('student_enrollment.semester', $filters['semester']);
        }

        foreach (['department_id' => 'courses.department_id', 'course_id' => 'courses.id', 'academic_year' => 'student_enrollment.academic_year', 'student_type' => 'students.student_type', 'status' => 'student_enrollment.status'] as $filter => $column) {
            if ($filters[$filter] !== null) {
                $query->where($column, $filters[$filter]);
            }
        }

        if ($filters['gender'] !== null) {
            $filters['gender'] === 'unspecified'
                ? $query->where(function (Builder $query): void {
                    $query->whereNull('students.gender')->orWhereRaw("TRIM(COALESCE(students.gender, '')) = ''");
                })
                : $query->whereRaw('LOWER(TRIM(COALESCE(students.gender, \'\'))) = ?', [$filters['gender']]);
        }
        if ($filters['intake_category'] !== null) {
            $filters['intake_category'] === 'unclassified'
                ? $query->where('student_enrollment.academic_year', 1)->whereNull('student_enrollment.intake_category')
                : $query->where('student_enrollment.intake_category', $filters['intake_category']);
        }

        return $query;
    }

    /** @param array<string, int|string|null> $filters */
    private function schoolYearPopulation(array $filters, string $pendingStatus): Builder
    {
        $yearFilters = $filters;
        $yearFilters['semester'] = null;

        return $this->reportingPopulation($this->enrollmentQuery($yearFilters), $yearFilters, $pendingStatus);
    }

    /** @param array<string, int|string|null> $filters */
    private function reportingPopulation(Builder $query, array $filters, string $pendingStatus): Builder
    {
        if ($filters['status'] === null) {
            $query->where('student_enrollment.status', '!=', $pendingStatus);
        }

        return $query;
    }

    /** @param array<string, int|string|null> $filters */
    private function previousPeriodFilters(array $filters): array
    {
        $previous = $filters;
        if ((int) $filters['semester'] === 1) {
            preg_match('/^(\\d{4}) - \\d{4}$/', (string) $filters['school_year'], $matches);
            $start = isset($matches[1]) ? (int) $matches[1] - 1 : $this->settingsService->getCurrentSchoolYearStart() - 1;
            $previous['school_year'] = $start.' - '.($start + 1);
            $previous['semester'] = 2;
        } else {
            $previous['semester'] = (int) $filters['semester'] - 1;
        }

        return $previous;
    }

    private function byDepartment(Builder $query): mixed
    {
        return (clone $query)->selectRaw("COALESCE(NULLIF(TRIM(departments.code), ''), 'Unassigned') as department, count(*) as count")->groupByRaw("COALESCE(NULLIF(TRIM(departments.code), ''), 'Unassigned')")->orderByDesc('count')->get();
    }

    private function byProgram(Builder $query): mixed
    {
        return (clone $query)->selectRaw("COALESCE(NULLIF(TRIM(courses.code), ''), 'Unassigned') as program, courses.title as title, count(*) as count")->groupBy('courses.code', 'courses.title')->orderByDesc('count')->get();
    }

    private function byYearLevel(Builder $query): mixed
    {
        return (clone $query)->selectRaw('student_enrollment.academic_year as year_level, count(*) as count')->groupBy('student_enrollment.academic_year')->orderBy('student_enrollment.academic_year')->get();
    }

    private function byStudentType(Builder $query): mixed
    {
        return $this->groupStudentField($query, 'students.student_type', 'student_type');
    }

    private function byGender(Builder $query): mixed
    {
        return (clone $query)->selectRaw("COALESCE(NULLIF(LOWER(TRIM(students.gender)), ''), 'unspecified') as gender, count(*) as count")->groupByRaw("COALESCE(NULLIF(LOWER(TRIM(students.gender)), ''), 'unspecified')")->orderBy('gender')->get();
    }

    private function byStatus(Builder $query): mixed
    {
        return (clone $query)->selectRaw('student_enrollment.status, count(*) as count')->groupBy('student_enrollment.status')->orderByDesc('count')->get();
    }

    private function dailyTrend(Builder $query): mixed
    {
        return (clone $query)->selectRaw('DATE(student_enrollment.created_at) as date, count(*) as count')->groupByRaw('DATE(student_enrollment.created_at)')->orderBy('date')->get();
    }

    private function groupStudentField(Builder $query, string $field, string $alias): mixed
    {
        return (clone $query)->selectRaw("COALESCE(NULLIF(TRIM(CAST({$field} AS TEXT)), ''), 'Unspecified') as {$alias}, count(*) as count")->groupByRaw("COALESCE(NULLIF(TRIM(CAST({$field} AS TEXT)), ''), 'Unspecified')")->orderByDesc('count')->get();
    }

    private function equityGroups(Builder $query): array
    {
        $columns = ['Indigenous person' => 'students.is_indigenous_person', 'Person with disability' => 'students.is_pwd', 'Solo parent' => 'students.is_solo_parent', 'Underprivileged' => 'students.is_underprivileged', 'First generation' => 'students.is_first_generation'];

        return collect($columns)->map(fn (string $column, string $label): array => ['group' => $label, 'count' => (clone $query)->where($column, true)->count()])->values()->all();
    }

    private function formBcMatrix(Builder $query): mixed
    {
        $selects = ["COALESCE(NULLIF(TRIM(courses.code), ''), 'Unassigned') as program_code", 'courses.title as program_title', "COALESCE(NULLIF(TRIM(departments.code), ''), 'Unassigned') as department"];
        $reportedConditions = [];
        foreach (['new_freshman' => "student_enrollment.academic_year = 1 AND student_enrollment.intake_category = 'new_freshman'", 'continuing_first_year' => "student_enrollment.academic_year = 1 AND student_enrollment.intake_category = 'continuing_first_year'"] as $key => $condition) {
            foreach (['male', 'female'] as $gender) {
                $reportedCondition = "{$condition} AND LOWER(TRIM(COALESCE(students.gender, ''))) = '{$gender}'";
                $selects[] = "SUM(CASE WHEN {$reportedCondition} THEN 1 ELSE 0 END) as {$key}_{$gender}";
                $reportedConditions[] = $reportedCondition;
            }
        }
        foreach (range(2, 7) as $year) {
            foreach (['male', 'female'] as $gender) {
                $reportedCondition = "student_enrollment.academic_year = {$year} AND LOWER(TRIM(COALESCE(students.gender, ''))) = '{$gender}'";
                $selects[] = "SUM(CASE WHEN {$reportedCondition} THEN 1 ELSE 0 END) as year_{$year}_{$gender}";
                $reportedConditions[] = $reportedCondition;
            }
        }
        $selects[] = 'SUM(CASE WHEN '.implode(' OR ', $reportedConditions).' THEN 1 ELSE 0 END) as total';

        return (clone $query)->selectRaw(implode(', ', $selects))->groupBy('courses.code', 'courses.title', 'departments.code')->orderBy('courses.code')->get();
    }

    /** @param array<string, int|string|null> $filters */
    private function annualGraduates(array $filters): mixed
    {
        $query = Student::query()->withTrashed()->where('students.status', StudentStatus::Graduated->value)->where('students.graduation_school_year', $filters['school_year'])
            ->leftJoin('courses', function ($join): void {
                $join->on('students.course_id', '=', 'courses.id')->whereRaw('(courses.school_id = students.school_id OR (courses.school_id IS NULL AND students.school_id IS NULL))');
            })
            ->leftJoin('departments', function ($join): void {
                $join->on('courses.department_id', '=', 'departments.id')->whereRaw('(departments.school_id = students.school_id OR (departments.school_id IS NULL AND students.school_id IS NULL))');
            });
        foreach (['department_id' => 'courses.department_id', 'course_id' => 'courses.id', 'student_type' => 'students.student_type'] as $filter => $column) {
            if ($filters[$filter] !== null) {
                $query->where($column, $filters[$filter]);
            }
        }
        if ($filters['gender'] !== null && $filters['gender'] !== 'unspecified') {
            $query->whereRaw('LOWER(TRIM(COALESCE(students.gender, \'\'))) = ?', [$filters['gender']]);
        }

        return $query->selectRaw("COALESCE(NULLIF(TRIM(courses.code), ''), 'Unassigned') as program, COALESCE(NULLIF(LOWER(TRIM(students.gender)), ''), 'unspecified') as gender, count(*) as count")->groupBy('courses.code', 'students.gender')->orderBy('courses.code')->get();
    }

    private function details(Builder $query): mixed
    {
        return (clone $query)->selectRaw('students.student_id as student_reference, students.first_name, students.last_name, students.middle_name, students.suffix, TRIM(students.gender) as gender, students.student_type, courses.code as course_code, courses.title as course_title, TRIM(departments.code) as department, student_enrollment.academic_year as year_level, student_enrollment.intake_category, student_enrollment.status, student_enrollment.created_at')->orderBy('departments.code')->orderBy('students.last_name')->get();
    }

    private function quality(Builder $query): array
    {
        $graduateMissing = (clone $query)->where('students.status', StudentStatus::Graduated->value)->where(function (Builder $query): void {
            $query->whereNull('students.graduation_school_year')->orWhereNull('students.graduation_semester');
        })->count();

        return [
            'missing_department_count' => (clone $query)->whereNull('departments.id')->count(),
            'missing_course_count' => (clone $query)->whereNull('courses.id')->count(),
            'missing_student_record_count' => (clone $query)->whereNull('students.id')->count(),
            'without_gender_count' => (clone $query)->where(function (Builder $query): void {
                $query->whereNull('students.gender')->orWhereRaw("TRIM(COALESCE(students.gender, '')) = ''");
            })->count(),
            'unclassified_first_year_intake_count' => (clone $query)->where('student_enrollment.academic_year', 1)->whereNull('student_enrollment.intake_category')->count(),
            'missing_program_metadata_count' => (clone $query)->where(function (Builder $query): void {
                $query->whereNull('courses.ched_program_status')->orWhereNull('courses.ched_authority_category')->orWhereNull('courses.ched_delivery_mode')->orWhereNull('courses.ched_normal_length_years')->orWhereNull('courses.ched_program_credit_units');
            })->count(),
            'reporting_confirmation_missing_count' => (clone $query)->whereNull('students.profile_reporting_confirmed_at')->count(),
            'missing_graduation_period_count' => $graduateMissing,
        ];
    }

    private function semesterLabel(int $semester): string
    {
        return match ($semester) {
            1 => '1st Term', 2 => '2nd Term', 3 => 'Summer Term', default => "Term {$semester}"
        };
    }
}
