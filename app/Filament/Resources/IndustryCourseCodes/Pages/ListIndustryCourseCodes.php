<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndustryCourseCodes\Pages;

use App\Filament\Resources\IndustryCourseCodes\IndustryCourseCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListIndustryCourseCodes extends ListRecords
{
    #[Override]
    protected static string $resource = IndustryCourseCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
