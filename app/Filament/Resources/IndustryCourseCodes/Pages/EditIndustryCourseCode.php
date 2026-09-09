<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndustryCourseCodes\Pages;

use App\Filament\Resources\IndustryCourseCodes\IndustryCourseCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditIndustryCourseCode extends EditRecord
{
    #[Override]
    protected static string $resource = IndustryCourseCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
