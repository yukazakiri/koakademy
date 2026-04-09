<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Handlers;

use App\Filament\Resources\Classes\ClassesResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;

final class DeleteHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ClassesResource::class;

    protected static string $permission = 'Delete:Classes';

    public static function getMethod()
    {
        return Handlers::DELETE;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Delete Classes
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
