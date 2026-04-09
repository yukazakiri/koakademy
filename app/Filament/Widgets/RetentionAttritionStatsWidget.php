<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AttritionCategory;
use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class RetentionAttritionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 22;

    protected ?string $heading = 'Retention & Attrition Analysis';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // Total active students (enrolled + on leave)
        $activeStudents = Student::whereIn('status', [
            StudentStatus::Enrolled->value,
            StudentStatus::OnLeave->value,
        ])->count();

        // Attrition metrics
        $totalWithdrawn = Student::where('status', StudentStatus::Withdrawn->value)->count();
        $totalDropped = Student::where('status', StudentStatus::Dropped->value)->count();
        $totalAttrition = $totalWithdrawn + $totalDropped;

        // Calculate retention rate
        $totalEverEnrolled = $activeStudents + $totalAttrition;
        $retentionRate = $totalEverEnrolled > 0
            ? round(($activeStudents / $totalEverEnrolled) * 100, 1)
            : 0;

        // Top attrition reason
        $topAttritionReason = Student::whereIn('status', [
            StudentStatus::Withdrawn->value,
            StudentStatus::Dropped->value,
        ])
            ->whereNotNull('attrition_category')
            ->selectRaw('attrition_category, COUNT(*) as count')
            ->groupBy('attrition_category')
            ->orderByDesc('count')
            ->first();

        $topReasonLabel = $topAttritionReason
            ? AttritionCategory::from($topAttritionReason->attrition_category)->getLabel()
            : 'N/A';

        // Recent attrition (last 30 days)
        $recentAttrition = Student::whereIn('status', [
            StudentStatus::Withdrawn->value,
            StudentStatus::Dropped->value,
        ])
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('Retention Rate', "{$retentionRate}%")
                ->description("{$activeStudents} of {$totalEverEnrolled} students retained")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($retentionRate >= 80 ? 'success' : ($retentionRate >= 60 ? 'warning' : 'danger')),

            Stat::make('Total Attrition', number_format($totalAttrition))
                ->description("{$recentAttrition} in last 30 days")
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart($this->getAttritionTrend()),

            Stat::make('Top Attrition Reason', $topReasonLabel)
                ->description("Withdrawn: {$totalWithdrawn}, Dropped: {$totalDropped}")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }

    private function getAttritionTrend(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Student::whereIn('status', [
                StudentStatus::Withdrawn->value,
                StudentStatus::Dropped->value,
            ])
                ->whereYear('updated_at', $date->year)
                ->whereMonth('updated_at', $date->month)
                ->count();
            $data[] = $count;
        }

        return $data;
    }
}
