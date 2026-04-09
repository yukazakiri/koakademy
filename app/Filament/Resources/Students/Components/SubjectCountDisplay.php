<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Components;

use Filament\Schemas\Components\Component;

final class SubjectCountDisplay extends Component
{
    protected string $view = 'filament.resources.students.components.subject-count-display';

    public static function make(int $count): static
    {
        $instance = app(self::class);
        $instance->state(['count' => $count]);

        return $instance;
    }
}
