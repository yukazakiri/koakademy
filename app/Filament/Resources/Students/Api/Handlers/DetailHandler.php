<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Handlers;

use App\Filament\Resources\Students\Api\Transformers\StudentTransformer;
use App\Filament\Resources\Students\StudentResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = StudentResource::class;

    protected static string $permission = 'View:Student';

    /**
     * Show Student
     *
     * @return StudentTransformer
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|StudentTransformer
    {
        $identifier = $request->route('id');

        $query = self::getEloquentQuery();

        // Determine which field to search by
        $queryBuilder = QueryBuilder::for(
            $this->getQueryByIdentifier($query, $identifier)
        )
            ->with([
                'Course',
                'DocumentLocation',
                'personalInfo',
                'studentContactsInfo',
                'studentEducationInfo',
                'studentParentInfo',
                'clearances',
                'subjectEnrolled.subject',
                'subjectEnrolled.class',
                'getCurrentClearanceRecord',
                'getCurrentTuitionRecord',
                'classEnrollments.class.subject',
            ])
            ->first();

        if (! $queryBuilder) {
            return self::sendNotFoundResponse();
        }

        return new StudentTransformer($queryBuilder);
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

        // Check if it matches student_id (use clone to avoid modifying original query)
        $studentById = clone $query;
        $studentById->where('student_id', $identifier);

        if ($studentById->exists()) {
            return $studentById;
        }

        // Otherwise, use the primary key
        return $query->where(self::getKeyName(), $identifier);
    }
}
