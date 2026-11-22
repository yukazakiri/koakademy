<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Handlers;

use App\Filament\Resources\Students\StudentResource;
use Illuminate\Http\Request;
use Override;
use Rupadana\ApiService\Http\Handlers;

final class DeleteHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/{id}';

    #[Override]
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

        // Students are never permitted to delete student records; enforce policy check
        $user = $request->user();
        if (! $user || $user->isStudentRole() || ! $user->can('delete', $model)) {
            return response()->json(['message' => 'You are not authorized to delete this student record.'], 403);
        }

        $model->delete();

        return self::sendSuccessResponse($model, 'Successfully Delete Resource');
    }
}
