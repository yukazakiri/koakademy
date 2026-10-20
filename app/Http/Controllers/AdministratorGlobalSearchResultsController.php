<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Full-page results for the administrator command palette.
 *
 * The palette only shows the first few rows per group; this page is where
 * "view all" lands so long result sets can be paginated properly.
 */
final class AdministratorGlobalSearchResultsController extends Controller
{
    public function __construct(private readonly GlobalSearchService $globalSearch) {}

    public function __invoke(Request $request): Response
    {
        $rawQuery = $request->input('q') ?? $request->input('query');
        $query = $this->globalSearch->normalizeQuery($rawQuery);
        $group = $this->globalSearch->resolveType($request->input('type'), GlobalSearchService::ADMINISTRATOR_GROUPS);

        // "all" cannot be paginated meaningfully; start on the first group.
        $group = $group === 'all' ? GlobalSearchService::ADMINISTRATOR_GROUPS[0] : $group;

        return Inertia::render('administrators/search/results', [
            'query' => $query,
            'rawQuery' => is_string($rawQuery) ? $rawQuery : '',
            'group' => $group,
            'groups' => GlobalSearchService::ADMINISTRATOR_GROUPS,
            'results' => $this->globalSearch->paginateAdministrator($query, $group),
        ]);
    }
}
