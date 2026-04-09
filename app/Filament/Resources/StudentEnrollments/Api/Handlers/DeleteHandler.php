<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Handlers;

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

final class DeleteHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StudentEnrollmentResource::class;

    protected static string $permission = 'Delete:StudentEnrollment';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Delete StudentEnrollment
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
