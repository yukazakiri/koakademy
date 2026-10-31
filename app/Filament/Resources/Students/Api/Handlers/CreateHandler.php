<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Handlers;

use App\Filament\Resources\Students\Api\Requests\CreateStudentRequest;
use App\Filament\Resources\Students\StudentResource;
use Override;
use Rupadana\ApiService\Http\Handlers;

final class CreateHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/';

    #[Override]
    public static ?string $resource = StudentResource::class;

    protected static string $permission = 'Create:Student';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Create Student
     */
    public function handler(CreateStudentRequest $request): \Illuminate\Http\JsonResponse
    {
        $model = new (self::getModel());

        $allowed = array_diff($model->getFillable(), ['id', 'school_id']);

        $model->fill($request->safe()->only($allowed));

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
