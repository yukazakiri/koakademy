<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Pages;

use App\Filament\Resources\Faculties\FacultyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditFaculty extends EditRecord
{
    protected static string $resource = FacultyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
