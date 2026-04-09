<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Pages;

use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\ShsStrandResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewShsStrand extends ViewRecord
{
    protected static string $resource = ShsStrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
