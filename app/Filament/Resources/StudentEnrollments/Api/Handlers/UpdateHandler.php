<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Handlers;

use App\Filament\Resources\StudentEnrollments\Api\Requests\UpdateStudentEnrollmentRequest;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use Override;
use Rupadana\ApiService\Http\Handlers;

final class UpdateHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/{id}';

    #[Override]
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

        $allowed = [
            'course_id',
            'status',
            'semester',
            'academic_year',
            'school_year',
            'downpayment',
            'remarks',
        ];

        $model->fill($request->safe()->only($allowed));

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}
