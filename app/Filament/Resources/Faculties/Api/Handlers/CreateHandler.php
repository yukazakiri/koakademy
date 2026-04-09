<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Handlers;

use App\Filament\Resources\Faculties\Api\Requests\CreateFacultyRequest;
use App\Filament\Resources\Faculties\FacultyResource;
use Rupadana\ApiService\Http\Handlers;

final class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = FacultyResource::class;

    protected static string $permission = 'Create:Faculty';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Create Faculty
     */
    public function handler(CreateFacultyRequest $request): \Illuminate\Http\JsonResponse
    {
        $model = new (self::getModel());

        $model->fill($request->all());

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
