<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Filament\Widgets\RecentStudentRegistrationsTable;
use App\Filament\Widgets\RecentStudentsTable;
use App\Filament\Widgets\StudentAcademicYearChart;
use App\Filament\Widgets\StudentAnalyticsStatsOverview;
use App\Filament\Widgets\StudentClearanceStatusChart;
use App\Filament\Widgets\StudentCourseDistributionChart;
use App\Filament\Widgets\StudentEnrollmentByTypeChart;
use App\Filament\Widgets\StudentEnrollmentTrendsChart;
use App\Filament\Widgets\StudentGenderDistributionChart;
use App\Filament\Widgets\StudentStatsOverview;
use App\Filament\Widgets\StudentStatusChart;
use App\Filament\Widgets\StudentTypeDistributionChart;
use App\Filament\Widgets\StudentYearLevelChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StudentStatsOverview::class,
            StudentAnalyticsStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            StudentTypeDistributionChart::class,
            StudentCourseDistributionChart::class,
            StudentAcademicYearChart::class,
            StudentYearLevelChart::class,
            StudentClearanceStatusChart::class,
            StudentEnrollmentByTypeChart::class,
            StudentEnrollmentTrendsChart::class,
            StudentGenderDistributionChart::class,
            StudentStatusChart::class,
            RecentStudentRegistrationsTable::class,
            RecentStudentsTable::class,
        ];
    }
}
