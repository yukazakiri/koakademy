<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
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

        $pendingEnrollments = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->where('status', $pipeline->getPendingStatus())
            ->count();

        $enrolledThisPeriod = self::getEnrolledCountForCurrentPeriod();

        // Get all student statistics in a single query
        $studentStats = self::getAggregatedStudentStats();

        $totalStudents = $studentStats->total;
        $applicationStats = self::buildApplicationStats($studentStats);

        $stats = [
            [
                'label' => 'Pending Enrollments',
                'value' => $pendingEnrollments,
                'description' => 'Enrollment requests awaiting review',
                'tone' => $pendingEnrollments > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Enrolled This Period',
                'value' => $enrolledThisPeriod,
                'description' => 'Verified enrollments for current term',
                'tone' => 'info',
            ],
            [
                'label' => 'Total Students',
                'value' => $totalStudents,
                'description' => 'All student profiles in the system',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Conversion Rate',
                'value' => sprintf('%.1f%%', $applicationStats['conversion_rate']),
                'description' => 'Applicants converted to enrolled',
                'tone' => $applicationStats['conversion_rate'] >= 70 ? 'success' : 'warning',
            ],
        ];

        return [
            'stats' => $stats,
            'recent_activity' => self::getRecentActivity(),
            'analytics' => [
                'last_updated_at' => now()->toIso8601String(),
                'enrollment_trends' => self::getEnrollmentTrends(),
                'enrollment_status' => self::getEnrollmentStatusDistribution($pendingEnrollments, $enrolledThisPeriod),
                'application_vs_enrollment' => $applicationStats,
                'student_types' => self::buildStudentTypeDistribution($studentStats, $totalStudents),
                'gender_distribution' => self::buildGenderDistribution($studentStats),
                'year_level_distribution' => self::buildYearLevelDistribution($studentStats),
                'top_courses' => self::getTopCourses(),
                'recent_students' => self::getRecentStudents(),
            ],
        ];
    }

    /**
     * Get all student statistics in a single aggregated query.
     * This replaces 16+ individual COUNT queries with 1 query.
     */
    private static function getAggregatedStudentStats(): object
    {
        return Student::query()
            ->selectRaw("
                count(*) as total,
                count(case when student_type = 'college' then 1 end) as type_college,
                count(case when student_type = 'shs' then 1 end) as type_shs,
                count(case when student_type = 'tesda' then 1 end) as type_tesda,
                count(case when student_type = 'dhrt' then 1 end) as type_dhrt,
                count(case when gender = 'male' then 1 end) as gender_male,
                count(case when gender = 'female' then 1 end) as gender_female,
                count(case when gender not in ('male', 'female') or gender is null then 1 end) as gender_other,
                count(case when academic_year = 1 then 1 end) as year_1,
                count(case when academic_year = 2 then 1 end) as year_2,
                count(case when academic_year = 3 then 1 end) as year_3,
                count(case when academic_year = 4 then 1 end) as year_4,
                count(case when academic_year = 5 then 1 end) as year_5,
                count(case when status = 'applicant' then 1 end) as status_applicant,
                count(case when status = 'enrolled' then 1 end) as status_enrolled,
                count(case when status = 'on_leave' then 1 end) as status_on_leave
            ")
            ->first();
    }

    /**
     * Build application vs enrollment stats from aggregated data.
     *
     * @return array{applicants: int, enrolled: int, on_leave: int, conversion_rate: float}
     */
    private static function buildApplicationStats(object $stats): array
    {
        $totalApplicants = (int) $stats->status_applicant;
        $totalEnrolled = (int) $stats->status_enrolled;
        $totalOnLeave = (int) $stats->status_on_leave;

        $totalProcessed = $totalApplicants + $totalEnrolled;
        $conversionRate = $totalProcessed > 0
            ? round(($totalEnrolled / $totalProcessed) * 100, 1)
            : 0.0;

        return [
            'applicants' => $totalApplicants,
            'enrolled' => $totalEnrolled,
            'on_leave' => $totalOnLeave,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Build student type distribution from aggregated data.
     *
     * @return array<int, array{type: string, label: string, count: int, percentage: float}>
     */
    private static function buildStudentTypeDistribution(object $stats, int $totalStudents): array
    {
        $typeMapping = [
            'college' => 'type_college',
            'shs' => 'type_shs',
            'tesda' => 'type_tesda',
            'dhrt' => 'type_dhrt',
        ];

        return collect(StudentType::cases())
            ->map(function (StudentType $type) use ($stats, $totalStudents, $typeMapping): array {
                $column = $typeMapping[$type->value] ?? null;
                $count = $column !== '' && $column !== '0' ? (int) ($stats->{$column} ?? 0) : 0;
                $percentage = $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0.0;

                return [
                    'type' => $type->value,
                    'label' => $type->getLabel() ?? $type->value,
                    'count' => $count,
                    'percentage' => $percentage,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build gender distribution from aggregated data.
     *
     * @return array<int, array{gender: string, count: int}>
     */
    private static function buildGenderDistribution(object $stats): array
    {
        return [
            ['gender' => 'Male', 'count' => (int) $stats->gender_male],
            ['gender' => 'Female', 'count' => (int) $stats->gender_female],
            ['gender' => 'Other', 'count' => (int) $stats->gender_other],
        ];
    }

    /**
     * Build year level distribution from aggregated data.
     *
     * @return array<int, array{year_level: string, count: int}>
     */
    private static function buildYearLevelDistribution(object $stats): array
    {
        return [
            ['year_level' => '1st Year', 'count' => (int) $stats->year_1],
            ['year_level' => '2nd Year', 'count' => (int) $stats->year_2],
            ['year_level' => '3rd Year', 'count' => (int) $stats->year_3],
            ['year_level' => '4th Year', 'count' => (int) $stats->year_4],
            ['year_level' => 'Graduates', 'count' => (int) $stats->year_5],
        ];
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
     * @return array<int, array{month: string, enrollments: int}>
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
                'month' => Carbon::parse($value->date)->format('M'),
                'enrollments' => (int) $value->aggregate,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private static function getEnrollmentStatusDistribution(int $pendingCount, int $enrolledCount): array
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $verificationStatus = $pipeline->getDepartmentVerifiedStatus();
        $pendingLabel = $pipeline->getStatusLabels()[$pipeline->getPendingStatus()] ?? 'Pending';
        $verificationLabel = $pipeline->getStatusLabels()[$verificationStatus] ?? 'In Verification';

        $verifiedByHeadCount = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->where('status', $verificationStatus)
            ->count();

        return [
            ['status' => $pendingLabel, 'count' => $pendingCount],
            ['status' => $verificationLabel, 'count' => $verifiedByHeadCount],
            ['status' => 'Enrolled', 'count' => $enrolledCount],
        ];
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
}
