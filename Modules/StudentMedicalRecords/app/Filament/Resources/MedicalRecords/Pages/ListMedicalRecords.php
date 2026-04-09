<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\MedicalRecordResource;

final class ListMedicalRecords extends ListRecords
{
    protected static string $resource = MedicalRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
