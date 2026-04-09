<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingDismissalRequest;
use App\Models\OnboardingDismissal;
use Illuminate\Http\RedirectResponse;

final class OnboardingDismissalController extends Controller
{
    public function store(StoreOnboardingDismissalRequest $request): RedirectResponse
    {
        $user = $request->user();

        OnboardingDismissal::query()->updateOrCreate([
            'user_id' => $user->id,
            'feature_key' => $request->string('feature_key')->toString(),
        ], [
            'dismissed_at' => now(),
        ]);

        return back();
    }
}
