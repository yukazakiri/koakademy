<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Handlers;

use App\Filament\Resources\Faculties\Api\Requests\CreateFacultyRequest;
use App\Filament\Resources\Faculties\FacultyResource;
use Override;
use Rupadana\ApiService\Http\Handlers;

final class CreateHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/';

    #[Override]
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

        $allowed = array_diff($model->getFillable(), ['id', 'school_id']);

        $model->fill($request->safe()->only($allowed));

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
