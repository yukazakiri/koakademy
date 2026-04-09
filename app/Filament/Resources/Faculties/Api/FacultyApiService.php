<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api;

use App\Filament\Resources\Faculties\FacultyResource;
use Rupadana\ApiService\ApiService;

final class FacultyApiService extends ApiService
{
    protected static ?string $resource = FacultyResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\CreateHandler::class,
            Handlers\UpdateHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
            Handlers\FacultyStudentsHandler::class,
        ];
    }
}
