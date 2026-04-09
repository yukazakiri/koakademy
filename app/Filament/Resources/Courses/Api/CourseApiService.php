<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api;

use App\Filament\Resources\Courses\CourseResource;
use Rupadana\ApiService\ApiService;

final class CourseApiService extends ApiService
{
    protected static ?string $resource = CourseResource::class;

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
