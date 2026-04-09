<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Handlers;

use App\Filament\Resources\StudentEnrollments\Api\Requests\UpdateStudentEnrollmentRequest;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use Rupadana\ApiService\Http\Handlers;

final class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StudentEnrollmentResource::class;

    protected static string $permission = 'Update:StudentEnrollment';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Update StudentEnrollment
     */
    public function handler(UpdateStudentEnrollmentRequest $request): \Illuminate\Http\JsonResponse
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
