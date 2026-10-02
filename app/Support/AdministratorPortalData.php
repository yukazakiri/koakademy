<?php

declare(strict_types=1);

namespace App\Support;

use App\Dashboards\Support\StatAggregates;
use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use Carbon\Carbon;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Spatie\Activitylog\Models\Activity;

final class AdministratorPortalData
{
    /**
     * Build dashboard payload for an administrator.
     *
     * @return array{
     *     stats: array<int, array{label: string, value: int|string, description: string, tone: string}>,
     *     recent_activity: array<int, array{actor: string, action: string, time: string, status: string}>,
     *     analytics: array{
     *         last_updated_at: string,
     *         enrollment_trends: array<int, array{month: string, enrollments: int}>,
     *         enrollment_status: array<int, array{status: string, count: int}>,
     *         application_vs_enrollment: array{applicants: int, enrolled: int, on_leave: int, conversion_rate: float},
     *         student_types: array<int, array{type: string, label: string, count: int, percentage: float}>,
     *         gender_distribution: array<int, array{gender: string, count: int}>,
     *         year_level_distribution: array<int, array{year_level: string, count: int}>,
     *         top_courses: array<int, array{code: string, title: string, student_count: int}>,
     *         recent_students: array<int, array{id: int, student_id: string|null, name: string, type: string|null, status: string|null, course: string|null, registered_at: string}>
     *     }
     * }
     */
    public static function build(User $user): array
    {
        unset($user);
        $pipeline = app(EnrollmentPipelineService::class);
        $settings = app(GeneralSettingsService::class);
        $currentSchoolYear = $settings->getCurrentSchoolYearString();
        $currentSemester = $settings->getCurrentSemester();

        $pendingEnrollments = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->where('status', $pipeline->getPendingStatus())
            ->count();

        $enrolledThisPeriod = self::getEnrolledCountForCurrentPeriod();

        // Get all student statistics in a single query
        $studentStats = self::getAggregatedStudentStats();

        $totalStudents = $studentStats->total;
        $applicationStats = self::buildApplicationStats($studentStats);
        $financeSnapshot = self::getFinanceSnapshot($currentSchoolYear, $currentSemester);
        $enrollmentPipeline = self::getEnrollmentPipelineDistribution($pendingEnrollments, $enrolledThisPeriod);
        $enrollmentTrends = self::getEnrollmentTrends();
        $studentTypes = self::buildStudentTypeDistribution($studentStats, $totalStudents);
        $genderDistribution = self::buildGenderDistribution($studentStats);
        $yearLevelDistribution = self::buildYearLevelDistribution($studentStats);
        $topCourses = self::getTopCourses();
        $recentStudents = self::getRecentStudents();
        $recentActivity = self::getRecentActivity();
        $operations = self::getOperationsSnapshot($pendingEnrollments, $financeSnapshot['outstanding_count']);
        $pendingEnrollmentTrend = self::getEnrollmentTrendSeries($pipeline->getPendingStatus());
        $enrolledThisPeriodTrend = self::statSeriesFromTrend($enrollmentTrends, 'enrollments');
        $studentProfileTrend = self::getStudentProfileTrendSeries();
        $conversionRateTrend = self::getConversionRateTrendSeries();

        $stats = [
            [
                'label' => 'Pending Enrollments',
                'value' => $pendingEnrollments,
                'description' => 'Enrollment requests awaiting review',
                'tone' => $pendingEnrollments > 0 ? 'warning' : 'success',
                'trend' => self::calculateSeriesTrend($pendingEnrollmentTrend),
                'series' => $pendingEnrollmentTrend,
                'format' => 'number',
            ],
            [
                'label' => 'Enrolled This Period',
                'value' => $enrolledThisPeriod,
                'description' => 'Verified enrollments for current term',
                'tone' => 'info',
                'trend' => self::calculateSeriesTrend($enrolledThisPeriodTrend),
                'series' => $enrolledThisPeriodTrend,
                'format' => 'number',
            ],
            [
                'label' => 'Total Students',
                'value' => $totalStudents,
                'description' => 'All student profiles in the system',
                'tone' => 'neutral',
                'trend' => self::calculateSeriesTrend($studentProfileTrend),
                'series' => $studentProfileTrend,
                'format' => 'number',
            ],
            [
                'label' => 'Conversion Rate',
                'value' => sprintf('%.1f%%', $applicationStats['conversion_rate']),
                'description' => 'Applicants converted to enrolled',
                'tone' => $applicationStats['conversion_rate'] >= 70 ? 'success' : 'warning',
                'trend' => self::calculateSeriesTrend($conversionRateTrend),
                'series' => $conversionRateTrend,
                'format' => 'percent',
            ],
        ];

        return [
            'current_period' => [
                'school_year' => $currentSchoolYear,
                'semester' => $currentSemester,
                'label' => sprintf('SY %s, Semester %d', $currentSchoolYear, $currentSemester),
            ],
            'stats' => $stats,
            'executive_summary' => [
                'kpis' => $stats,
                'last_updated_at' => now()->toIso8601String(),
            ],
            'recent_activity' => $recentActivity,
            'enrollment_health' => [
                'pending' => $pendingEnrollments,
                'enrolled_this_period' => $enrolledThisPeriod,
                'conversion_rate' => $applicationStats['conversion_rate'],
                'applicants' => $applicationStats['applicants'],
                'enrolled' => $applicationStats['enrolled'],
                'on_leave' => $applicationStats['on_leave'],
                'pipeline' => $enrollmentPipeline,
                'trends' => $enrollmentTrends,
            ],
            'student_demographics' => [
                'total' => $totalStudents,
                'by_type' => $studentTypes,
                'by_gender' => $genderDistribution,
                'by_year_level' => $yearLevelDistribution,
                'top_courses' => $topCourses,
            ],
            'finance_snapshot' => $financeSnapshot,
            'operations' => $operations,
            'recent_records' => [
                'students' => $recentStudents,
                'activity' => $recentActivity,
            ],
            'analytics' => [
                'last_updated_at' => now()->toIso8601String(),
                'enrollment_trends' => $enrollmentTrends,
                'enrollment_status' => $enrollmentPipeline,
                'application_vs_enrollment' => $applicationStats,
                'student_types' => $studentTypes,
                'gender_distribution' => $genderDistribution,
                'year_level_distribution' => $yearLevelDistribution,
                'top_courses' => $topCourses,
                'recent_students' => $recentStudents,
            ],
        ];
    }

