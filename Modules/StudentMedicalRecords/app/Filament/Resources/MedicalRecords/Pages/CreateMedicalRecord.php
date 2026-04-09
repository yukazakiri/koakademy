<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\MedicalRecordResource;

final class CreateMedicalRecord extends CreateRecord
{
    protected static string $resource = MedicalRecordResource::class;
}
