<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Actions;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;

final class ChangeCourseAction
{
    public static function make(): Action
    {
        return Action::make('changeCourse')
            ->label('Change Course')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('info')
            ->url(fn (Student $record): string => StudentResource::getUrl('change-course', ['record' => $record]));
    }
}
