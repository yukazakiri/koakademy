<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api\Handlers;

use App\Filament\Resources\Courses\CourseResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

final class DeleteHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CourseResource::class;

    protected static string $permission = 'Delete:Course';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Delete Course
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->route('id');

        $model = self::getModel()::find($id);

        if (! $model) {
            return self::sendNotFoundResponse();
        }

        $model->delete();

        return self::sendSuccessResponse($model, 'Successfully Delete Resource');
    }
}
