<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FeatureToggleRegistry;
use App\Services\FeatureToggleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorFeatureToggleController extends Controller
{
    public function __construct(
        private readonly FeatureToggleService $featureToggleService,
    ) {}

    public function index(Request $request): Response
    {
        $toggles = FeatureToggleRegistry::all();

        $features = collect($toggles)
            ->map(function ($toggle): array {
                $state = $this->featureToggleService->getFeatureState($toggle->key());

                return [
                    'key' => $toggle->key(),
                    'name' => $toggle->name(),
                    'audience' => $toggle->audience(),
                    'summary' => $toggle->summary(),
                    'badge' => $toggle->badge(),
                    'accent' => $toggle->accent(),
                    'cta_label' => $toggle->ctaLabel(),
                    'cta_url' => $toggle->ctaUrl(),
                    'steps' => $toggle->steps(),
                    'steps_count' => count($toggle->steps()),
                    'category' => $toggle->category(),
                    'is_active' => $state['is_globally_activated'],
                    'pennant_class' => get_class($toggle),
                    'pennant_type' => 'class',
                    'pennant_global_state' => $state['is_globally_activated'],
                    'pennant_user_overrides_count' => $state['override_count'],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('administrators/feature-toggles/index', [
            'features' => $features,
            'filters' => [
                'search' => $request->input('search'),
                'audience' => $request->input('audience'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    public function toggle(string $featureKey): \Illuminate\Http\RedirectResponse
    {
        $state = $this->featureToggleService->getFeatureState($featureKey);

        if ($state['is_globally_activated']) {
            $this->featureToggleService->deactivateGlobally($featureKey);
            $status = 'deactivated';
        } else {
            $this->featureToggleService->activateGlobally($featureKey);
            $status = 'activated';
        }

        $toggle = FeatureToggleRegistry::make($featureKey);
        $name = $toggle?->name() ?? $featureKey;

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Feature \"{$name}\" has been {$status}.",
        ]);
    }

    public function activateForUser(Request $request, string $featureKey): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $this->featureToggleService->activateForUser($featureKey, $user);

        return response()->json([
            'message' => "Feature activated for user {$user->name}.",
        ]);
    }

    public function deactivateForUser(Request $request, string $featureKey): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $this->featureToggleService->deactivateForUser($featureKey, $user);

        return response()->json([
            'message' => "Feature deactivated for user {$user->name}.",
        ]);
    }

    public function purgeOverrides(string $featureKey): JsonResponse
    {
        $this->featureToggleService->purgeOverrides($featureKey);

        return response()->json([
            'message' => 'All per-user overrides have been purged.',
        ]);
    }

    public function overriddenUsers(string $featureKey): JsonResponse
    {
        $users = $this->featureToggleService->getOverriddenUsers($featureKey);

        return response()->json(['users' => $users]);
    }
}
