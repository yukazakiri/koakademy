<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Handlers;

use App\Filament\Resources\Faculties\Api\Requests\UpdateFacultyRequest;
use App\Filament\Resources\Faculties\FacultyResource;
use Rupadana\ApiService\Http\Handlers;

final class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = FacultyResource::class;

    protected static string $permission = 'Update:Faculty';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Update Faculty
     */
    public function handler(UpdateFacultyRequest $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->route('id');

        $model = self::getModel()::find($id);

        if (! $model) {
            return self::sendNotFoundResponse();
        }

        // Check ownership: faculty can only update their own record
        $user = $request->user();
        if ($user && $user->role?->isFaculty() && $model->email !== $user->email) {
            return response()->json(['message' => 'You can only update your own faculty record.'], 403);
        }

        $model->fill($request->all());

        $model->save();

        return self::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}
