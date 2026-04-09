<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Handlers;

use App\Filament\Resources\Faculties\Api\Transformers\FacultyTransformer;
use App\Filament\Resources\Faculties\FacultyResource;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class PaginationHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = FacultyResource::class;

    protected static string $permission = 'ViewAny:Faculty';

    /**
     * List of Faculty
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function handler()
    {
        $query = self::getEloquentQuery();

        $query = QueryBuilder::for($query)
            ->allowedFields($this->getAllowedFields() ?? [])
            ->allowedSorts($this->getAllowedSorts() ?? [])
            ->allowedFilters($this->getAllowedFilters() ?? [])
            ->allowedIncludes($this->getAllowedIncludes() ?? [])
            ->paginate(request()->query('per_page'))
            ->appends(request()->query());

        return FacultyTransformer::collection($query);
    }
}
