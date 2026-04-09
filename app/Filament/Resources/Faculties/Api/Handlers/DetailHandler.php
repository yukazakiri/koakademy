<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Handlers;

use App\Filament\Resources\Faculties\Api\Transformers\FacultyTransformer;
use App\Filament\Resources\Faculties\FacultyResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = FacultyResource::class;

    protected static string $permission = 'View:Faculty';

    /**
     * Show Faculty
     *
     * @return FacultyTransformer
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|FacultyTransformer
    {
        $identifier = $request->route('id');

        $query = self::getEloquentQuery();

        // Determine which field to search by
        $queryBuilder = QueryBuilder::for(
            $this->getQueryByIdentifier($query, $identifier)
        )
            ->with([
                'classes',
                'classEnrollments',
                'account',
                'departmentBelongsTo',
            ])
            ->withCount([
                'classes',
                'classEnrollments',
            ])
            ->allowedFields($this->getAllowedFields() ?? [])
            ->allowedIncludes([
                'classes',
                'classEnrollments',
                'account',
                'departmentBelongsTo',
            ])
            ->first();

        if (! $queryBuilder) {
            return self::sendNotFoundResponse();
        }

        return new FacultyTransformer($queryBuilder);
    }

    /**
     * Get query based on identifier type
     */
    private function getQueryByIdentifier($query, string $identifier)
    {
        // If it contains '@', treat it as an email
        if (str_contains($identifier, '@')) {
            return $query->where('email', $identifier);
        }

        // Check if it matches faculty_id_number (use clone to avoid modifying original query)
        $facultyByIdNumber = clone $query;
        $facultyByIdNumber->where('faculty_id_number', $identifier);

        if ($facultyByIdNumber->exists()) {
            return $facultyByIdNumber;
        }

        // Otherwise, use the primary key
        return $query->where(self::getKeyName(), $identifier);
    }
}
