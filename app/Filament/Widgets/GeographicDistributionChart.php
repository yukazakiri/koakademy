<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Student;
use Filament\Widgets\ChartWidget;

final class GeographicDistributionChart extends ChartWidget
{
    protected static ?int $sort = 32;

    protected ?string $heading = 'Geographic Distribution by Region';

    protected ?string $description = 'Student distribution across Philippine regions';

    protected string $color = 'info';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        // Get top 10 regions by student count
        $regionData = Student::whereNotNull('region_of_origin')
            ->selectRaw('region_of_origin, COUNT(*) as count')
            ->groupBy('region_of_origin')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($regionData as $region) {
            $data[] = $region->count;
            $labels[] = $region->region_of_origin;
            $colors[] = $this->getRegionColor($region->region_of_origin);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Students',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => array_map(fn (string $color): string => str_replace('0.7', '1', $color), $colors),
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
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
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

    private function getRegionColor(string $region): string
    {
        // Generate consistent colors based on region name
        $hash = crc32($region);
        $hue = $hash % 360;

        return "hsla({$hue}, 70%, 60%, 0.7)";
    }
}
