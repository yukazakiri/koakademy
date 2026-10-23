<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api\Handlers;

use App\Filament\Resources\Courses\Api\Transformers\CourseTransformer;
use App\Filament\Resources\Courses\CourseResource;
use App\Http\Controllers\Api\V1\ApiPagination;
use Illuminate\Http\Request;
use Override;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

final class PaginationHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/';

    #[Override]
    public static ?string $resource = CourseResource::class;

    protected static string $permission = 'ViewAny:Course';

    /**
     * List of Course
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
            ->paginate(ApiPagination::perPage(request()->query('per_page')))
            ->appends(request()->query());

        return CourseTransformer::collection($query);
    }
}
