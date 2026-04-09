<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
