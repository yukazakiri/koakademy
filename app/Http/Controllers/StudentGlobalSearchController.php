<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentGlobalSearchController extends Controller
{
    public function __construct(private readonly GlobalSearchService $globalSearch) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json($this->globalSearch->emptyPayload(GlobalSearchService::STUDENT_GROUPS));
        }

        $type = $this->globalSearch->resolveType($request->input('type'), GlobalSearchService::STUDENT_GROUPS);

        return response()->json($this->globalSearch->searchStudent(
            $user,
            $this->globalSearch->normalizeQuery($request->input('q') ?? $request->input('query')),
            $type,
        ));
    }
}
