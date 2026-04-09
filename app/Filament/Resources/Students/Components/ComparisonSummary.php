<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Components;

use Filament\Schemas\Components\Component;

final class ComparisonSummary extends Component
{
    protected string $view = 'filament.resources.students.components.comparison-summary';

    public static function make(int $autoMatched, int $requiresReview, int $noCreditTransfer): static
    {
        $instance = app(self::class);
        $instance->state([
            'autoMatched' => $autoMatched,
            'requiresReview' => $requiresReview,
            'noCreditTransfer' => $noCreditTransfer,
        ]);

        return $instance;
    }
}
