<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api\Handlers;

use App\Filament\Resources\Courses\Api\Requests\UpdateCourseRequest;
use App\Filament\Resources\Courses\CourseResource;
use Rupadana\ApiService\Http\Handlers;

final class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CourseResource::class;

    protected static string $permission = 'Update:Course';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Update Course
     */
    public function handler(UpdateCourseRequest $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->route('id');

        $model = self::getModel()::find($id);

        if (! $model) {
            return self::sendNotFoundResponse();
        }

        $model->fill($request->all());

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}
