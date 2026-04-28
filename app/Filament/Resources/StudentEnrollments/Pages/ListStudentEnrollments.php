<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Pages;

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Widgets\EnrollmentByCourseStatsWidget;
use App\Filament\Widgets\EnrollmentStatusChart;
use App\Filament\Widgets\MissingClassEnrollmentsTable;
use App\Filament\Widgets\RecentEnrollmentsTable;
use App\Filament\Widgets\StudentEnrollmentStatsOverview;
use App\Filament\Widgets\StudentEnrollmentTrendsChart;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListStudentEnrollments extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = StudentEnrollmentResource::class;

    public function getTabs(): array
    {
        $pipeline = app(EnrollmentPipelineService::class);
        $pendingStatus = $pipeline->getPendingStatus();
        $deptVerifiedStatus = $pipeline->getDepartmentVerifiedStatus();
        $cashierVerifiedStatus = $pipeline->getCashierVerifiedStatus();
        $labels = $pipeline->getStatusLabels();
        $steps = $pipeline->getSteps();

        $tabs = [
            'all' => Tab::make('All Enrollments')
                ->icon('heroicon-m-academic-cap')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()->withTrashed()->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->currentAcademicPeriod()->withTrashed()),
            'bsit' => Tab::make('BSIT')
                ->icon('heroicon-m-computer-desktop')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()
                    ->withTrashed()
                    ->whereRaw("EXISTS (SELECT * FROM students WHERE CAST(student_enrollment.student_id AS BIGINT) = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSIT%'))")
                    ->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->withTrashed()
                    ->whereRaw("EXISTS (SELECT * FROM students WHERE CAST(student_enrollment.student_id AS BIGINT) = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSIT%'))")
                ),
            'bshm' => Tab::make('BSHM')
                ->icon('heroicon-m-building-storefront')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()
                    ->withTrashed()
                    ->whereRaw("EXISTS (SELECT * FROM students WHERE CAST(student_enrollment.student_id AS BIGINT) = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSHM%'))")
                    ->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->withTrashed()
                    ->whereRaw("EXISTS (SELECT * FROM students WHERE CAST(student_enrollment.student_id AS BIGINT) = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSHM%'))")
                ),
            'bsba' => Tab::make('BSBA')
                ->icon('heroicon-m-briefcase')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()
                    ->withTrashed()
                    ->whereRaw("EXISTS (SELECT * FROM students WHERE CAST(student_enrollment.student_id AS BIGINT) = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSBA%'))")
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->withTrashed()
                    ->whereRaw("EXISTS (SELECT * FROM students WHERE CAST(student_enrollment.student_id AS BIGINT) = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSBA%'))")
                ),
            'pending' => Tab::make('Pending')
                ->icon('heroicon-m-clock')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()->withTrashed()->where('status', $pendingStatus)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->withTrashed()
                    ->where('status', $pendingStatus)
                ),
            'verified_by_head' => Tab::make($labels[$deptVerifiedStatus] ?? 'Verified By Head')
                ->icon('heroicon-m-check-circle')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()->withTrashed()->where('status', $deptVerifiedStatus)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->withTrashed()
                    ->where('status', $deptVerifiedStatus)
                ),
            'enrolled_no_receipt' => Tab::make('Enrolled (No Receipt)')
                ->icon('heroicon-m-exclamation-triangle')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()
                    ->where('status', $cashierVerifiedStatus)
                    ->whereNull('deleted_at') // Only active (no-receipt verification)
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->where('status', $cashierVerifiedStatus)
                    ->whereNull('deleted_at') // Only active (no-receipt verification)
                ),
            'enrolled' => Tab::make($labels[$cashierVerifiedStatus] ?? 'Enrolled')
                ->icon('heroicon-m-academic-cap')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()
                    ->where(function ($query) use ($cashierVerifiedStatus): void {
                        $query->whereNotNull('deleted_at') // Soft-deleted (with receipt)
                            ->orWhere(function ($q) use ($cashierVerifiedStatus): void {
                                $q->whereNull('deleted_at')
                                    ->where('status', $cashierVerifiedStatus); // No-receipt verification
                            });
                    })
                    ->withTrashed()
                    ->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->where(function ($query) use ($cashierVerifiedStatus): void {
                        $query->whereNotNull('deleted_at') // Soft-deleted (with receipt)
                            ->orWhere(function ($q) use ($cashierVerifiedStatus): void {
                                $q->whereNull('deleted_at')
                                    ->where('status', $cashierVerifiedStatus); // No-receipt verification
                            });
                    })
                    ->withTrashed()
                ),
        ];

        foreach ($steps as $step) {
            if ($step['status'] === $pendingStatus) {
                continue;
            }
            if ($step['status'] === $deptVerifiedStatus) {
                continue;
            }
            if ($step['status'] === $cashierVerifiedStatus) {
                continue;
            }
            $tabKey = 'pipeline_'.str($step['status'])->slug('_');
            $tabs[$tabKey] = Tab::make($step['label'])
                ->icon('heroicon-m-adjustments-vertical')
                ->badge(fn () => StudentEnrollment::currentAcademicPeriod()->withTrashed()->where('status', $step['status'])->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->currentAcademicPeriod()
                    ->withTrashed()
                    ->where('status', $step['status'])
                );
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string
    {
        return 'all';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StudentEnrollmentStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            StudentEnrollmentTrendsChart::class,
            EnrollmentByCourseStatsWidget::class,
            EnrollmentStatusChart::class,
            MissingClassEnrollmentsTable::class,
            RecentEnrollmentsTable::class,
        ];
    }
}
