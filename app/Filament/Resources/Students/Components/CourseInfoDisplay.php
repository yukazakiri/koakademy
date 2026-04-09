<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Components;

use App\Models\Course;
use Filament\Schemas\Components\Component;

final class CourseInfoDisplay extends Component
{
    protected string $view = 'filament.resources.students.components.course-info-display';

    public static function make(): static
    {
        return app(self::class);
    }

    public function course(?Course $course): static
    {
        $this->state(['course' => $course]);

        return $this;
    }
}
