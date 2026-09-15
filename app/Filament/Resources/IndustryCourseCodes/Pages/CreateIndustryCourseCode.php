<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndustryCourseCodes\Pages;

use App\Filament\Resources\IndustryCourseCodes\IndustryCourseCodeResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateIndustryCourseCode extends CreateRecord
{
    #[Override]
    protected static string $resource = IndustryCourseCodeResource::class;
}
