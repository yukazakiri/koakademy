<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Handlers;

use App\Filament\Resources\StudentEnrollments\Api\Transformers\StudentEnrollmentTransformer;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StudentEnrollmentResource::class;

    protected static string $permission = 'View:StudentEnrollment';

    /**
     * Show StudentEnrollment
     *
     * @return StudentEnrollmentTransformer
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|StudentEnrollmentTransformer
    {
        $id = $request->route('id');

        $query = self::getEloquentQuery();

        $query = QueryBuilder::for(
            $query->where(self::getKeyName(), $id)
        )
            ->first();

        if (! $query) {
            return self::sendNotFoundResponse();
        }

        return new StudentEnrollmentTransformer($query);
    }
}
