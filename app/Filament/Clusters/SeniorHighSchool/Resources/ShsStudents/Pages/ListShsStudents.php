<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Pages;

use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\ShsStudentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListShsStudents extends ListRecords
{
    protected static string $resource = ShsStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
