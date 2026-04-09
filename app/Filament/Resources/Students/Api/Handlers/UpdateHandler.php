<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Handlers;

use App\Filament\Resources\Students\Api\Requests\UpdateStudentRequest;
use App\Filament\Resources\Students\StudentResource;
use Rupadana\ApiService\Http\Handlers;

final class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StudentResource::class;

    protected static string $permission = 'Update:Student';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Update Student
     */
    public function handler(UpdateStudentRequest $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->route('id');

        $model = self::getModel()::find($id);

        if (! $model) {
            return self::sendNotFoundResponse();
        }

        // Check ownership: students can only update their own record
        $user = $request->user();
        if ($user && $user->role?->isStudent() && $model->user_id !== $user->id) {
            return response()->json(['message' => 'You can only update your own student record.'], 403);
        }

        $model->fill($request->all());

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}
