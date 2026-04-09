<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Pages;

use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\ShsStudentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewShsStudent extends ViewRecord
{
    protected static string $resource = ShsStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
