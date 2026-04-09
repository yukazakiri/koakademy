<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Components;

use Filament\Schemas\Components\Component;

final class SubjectDetailDisplay extends Component
{
    protected string $view = 'filament.resources.students.components.subject-detail-display';

    public static function make(string $title, string $code, int $units): static
    {
        $instance = app(self::class);
        $instance->state([
            'title' => $title,
            'code' => $code,
            'units' => $units,
        ]);

        return $instance;
    }
}
