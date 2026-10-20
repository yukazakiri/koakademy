<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdministratorGlobalSearchController extends Controller
{
    public function __construct(private readonly GlobalSearchService $globalSearch) {}

    public function __invoke(Request $request): JsonResponse
    {
        $type = $this->globalSearch->resolveType($request->input('type'), GlobalSearchService::ADMINISTRATOR_GROUPS);

        return response()->json($this->globalSearch->searchAdministrator(
            $this->globalSearch->normalizeQuery($request->input('q') ?? $request->input('query')),
            $type,
        ));
    }
}
