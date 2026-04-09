<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\ChartWidget;

final class DropoutByYearLevelChart extends ChartWidget
{
    protected static ?int $sort = 31;

    protected ?string $heading = 'Dropout Rates by Year Level';

    protected ?string $description = 'Distribution of dropouts across academic year levels';

    protected string $color = 'warning';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $yearLevels = [1, 2, 3, 4, 5];
        $dropoutData = [];
        $withdrawnData = [];
        $labels = [];

        foreach ($yearLevels as $year) {
            $dropouts = Student::where('status', StudentStatus::Dropped->value)
                ->where('academic_year', $year)
                ->count();

            $withdrawn = Student::where('status', StudentStatus::Withdrawn->value)
                ->where('academic_year', $year)
                ->count();

            $dropoutData[] = $dropouts;
            $withdrawnData[] = $withdrawn;
            $labels[] = $this->getYearLabel($year);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Dropped',
                    'data' => $dropoutData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Withdrawn',
                    'data' => $withdrawnData,
                    'backgroundColor' => 'rgba(251, 191, 36, 0.8)',
                    'borderColor' => 'rgba(251, 191, 36, 1)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => true,
        ];
    }

    private function getYearLabel(int $year): string
    {
        return match ($year) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            5 => 'Graduate',
            default => "Year {$year}",
        };
    }
}
