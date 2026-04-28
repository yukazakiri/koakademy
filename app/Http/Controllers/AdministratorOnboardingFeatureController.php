<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Features\Onboarding\FeatureClassRegistry;
use App\Models\OnboardingFeature;
use App\Models\User;
use DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

final class AdministratorOnboardingFeatureController extends Controller
{
    /**
     * Display listing of onboarding features.
     */
    public function index(Request $request): Response
    {
        $features = OnboardingFeature::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->input('search');
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('feature_key', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('audience'), function ($query) use ($request): void {
                $query->where('audience', $request->input('audience'));
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $isActive = $request->input('status') === 'active';
                $query->where('is_active', $isActive);
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (OnboardingFeature $feature): array {
                $steps = is_array($feature->steps) ? $feature->steps : [];
                $featureClass = FeatureClassRegistry::classForKey($feature->feature_key);

                return [
                    'id' => $feature->id,
                    'feature_key' => $feature->feature_key,
                    'name' => $feature->name,
                    'audience' => $feature->audience,
                    'summary' => $feature->summary,
                    'badge' => $feature->badge,
                    'accent' => $feature->accent,
                    'cta_label' => $feature->cta_label,
                    'cta_url' => $feature->cta_url,
                    'steps' => $steps,
                    'steps_count' => count($steps),
                    'is_active' => $feature->is_active,
                    'created_at' => format_timestamp($feature->created_at),
                    'updated_at' => format_timestamp($feature->updated_at),
                    // Pennant metadata
                    'pennant_class' => $featureClass,
                    'pennant_type' => $featureClass ? 'class' : 'string',
                    'pennant_global_state' => $featureClass && $this->isGloballyActivated($featureClass),
                    'pennant_user_overrides_count' => $featureClass
                        ? $this->getUserOverrideCount($featureClass)
                        : 0,
                ];
            });

        $experimentalKeys = config('onboarding.experimental_feature_keys', []);

        return Inertia::render('administrators/onboarding-features/index', [
            'features' => $features,
            'experimental_keys' => $experimentalKeys,
            'filters' => [
                'search' => $request->input('search'),
                'audience' => $request->input('audience'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Display the form for creating a new onboarding feature.
     */
    public function create(): Response
    {
        return Inertia::render('administrators/onboarding-features/edit', [
            'feature' => null,
            'audiences' => $this->getAudienceOptions(),
        ]);
    }

    /**
     * Store a newly created onboarding feature.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->validationRules());

        $feature = OnboardingFeature::create($validated);

        if ($feature->is_active) {
            $this->activateFeature($feature->feature_key);
        }

        return redirect()
            ->route('administrators.onboarding-features.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Onboarding feature \"{$feature->name}\" created successfully.",
            ]);
    }

    /**
     * Display the edit form for an onboarding feature.
     */
    public function edit(OnboardingFeature $onboardingFeature): Response
    {
        return Inertia::render('administrators/onboarding-features/edit', [
            'feature' => [
                'id' => $onboardingFeature->id,
                'feature_key' => $onboardingFeature->feature_key,
                'name' => $onboardingFeature->name,
                'audience' => $onboardingFeature->audience,
                'summary' => $onboardingFeature->summary,
                'badge' => $onboardingFeature->badge,
                'accent' => $onboardingFeature->accent,
                'cta_label' => $onboardingFeature->cta_label,
                'cta_url' => $onboardingFeature->cta_url,
                'steps' => is_array($onboardingFeature->steps) ? $onboardingFeature->steps : [],
                'is_active' => $onboardingFeature->is_active,
            ],
            'audiences' => $this->getAudienceOptions(),
        ]);
    }

    /**
     * Update an existing onboarding feature.
     */
    public function update(Request $request, OnboardingFeature $onboardingFeature): RedirectResponse
    {
        $validated = $request->validate($this->validationRules($onboardingFeature->id));

        $onboardingFeature->update($validated);

        // Sync Pennant feature flag
        if ($onboardingFeature->is_active) {
            $this->activateFeature($onboardingFeature->feature_key);
        } else {
            $this->deactivateFeature($onboardingFeature->feature_key);
        }

        return redirect()
            ->route('administrators.onboarding-features.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Onboarding feature \"{$onboardingFeature->name}\" updated successfully.",
            ]);
    }

    /**
     * Toggle the active status of an onboarding feature.
     */
    public function toggle(OnboardingFeature $onboardingFeature): RedirectResponse
    {
        $onboardingFeature->is_active = ! $onboardingFeature->is_active;
        $onboardingFeature->save();

        if ($onboardingFeature->is_active) {
            $this->activateFeature($onboardingFeature->feature_key);
        } else {
            $this->deactivateFeature($onboardingFeature->feature_key);
        }

        $status = $onboardingFeature->is_active ? 'activated' : 'deactivated';

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Feature \"{$onboardingFeature->name}\" has been {$status}.",
        ]);
    }

    /**
     * Delete an onboarding feature.
     */
    public function destroy(OnboardingFeature $onboardingFeature): RedirectResponse
    {
        $name = $onboardingFeature->name;
        $featureKey = $onboardingFeature->feature_key;

        // Deactivate the feature flag before deletion
        $this->deactivateFeature($featureKey);

        $onboardingFeature->delete();

        return redirect()
            ->route('administrators.onboarding-features.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Onboarding feature \"{$name}\" deleted successfully.",
            ]);
    }

    /**
     * Upload an image for onboarding step.
     */
    public function uploadImage(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('image')->store('onboarding', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    /**
     * Activate a feature for a specific user (Pennant per-user override).
     */
    public function activateForUser(Request $request, OnboardingFeature $onboardingFeature): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $featureClass = FeatureClassRegistry::classForKey($onboardingFeature->feature_key);
        $featureRef = $featureClass ?? $onboardingFeature->feature_key;

        $user = User::findOrFail($validated['user_id']);
        Feature::for($user)->activate($featureRef);

        return response()->json([
            'message' => "Feature activated for user {$user->name}.",
        ]);
    }

    /**
     * Deactivate a feature for a specific user (Pennant per-user override).
     */
    public function deactivateForUser(Request $request, OnboardingFeature $onboardingFeature): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $featureClass = FeatureClassRegistry::classForKey($onboardingFeature->feature_key);
        $featureRef = $featureClass ?? $onboardingFeature->feature_key;

        $user = User::findOrFail($validated['user_id']);
        Feature::for($user)->deactivate($featureRef);

        return response()->json([
            'message' => "Feature deactivated for user {$user->name}.",
        ]);
    }

    /**
     * Purge all per-user overrides for a feature (reset to default resolution).
     */
    public function purgeOverrides(OnboardingFeature $onboardingFeature): \Illuminate\Http\JsonResponse
    {
        $featureClass = FeatureClassRegistry::classForKey($onboardingFeature->feature_key);
        $featureRef = $featureClass ?? $onboardingFeature->feature_key;

        Feature::forget($featureRef);

        return response()->json([
            'message' => 'All per-user overrides have been purged.',
        ]);
    }

    /**
     * Get users with overrides for a feature.
     */
    public function overriddenUsers(OnboardingFeature $onboardingFeature): \Illuminate\Http\JsonResponse
    {
        $featureClass = FeatureClassRegistry::classForKey($onboardingFeature->feature_key);

        if (! $featureClass) {
            return response()->json(['users' => []]);
        }

        // Find user IDs with explicit overrides in the features table
        $userScopes = DB::table('features')
            ->where('name', $featureClass)
            ->where('scope', 'not like', '%__laravel_null%')
            ->pluck('scope')
            ->all();

        // Parse scope format: "App\Models\User|{id}"
        $userIds = collect($userScopes)
            ->map(fn (string $scope) => Str::afterLast($scope, '|'))
            ->filter()
            ->map(fn (string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'is_active' => Feature::for($user)->active($featureClass),
            ]);

        return response()->json(['users' => $users]);
    }

    /**
     * Get validation rules for the feature.
     *
     * @return array<string, mixed>
     */
    private function validationRules(?int $ignoreId = null): array
    {
        return [
            'feature_key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('onboarding_features', 'feature_key')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'audience' => ['required', 'in:student,faculty,all'],
            'summary' => ['nullable', 'string'],
            'badge' => ['nullable', 'string', 'max:255'],
            'accent' => ['nullable', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:255', 'regex:/^(\/[\w\-\/]*|https?:\/\/.+)$/'],
            'steps' => ['nullable', 'array'],
            'steps.*.type' => ['required', 'string'],
            'steps.*.data' => ['required', 'array'],
            'steps.*.data.title' => ['required', 'string', 'max:255'],
            'steps.*.data.summary' => ['required', 'string'],
            'steps.*.data.badge' => ['nullable', 'string', 'max:255'],
            'steps.*.data.accent' => ['nullable', 'string', 'max:255'],
            'steps.*.data.icon' => ['nullable', 'string'],
            'steps.*.data.image' => ['nullable', 'string'],
            'steps.*.data.highlights' => ['nullable', 'array'],
            'steps.*.data.stats' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get audience options for the form.
     *
     * @return array<string, string>
     */
    private function getAudienceOptions(): array
    {
        return [
            'student' => 'Student',
            'faculty' => 'Faculty',
            'all' => 'All Users',
        ];
    }

    /**
     * Activate a feature via its Pennant class.
     */
    private function activateFeature(string $featureKey): void
    {
        $featureClass = FeatureClassRegistry::classForKey($featureKey);

        if ($featureClass) {
            Feature::activateForEveryone($featureClass);
        } else {
            Feature::activateForEveryone($featureKey);
        }
    }

    /**
     * Deactivate a feature via its Pennant class.
     */
    private function deactivateFeature(string $featureKey): void
    {
        $featureClass = FeatureClassRegistry::classForKey($featureKey);

        if ($featureClass) {
            Feature::deactivateForEveryone($featureClass);
        } else {
            Feature::deactivateForEveryone($featureKey);
        }
    }

    /**
     * Count users with explicit overrides for a feature.
     */
    private function getUserOverrideCount(string $featureClass): int
    {
        return DB::table('features')
            ->where('name', $featureClass)
            ->where('scope', 'not like', '%__laravel_null%')
            ->count();
    }

    /**
     * Check if a feature is force-activated for everyone (global override).
     */
    private function isGloballyActivated(string $featureClass): bool
    {
        return DB::table('features')
            ->where('name', $featureClass)
            ->where('scope', '__laravel_null')
            ->where('value', 'true')
            ->exists();
    }
}
