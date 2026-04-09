<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Pages;

use App\Filament\Resources\Faculties\FacultyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListFaculties extends ListRecords
{
    protected static string $resource = FacultyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
