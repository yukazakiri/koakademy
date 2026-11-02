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
        $model = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            app(\App\Services\IdentifierGenerator::class)->sequenceFor('student', lock: true);
            $configuration = app(\App\Services\StudentConfigurationService::class);
            $configuration->forget();
            $configuration->assertRequiredFieldsSupported(['email', 'phone', 'civil_status', 'nationality', 'religion']);
            $model = new (self::getModel());
            $allowed = array_diff($model->getFillable(), ['id', 'school_id', 'user_id', 'institution_id']);
            $model->fill($request->safe()->only($allowed));
            $type = \App\Enums\StudentType::from($request->input('student_type'));
            $model->student_id = app(\App\Services\StudentConfigurationService::class)->usesExternal($type->value)
                ? app(\App\Services\IdentifierGenerator::class)->claimExternalIdentifier($type, $request->validated('lrn'))
                : app(\App\Services\IdentifierGenerator::class)->assign($type, $request->validated('student_id'), $request->input('identifier_assignment', $request->filled('student_id') ? 'manual' : 'automatic'));
            $model->age = \Illuminate\Support\Carbon::parse($model->birth_date)->age;
            $model->save();

            return $model;
        });

        return self::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}