    /**
     * Shared aggregates live in StatAggregates so the dashboard desks compute identical
     * figures from identical SQL rather than each re-deriving them.
     */
    private static function aggregates(): StatAggregates
    {
        return app(StatAggregates::class);
    }

    /**
     * Get all student statistics in a single aggregated query.
     * This replaces 16+ individual COUNT queries with 1 query.
     */
    private static function getAggregatedStudentStats(): object
    {
        return self::aggregates()->studentStats();
    }

    /**
     * Build application vs enrollment stats from aggregated data.
     *
     * @return array{applicants: int, enrolled: int, on_leave: int, conversion_rate: float}
     */
    private static function buildApplicationStats(object $stats): array
    {
        return self::aggregates()->applicationStats($stats);
    }

    /**
     * Build student type distribution from aggregated data.
     *
     * @return array<int, array{type: string, label: string, count: int, percentage: float}>
     */
    private static function buildStudentTypeDistribution(object $stats, int $totalStudents): array
    {
        return self::aggregates()->studentTypeDistribution($stats, $totalStudents);
    }

    /**
     * Build gender distribution from aggregated data.
     *
     * @return array<int, array{gender: string, count: int}>
     */
    private static function buildGenderDistribution(object $stats): array
    {
        return self::aggregates()->genderDistribution($stats);
    }

    /**
     * Build year level distribution from aggregated data.
     *
     * @return array<int, array{year_level: string, count: int}>
     */
    private static function buildYearLevelDistribution(object $stats): array
    {
        return self::aggregates()->yearLevelDistribution($stats);
    }

    /**
     * Enrolled count logic matches `EnrollmentStatusChart`.
     */
    private static function getEnrolledCountForCurrentPeriod(): int
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $completedStatus = $pipeline->getCashierVerifiedStatus();

