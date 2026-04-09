<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ChangelogService;
use App\Services\VersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class ChangelogController extends Controller
{
    /**
     * Display the changelog page.
     *
     * This page is publicly accessible - users do not need to be authenticated.
     */
    public function __invoke(Request $request, ChangelogService $changelogService, VersionService $versionService): Response
    {
        $user = Auth::user();

        // Get changelog entries (excluding pre-releases for public view)
        $changelog = $changelogService->getChangelog(20, includePrereleases: false);

        // Get version info for the current release
        $versionInfo = $versionService->getVersionInfo();

        return Inertia::render('changelog', [
            'user' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'User',
            ] : [
                'name' => 'Guest',
                'email' => '',
                'avatar' => null,
                'role' => 'guest',
            ],
            'version' => config('app.version', '1.0.0'),
            'versionInfo' => $versionInfo,
            'changelog' => $changelog->toArray(),
            'github_repo' => config('services.github.repo', 'dccp-developers/DccpAdminV3'),
        ]);
    }
}
