<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Full-page results for the faculty command palette.
 *
 * The palette only shows the first few rows per group; this page is where
 * "view all" lands so long result sets can be paginated properly.
 */
final class FacultyGlobalSearchResultsController extends Controller
{
    public function __construct(private readonly GlobalSearchService $globalSearch) {}

    public function __invoke(Request $request): Response
    {
        $rawQuery = $request->input('q') ?? $request->input('query');
        $query = $this->globalSearch->normalizeQuery($rawQuery);
        $group = $this->globalSearch->resolveType($request->input('type'), GlobalSearchService::FACULTY_GROUPS);

        // "all" cannot be paginated meaningfully; start on the first group.
        $group = $group === 'all' ? GlobalSearchService::FACULTY_GROUPS[0] : $group;

        return Inertia::render('faculty/search/results', [
            'query' => $query,
            'rawQuery' => is_string($rawQuery) ? $rawQuery : '',
            'group' => $group,
            'groups' => GlobalSearchService::FACULTY_GROUPS,
            'results' => $this->globalSearch->paginateFaculty($this->facultyOrAbort(), $query, $group),
        ]);
    }

    private function facultyOrAbort(): Faculty
    {
        $user = Auth::user();

        if (! $user instanceof User || ! method_exists($user, 'isFaculty') || ! $user->isFaculty()) {
            abort(403);
        }

        $faculty = $this->globalSearch->facultyFor($user);

        if (! $faculty) {
            abort(403);
        }

        return $faculty;
    }
}
