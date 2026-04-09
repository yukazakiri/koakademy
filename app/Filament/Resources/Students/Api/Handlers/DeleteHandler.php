<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Handlers;

use App\Filament\Resources\Students\StudentResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

final class DeleteHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StudentResource::class;

    protected static string $permission = 'Delete:Student';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Delete Student
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->route('id');

        $model = self::getModel()::find($id);

        if (! $model) {
            return self::sendNotFoundResponse();
        }

        // Check ownership: students can only delete their own record
        $user = $request->user();
        if ($user && $user->role?->isStudent() && $model->user_id !== $user->id) {
            return response()->json(['message' => 'You can only delete your own student record.'], 403);
        }

        $model->delete();

        return self::sendSuccessResponse($model, 'Successfully Delete Resource');
    }
}
