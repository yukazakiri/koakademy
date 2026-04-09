<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api;

use App\Filament\Resources\Classes\ClassesResource;
use Rupadana\ApiService\ApiService;

final class ClassesApiService extends ApiService
{
    protected static ?string $resource = ClassesResource::class;

    public static function handlers(): array
    {
        return [
            Handlers\CreateHandler::class,
            Handlers\UpdateHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class,
            Handlers\IndexHandler::class,
            Handlers\ScheduleDetailHandler::class,
            Handlers\FacultyScheduleHandler::class,
        ];

    }
}
