<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class FacultyGlobalSearchController extends Controller
{
    public function __construct(private readonly GlobalSearchService $globalSearch) {}

    public function classes(Request $request): JsonResponse
    {
        return $this->respond($request, 'classes');
    }

    public function students(Request $request): JsonResponse
    {
        return $this->respond($request, 'students');
    }

    private function respond(Request $request, string $type): JsonResponse
    {
        $faculty = $this->resolveFacultyOrAbort();

        return response()->json($this->globalSearch->searchFaculty(
            $faculty,
            $this->globalSearch->normalizeQuery($request->input('q') ?? $request->input('query')),
            $type,
        ));
    }

    private function resolveFacultyOrAbort(): Faculty
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'isFaculty') || ! $user->isFaculty()) {
            abort(403);
        }

        $faculty = $this->globalSearch->facultyFor($user);

        if (! $faculty) {
            abort(403);
        }

        return $faculty;
    }
}
