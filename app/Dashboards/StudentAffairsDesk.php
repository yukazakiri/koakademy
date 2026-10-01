<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;

/**
 * Student welfare desk for guidance counsellors and student affairs officers.
 *
 * Orientation is "who needs a human conversation", not institutional throughput.
 */
final class StudentAffairsDesk implements Dashboard
{
    public function id(): string
    {
        return 'student-affairs';
    }

    public function title(): string
    {
        return 'Student Affairs';
    }

    public function description(): string
    {
        return 'Student wellbeing signals: stalled enrollments and clearance holds.';
    }

    /**
     * view_clearance alone is also held by the registrar, so this desk additionally requires
     * manage_clearance, which only the welfare offices hold.
     *
     * @param  list<string>  $permissions
     */
    public function canView(User $user, array $permissions = []): bool
    {
        if ($permissions === []) {
            return in_array($user->role, [
                UserRole::StudentAffairsOfficer,
                UserRole::GuidanceCounselor,
            ], true);
        }

        return array_intersect(['manage_clearance'], $permissions) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $pendingStatus = $pipeline->getPendingStatus();

        $stalled = StudentEnrollment::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->where('status', $pendingStatus)
            ->where('created_at', '<=', now()->subDays(14)->toDateTimeString())
            ->count();

        $pending = StudentEnrollment::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->where('status', $pendingStatus)
            ->count();

        $population = Student::query()->count();

        return [
            'kpis' => [
                [
                    'label' => 'Pending Enrollments',
                    'value' => $pending,
                    'description' => 'Students awaiting a decision.',
                    'tone' => $pending > 0 ? 'warning' : 'success',
                    'format' => 'number',
                ],
                [
                    'label' => 'Stalled 14+ Days',
                    'description' => 'Pending for more than two weeks.',
                    'value' => $stalled,
                    'tone' => $stalled > 0 ? 'warning' : 'success',
                    'format' => 'number',
                ],
                [
                    'label' => 'Student Population',
                    'value' => $population,
                    'description' => 'Registered student profiles.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
            ],
            'queues' => $stalled > 0
                ? [[
                    'id' => 'stalled-enrollments',
                    'title' => 'Stalled enrollments',
                    'description' => 'Pending over 14 days and likely to need outreach.',
                    'count' => $stalled,
                    'severity' => 'warning',
                    'href' => '/administrators/enrollments',
                    'icon' => 'user-check',
                ]]
                : [],
            'trends' => [],
            'tables' => [],
        ];
    }
}