        return StudentEnrollment::currentAcademicPeriod()
            ->where(function ($query) use ($completedStatus): void {
                $query->whereNotNull('deleted_at')
                    ->orWhere(function ($q) use ($completedStatus): void {
                        $q->whereNull('deleted_at')
                            ->where('status', $completedStatus);
                    });
            })
            ->withTrashed()
            ->count();
    }

    /**
     * @return array<int, array{date: string, month: string, enrollments: int}>
     */
    private static function getEnrollmentTrends(): array
    {
        $start = now()->startOfYear();
        $end = now()->endOfYear();

        $data = Trend::query(StudentEnrollment::withTrashed())
            ->between(start: $start, end: $end)
            ->perMonth()
            ->count();

        return $data
            ->map(fn (TrendValue $value): array => [
                'date' => Carbon::parse($value->date)->startOfMonth()->toDateString(),
                'month' => Carbon::parse($value->date)->format('M'),
                'enrollments' => (int) $value->aggregate,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date: string, value: float|int}>
     */
    private static function getEnrollmentTrendSeries(?string $status = null): array
    {
        return self::aggregates()->monthlySeries(StudentEnrollment::withTrashed(), $status);
    }

    /**
     * @return array<int, array{date: string, value: float|int}>
     */
    private static function getStudentProfileTrendSeries(): array
    {
        return self::aggregates()->monthlySeries(Student::query());
    }

    /**
     * @return array<int, array{date: string, value: float|int}>
     */
    private static function getConversionRateTrendSeries(): array
    {
        $aggregates = self::aggregates();

        $applicantTrend = $aggregates->studentStatusSeries(StudentStatus::Applicant);
        $enrolledTrend = $aggregates->studentStatusSeries(StudentStatus::Enrolled);
        $applicantsByDate = collect($applicantTrend)->keyBy('date');

        return collect($enrolledTrend)
            ->map(function (array $enrolledPoint) use ($applicantsByDate): array {
                $date = (string) $enrolledPoint['date'];
                $enrolled = (int) $enrolledPoint['value'];
                $applicants = (int) ($applicantsByDate->get($date)['value'] ?? 0);
                $processed = $enrolled + $applicants;

                return [
                    'date' => $date,
                    'value' => $processed > 0 ? round(($enrolled / $processed) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date: string, value: float|int}>
     */
    private static function getStudentStatusTrendSeries(string $status): array
    {
        return self::aggregates()->monthlySeries(Student::query(), $status);
    }

    /**
     * @param  array<int, array<string, mixed>>  $trend
     * @return array<int, array{date: string, value: float|int}>
     */
    private static function statSeriesFromTrend(array $trend, string $valueKey): array
    {
        return self::aggregates()->toStatSeries($trend, $valueKey);
    }

    /**
     * @param  array<int, array{date: string, value: float|int}>  $series
     */
    private static function calculateSeriesTrend(array $series): float
    {
        return self::aggregates()->seriesTrend($series);
    }

    /**
     * @return array<int, array{status: string, count: int, color: string}>
     */
    private static function getEnrollmentPipelineDistribution(int $pendingCount, int $enrolledCount): array
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $statusCounts = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $completionStatus = $pipeline->getCashierVerifiedStatus();

        return collect($pipeline->getSteps())
            ->map(function (array $step) use ($completionStatus, $enrolledCount, $pendingCount, $pipeline, $statusCounts): array {
                $status = (string) $step['status'];
                $count = (int) ($statusCounts[$status] ?? 0);

                if ($status === $pipeline->getPendingStatus()) {
                    $count = $pendingCount;
                }

                if ($status === $completionStatus) {
                    $count = $enrolledCount;
                }

                return [
                    'status' => (string) ($step['label'] ?? $status),
                    'count' => $count,
                    'color' => (string) ($step['color'] ?? 'blue'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, student_count: int}>
     */
    private static function getTopCourses(): array
    {
        $courses = Course::getCoursesWithStudentCount()
            ->sortByDesc('student_count')
            ->take(8);

        return $courses
            ->map(fn (Course $course): array => [
                'code' => (string) $course->code,
                'title' => (string) $course->title,
                'student_count' => (int) ($course->student_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, student_id: string|null, name: string, type: string|null, status: string|null, course: string|null, registered_at: string}>
     */
    private static function getRecentStudents(): array
    {
        $students = Student::query()
            ->with(['Course'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        return $students
            ->map(function (Student $student): array {
                $studentType = $student->student_type;
                $studentStatus = $student->status;

                $type = $studentType instanceof StudentType
                    ? $studentType->value
                    : (is_string($studentType) ? $studentType : null);

                $status = $studentStatus instanceof StudentStatus
                    ? $studentStatus->value
                    : (is_string($studentStatus) ? $studentStatus : null);

                return [
                    'id' => (int) $student->id,
                    'student_id' => $student->student_id ? (string) $student->student_id : null,
                    'name' => (string) ($student->full_name ?? $student->first_name.' '.$student->last_name),
                    'type' => $type,
                    'status' => $status,
                    'course' => $student->Course?->code ? (string) $student->Course->code : null,
                    'registered_at' => $student->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{actor: string, action: string, time: string, status: string}>
     */
    private static function getRecentActivity(): array
    {
        $activities = Activity::query()
            ->with(['causer'])
            ->latest('id')
            ->limit(10)
            ->get();

        return $activities
            ->map(function (Activity $activity): array {
                $actorName = $activity->causer?->name;

                $actor = is_string($actorName) && $actorName !== ''
                    ? $actorName
                    : 'System';

                $subject = $activity->subject_type ? class_basename($activity->subject_type) : 'Item';
                $action = $activity->description
                    ? (string) $activity->description
                    : sprintf('%s %s', ucfirst((string) ($activity->event ?? 'updated')), $subject);

                $status = match ($activity->event) {
                    'created' => 'success',
                    'updated' => 'info',
                    'deleted' => 'warning',
                    default => 'info',
                };

                return [
                    'actor' => $actor,
                    'action' => $action,
                    'time' => $activity->created_at?->shiftTimezone(config('app.timezone'))->diffForHumans() ?? 'Just now',
                    'status' => $status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     total_revenue: float,
     *     total_collectibles: float,
     *     total_assessed: float,
     *     collection_rate: float,
     *     fully_paid_count: int,
     *     outstanding_count: int,
     *     today_collection: float,
     *     today_transactions: int
     * }
     */
    private static function getFinanceSnapshot(string $schoolYear, int $semester): array
    {
        return self::aggregates()->financeSnapshot($schoolYear, $semester);
    }

    /**
     * @return array{
     *     total_faculty: int,
     *     active_classes: int,
     *     total_users: int,
     *     unassigned_classes: int,
     *     action_queue: array<int, array{label: string, value: int, description: string, href: string, tone: string}>
     * }
     */
    private static function getOperationsSnapshot(int $pendingEnrollments, int $outstandingBalances): array
    {
        $activeClasses = Classes::currentAcademicPeriod()->count();
        $unassignedClasses = Classes::currentAcademicPeriod()
            ->whereNull('faculty_id')
            ->count();

        return [
            'total_faculty' => Faculty::query()->count(),
            'active_classes' => $activeClasses,
            'total_users' => User::query()->count(),
            'unassigned_classes' => $unassignedClasses,
            'action_queue' => [
                [
                    'label' => 'Enrollment reviews',
                    'value' => $pendingEnrollments,
                    'description' => 'Pending enrollment records awaiting staff review.',
                    'href' => route('administrators.enrollments.index'),
                    'tone' => $pendingEnrollments > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Outstanding balances',
                    'value' => $outstandingBalances,
                    'description' => 'Students with remaining tuition balances this period.',
                    'href' => route('administrators.finance.reports', ['tab' => 'outstanding']),
                    'tone' => $outstandingBalances > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Unassigned classes',
                    'value' => $unassignedClasses,
                    'description' => 'Current-period classes without an assigned faculty member.',
                    'href' => route('administrators.scheduling-analytics.index'),
                    'tone' => $unassignedClasses > 0 ? 'info' : 'success',
                ],
                [
                    'label' => 'Audit review',
                    'value' => Activity::query()->where('created_at', '>=', now()->subDay())->count(),
                    'description' => 'System events recorded in the last 24 hours.',
                    'href' => route('administrators.audit-logs.index'),
                    'tone' => 'neutral',
                ],
            ],
        ];
    }
}
