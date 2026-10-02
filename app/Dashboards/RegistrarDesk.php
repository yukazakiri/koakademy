<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Enums\UserRole;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use Flowframe\Trend\Trend;

/**
 * Admissions and records desk for the Registrar's office.
 *
 * Everything here is a queue the registrar has to work through, which is why this desk keeps
 * the pipeline and review counts on first paint.
 */
final class RegistrarDesk implements Dashboard
{
    public function id(): string
    {
        return 'registrar';
    }

    public function title(): string
    {
        return 'Registrar';
    }

    public function description(): string
    {
        return 'Admission pipeline, pending reviews and student records clearance.';
    }

    /**
     * Admissions staff only, gated on the role.
     *
     * A permission gate cannot separate the two registrar roles from the welfare offices:
     * manage_enrollments is registrar-only, but view_clearance is held by the assistant
     * registrar, the guidance counsellor and student affairs alike, and manage_clearance is
     * held by the guidance counsellor too. Requiring manage_enrollments would lock the assistant
     * registrar out of the desk meant for them. The role list is unambiguous; sysadmins bypass
     * this via DashboardRegistry.
     */
    public function canView(User $user, array $permissions = []): bool
    {
        return in_array($user->role, [UserRole::Registrar, UserRole::AssistantRegistrar], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array
    {
        $pendingStatus = app(EnrollmentPipelineService::class)->getPendingStatus();

        $pending = StudentEnrollment::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->where('status', $pendingStatus)
            ->count();

        $total = StudentEnrollment::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->count();

        $pipeline = $this->pipeline($context);

        $conversion = $total > 0 ? round((($total - $pending) / $total) * 100, 1) : 0.0;

        return [
            'kpis' => [
                [
                    'label' => 'Awaiting Review',
                    'value' => $pending,
                    'description' => 'Enrollment requests not yet actioned.',
                    'tone' => $pending > 0 ? 'warning' : 'success',
                    'format' => 'number',
                ],
                [
                    'label' => 'Total Requests',
                    'value' => $total,
                    'description' => $context->periodLabel(),
                    'tone' => 'info',
                    'format' => 'number',
                ],
                [
                    'label' => 'Cleared Rate',
                    'value' => sprintf('%.1f%%', $conversion),
                    'description' => 'Share of requests past the pending step.',
                    'tone' => $conversion >= 70 ? 'success' : 'warning',
                    'format' => 'percent',
                ],
            ],
            'queues' => [
                [
                    'id' => 'pending-reviews',
                    'title' => 'Enrollment reviews',
                    'description' => 'Requests waiting on a registrar decision.',
                    'count' => $pending,
                    'severity' => $pending > 0 ? 'warning' : 'success',
                    'href' => '/administrators/enrollments',
                    'icon' => 'clipboard-check',
                ],
                [
                    'id' => 'applicants',
                    'title' => 'New applicants',
                    'description' => 'Applicants not yet converted to an enrollment.',
                    'count' => StudentEnrollment::query()
                        ->where('school_year', $context->schoolYear)
                        ->where('semester', $context->semester)
                        ->where('status', $pendingStatus)
                        ->count(),
                    'severity' => 'info',
                    'href' => '/administrators/enrollments/applicants',
                    'icon' => 'users',
                ],
                [
                    'id' => 'clearance',
                    'title' => 'Uncleared students',
                    'description' => 'Students this term still holding a clearance requirement.',
                    'count' => StudentClearance::query()
                        ->where('academic_year', $context->schoolYear)
                        ->where('semester', $context->semester)
                        ->where('is_cleared', false)
                        ->count(),
                    'severity' => 'info',
                    'href' => '/administrators/students',
                    'icon' => 'shield-check',
                    'permission' => 'view_clearance',
                ],
            ],
            'trends' => [
                [
                    'id' => 'applications',
                    'title' => 'Applications received',
                    'description' => 'Enrollment records created per month.',
                    'data' => $this->trend($context),
                ],
            ],
            'tables' => [
                [
                    'id' => 'pipeline',
                    'title' => 'Pipeline distribution',
                    'description' => 'Where this term\'s requests currently sit.',
                    'columns' => [
                        ['key' => 'label', 'label' => 'Stage'],
                        ['key' => 'count', 'label' => 'Records', 'align' => 'end'],
                    ],
                    'rows' => $pipeline,
                ],
            ],
        ];
    }

    /**
     * Stage counts from the configured pipeline rather than hardcoded statuses, so it tracks
     * EnrollmentPipelineService changes automatically.
     *
     * @return list<array{label: string, count: int}>
     */
    private function pipeline(DashboardContext $context): array
    {
        $service = app(EnrollmentPipelineService::class);

        $counts = StudentEnrollment::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect($service->getSteps())
            ->map(fn (array $step): array => [
                // Enrollments store the step's status, not its key.
                'key' => (string) ($step['status'] ?? $step['key'] ?? ''),
                'label' => (string) ($step['label'] ?? $step['status'] ?? ''),
                'count' => (int) ($counts[(string) ($step['status'] ?? '')] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function trend(DashboardContext $context): array
    {
        return Trend::model(StudentEnrollment::class)
            ->between($context->from, $context->to)
            ->perMonth()
            ->count()
            ->map(fn (object $trend): array => [
                'date' => $trend->date,
                'label' => date('M Y', strtotime((string) $trend->date)),
                'enrollments' => $trend->aggregate,
            ])
            ->values()
            ->all();
    }
}
