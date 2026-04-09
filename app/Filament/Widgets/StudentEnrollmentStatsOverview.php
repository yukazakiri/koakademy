<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\StudentEnrollments\Pages\ListStudentEnrollments;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StudentEnrollmentStatsOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Student Enrollment Statistics';

    protected ?string $description = 'Overview based on current filters';

    protected ?string $pollingInterval = null; // Disable polling to avoid resetting filters/query issues

    protected function getTablePage(): string
    {
        return ListStudentEnrollments::class;
    }

    protected function getStats(): array
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $labels = $pipeline->getStatusLabels();

        // Get the query from the table, respecting all current filters
        $query = $this->getPageTableQuery();

        // If no query is available (e.g. widget used outside of table context), fallback to default
        if (! $query) {
            $query = StudentEnrollment::currentAcademicPeriod()->withTrashed();
        }

        // Clone query for each stat to avoid modifying the base query reference
        $totalCount = (clone $query)->count();

        // Calculate status counts based on the FILTERED data
        $pendingCount = (clone $query)->where('status', $pipeline->getPendingStatus())->count();
        $verifiedByHeadCount = (clone $query)->where('status', $pipeline->getDepartmentVerifiedStatus())->count();
        $verifiedByCashierCount = (clone $query)->where('status', $pipeline->getCashierVerifiedStatus())->count();

        // For "Enrolled", we typically mean Verified By Cashier (active) OR Soft Deleted (receipt generated)
        // But since we are filtering, we should just show what's in the current filtered view
        // If the user filters by "Pending", Enrolled should be 0.

        // Let's provide stats relevant to the current view
        return [
            Stat::make('Total Records', number_format($totalCount))
                ->description('Matches current filters')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('primary'),

            Stat::make($labels[$pipeline->getPendingStatus()] ?? 'Pending', number_format($pendingCount))
                ->description('In current selection')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make($labels[$pipeline->getDepartmentVerifiedStatus()] ?? 'Verified by Head', number_format($verifiedByHeadCount))
                ->description('In current selection')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),

            Stat::make($labels[$pipeline->getCashierVerifiedStatus()] ?? 'Verified by Cashier', number_format($verifiedByCashierCount))
                ->description('In current selection')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}
