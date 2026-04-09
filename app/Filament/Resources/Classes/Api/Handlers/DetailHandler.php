<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Handlers;

use App\Filament\Resources\Classes\Api\Transformers\ClassesTransformer;
use App\Filament\Resources\Classes\ClassesResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = ClassesResource::class;

    protected static string $permission = 'View:Classes';

    /**
     * Show Classes
     *
     * @return ClassesTransformer
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|ClassesTransformer
    {
        $id = $request->route('id');

        $query = self::getEloquentQuery();

        $query = QueryBuilder::for(
            $query->where(self::getKeyName(), $id)
        )
            ->withCount('class_enrollments')
            ->with([
                'subject',
                'subjectByCode',
                'subjectByCodeFallback',
                'shsSubject',
                'faculty',
                'shsTrack',
                'shsStrand',
                'schedules.room',
                'class_enrollments.student',
            ])
            ->first();

        if (! $query) {
            return self::sendNotFoundResponse();
        }

        return new ClassesTransformer($query);
    }
}
