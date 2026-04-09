<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Pages;

use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\ShsStrandResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditShsStrand extends EditRecord
{
    protected static string $resource = ShsStrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
