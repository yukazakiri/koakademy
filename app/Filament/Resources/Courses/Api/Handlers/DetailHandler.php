<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api\Handlers;

use App\Filament\Resources\Courses\Api\Transformers\CourseTransformer;
use App\Filament\Resources\Courses\CourseResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = CourseResource::class;

    protected static string $permission = 'View:Course';

    /**
     * Show Course
     *
     * @return CourseTransformer
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|CourseTransformer
    {
        $id = $request->route('id');

        $query = self::getEloquentQuery();

        $query = QueryBuilder::for(
            $query->where(self::getKeyName(), $id)
        )
            ->withCount(['subjects', 'students'])
            ->with([
                'department',
                'subjects',
                'students',
            ])
            ->first();

        if (! $query) {
            return self::sendNotFoundResponse();
        }

        return new CourseTransformer($query);
    }
}
