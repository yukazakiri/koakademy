<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\StudentMedicalRecords\Models\MedicalRecord;

final class MedicalStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalRecords = MedicalRecord::count();
        $urgentRecords = MedicalRecord::urgent()->count();
        $emergencyRecords = MedicalRecord::emergency()->count();
        $needsFollowUp = MedicalRecord::query()->needsFollowUp()->count();
        $confidentialRecords = MedicalRecord::confidential()->count();

        return [
            Stat::make('📋 Total Medical Records', $totalRecords)
                ->description('All medical records in the system')
                ->descriptionIcon('heroicon-m-heart')
                ->color('primary'),

            Stat::make('🔴 Urgent Cases', $urgentRecords)
                ->description('Requires immediate attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($urgentRecords > 0 ? 'danger' : 'success'),

            Stat::make('🚨 Emergency Cases', $emergencyRecords)
                ->description('Emergency medical incidents')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($emergencyRecords > 0 ? 'danger' : 'success'),

            Stat::make('🔄 Needs Follow-up', $needsFollowUp)
                ->description('Scheduled for follow-up')
                ->descriptionIcon('heroicon-m-calendar')
                ->color($needsFollowUp > 0 ? 'warning' : 'success'),

            Stat::make('🔒 Confidential Records', $confidentialRecords)
                ->description('Restricted access records')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($confidentialRecords > 0 ? 'warning' : 'success'),
        ];
    }
}
