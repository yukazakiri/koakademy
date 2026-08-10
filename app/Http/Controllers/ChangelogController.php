<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ChangelogService;
use App\Services\VersionService;
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
    public function __invoke(ChangelogService $changelogService, VersionService $versionService): Response
    {
        $user = Auth::user();
        $canAccessAdminPortal = $user?->canAccessAdminPortal() ?? false;

        // Resolve the configured GitHub source for the release history. This
        // application ships from yukazakiri/koakademy; the value is overridable
        // through the `services.github.repo` configuration key.
        $githubRepo = config('services.github.repo', 'yukazakiri/koakademy');

        // The deployment workflow publishes every application update as a
        // pre-release, so these entries are part of the public product history.
        $changelogResult = $changelogService->getChangelogResult(30, includePrereleases: true);

        // Get version info for the current release
        $versionInfo = $versionService->getVersionInfo();
        $version = config('app.version', '1.0.0');

        if (! $canAccessAdminPortal) {
            $versionInfo['commit'] = null;
            $versionInfo['build_url'] = null;
        }

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
            'layout' => $canAccessAdminPortal ? 'admin' : 'portal',
            'version' => $version,
            'versionInfo' => $versionInfo,
            'changelog' => $changelogResult->entries->toArray(),
            'changelog_status' => $changelogResult->status->value,
            'changelog_last_synced_at' => $changelogResult->lastSyncedAt,
            'github_repo' => $githubRepo,
        ]);
    }
}
