<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use Filament\Widgets\ChartWidget;

final class EnrollmentStatusChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Enrollment Workflow Status';

    protected ?string $description = 'Current status distribution in the enrollment process';

    protected string $color = 'warning';

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '400px';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $labels = $pipeline->getStatusLabels();
        $pendingStatus = $pipeline->getPendingStatus();
        $departmentStatus = $pipeline->getDepartmentVerifiedStatus();
        $cashierStatus = $pipeline->getCashierVerifiedStatus();

        $pendingCount = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->where('status', $pendingStatus)
            ->count();

        $verifiedByHeadCount = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->where('status', $departmentStatus)
            ->count();

        // Verified by Cashier: Should show 0 since cashier verification completes enrollment
        $verifiedByCashierCount = 0; // No longer a separate stat

        // Enrolled Students: soft-deleted (with receipt) OR active with "Verified By Cashier" status (no receipt)
        // Both types are considered "enrolled" for analytics
        $enrolledCount = StudentEnrollment::currentAcademicPeriod()
            ->where(function ($query) use ($cashierStatus): void {
                $query->whereNotNull('deleted_at') // Soft-deleted (regular verification with receipt)
                    ->orWhere(function ($q) use ($cashierStatus): void {
                        $q->whereNull('deleted_at')
                            ->where('status', $cashierStatus); // No-receipt verification
                    });
            })
            ->withTrashed()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Enrollment Status',
                    'data' => [
                        $pendingCount,
                        $verifiedByHeadCount,
                        $verifiedByCashierCount,
                        $enrolledCount,
                    ],
                    'backgroundColor' => [
                        'rgba(156, 163, 175, 0.8)',  // Gray for Pending
                        'rgba(34, 197, 94, 0.8)',    // Green for Verified by Head
                        'rgba(16, 185, 129, 0.8)',   // Emerald for Verified by Cashier
                        'rgba(34, 197, 94, 0.8)',    // Green for Enrolled
                    ],
                    'borderColor' => [
                        'rgba(156, 163, 175, 1)',
                        'rgba(34, 197, 94, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(34, 197, 94, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => [
                $labels[$pendingStatus] ?? 'Pending',
                $labels[$departmentStatus] ?? 'Verified by Head',
                $labels[$cashierStatus] ?? 'Verified by Cashier',
                $labels[$cashierStatus] ?? 'Enrolled',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.parsed / total) * 100);
                            return context.label + ": " + context.parsed + " (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
