<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api\Handlers;

use App\Filament\Resources\Courses\Api\Requests\CreateCourseRequest;
use App\Filament\Resources\Courses\CourseResource;
use Override;
use Rupadana\ApiService\Http\Handlers;

final class CreateHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/';

    #[Override]
    public static ?string $resource = CourseResource::class;

    protected static string $permission = 'Create:Course';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Create Course
     */
    public function handler(CreateCourseRequest $request): \Illuminate\Http\JsonResponse
    {
        $model = new (self::getModel());

        $allowed = array_diff($model->getFillable(), ['id', 'school_id']);

        $model->fill($request->safe()->only($allowed));

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
