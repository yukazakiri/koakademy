<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ApplicationEnrollmentStatsWidget extends BaseWidget
{
    protected static ?int $sort = 20;

    protected ?string $heading = 'Application vs Enrollment Tracking';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // Application vs Enrollment metrics
        $totalApplicants = Student::where('status', StudentStatus::Applicant->value)->count();
        $totalEnrolled = Student::where('status', StudentStatus::Enrolled->value)->count();
        $totalOnLeave = Student::where('status', StudentStatus::OnLeave->value)->count();

        // Calculate conversion rate
        $totalProcessed = $totalApplicants + $totalEnrolled;
        $conversionRate = $totalProcessed > 0
            ? round(($totalEnrolled / $totalProcessed) * 100, 1)
            : 0;

        // Recent applicants (last 30 days)
        $recentApplicants = Student::where('status', StudentStatus::Applicant->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('Total Applicants', number_format($totalApplicants))
                ->description("{$recentApplicants} new in last 30 days")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning')
                ->chart($this->getApplicantTrend()),

            Stat::make('Currently Enrolled', number_format($totalEnrolled))
                ->description("{$conversionRate}% conversion rate")
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success')
                ->chart($this->getEnrollmentTrend()),

            Stat::make('On Leave', number_format($totalOnLeave))
                ->description('Students temporarily away')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('info'),
        ];
    }

    private function getApplicantTrend(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Student::where('status', StudentStatus::Applicant->value)
                ->whereDate('created_at', $date)
                ->count();
            $data[] = $count;
        }

        return $data;
    }

    private function getEnrollmentTrend(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Student::where('status', StudentStatus::Enrolled->value)
                ->whereDate('created_at', $date)
                ->count();
            $data[] = $count;
        }

        return $data;
    }
}
