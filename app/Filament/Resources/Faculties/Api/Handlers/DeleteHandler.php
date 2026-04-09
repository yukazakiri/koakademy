<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Handlers;

use App\Filament\Resources\Faculties\FacultyResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

final class DeleteHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = FacultyResource::class;

    protected static string $permission = 'Delete:Faculty';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Delete Faculty
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->route('id');

        $model = self::getModel()::find($id);

        if (! $model) {
            return self::sendNotFoundResponse();
        }

        // Check ownership: faculty can only delete their own record
        $user = $request->user();
        if ($user && $user->role?->isFaculty() && $model->email !== $user->email) {
            return response()->json(['message' => 'You can only delete your own faculty record.'], 403);
        }

        $model->delete();

        return self::sendSuccessResponse($model, 'Successfully Delete Resource');
    }
}
