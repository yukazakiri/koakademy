<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api;

use App\Filament\Resources\Students\StudentResource;
use Rupadana\ApiService\ApiService;

final class StudentApiService extends ApiService
{
    protected static ?string $resource = StudentResource::class;

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
