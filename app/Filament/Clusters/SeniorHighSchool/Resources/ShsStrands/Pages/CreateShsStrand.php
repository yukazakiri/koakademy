<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Pages;

use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\ShsStrandResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShsStrand extends CreateRecord
{
    protected static string $resource = ShsStrandResource::class;
}
