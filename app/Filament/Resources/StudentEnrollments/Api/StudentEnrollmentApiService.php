<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api;

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use Rupadana\ApiService\ApiService;

final class StudentEnrollmentApiService extends ApiService
{
    protected static ?string $resource = StudentEnrollmentResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\CreateHandler::class,
            Handlers\UpdateHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
        ];

    }
}
