<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OnboardingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OnboardingProgressController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $progress = OnboardingProgress::query()
            ->where('user_id', $user->id)
            ->where('variant', $request->string('variant', 'faculty')->toString())
            ->first();

        return response()->json([
            'progress' => $progress?->only([
                'completed_steps',
                'checklist_state',
                'started_at',
                'completed_at',
                'last_seen_at',
                'current_step_index',
                'is_dismissed',
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $variant = $request->string('variant', 'faculty')->toString();

        $validated = $request->validate([
            'completed_steps' => ['nullable', 'array'],
            'checklist_state' => ['nullable', 'array'],
            'current_step_index' => ['nullable', 'integer', 'min:0'],
            'is_dismissed' => ['nullable', 'boolean'],
        ]);

        $progress = OnboardingProgress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'variant' => $variant,
            ],
            [
                'completed_steps' => $validated['completed_steps'] ?? [],
                'checklist_state' => $validated['checklist_state'] ?? [],
                'current_step_index' => $validated['current_step_index'] ?? 0,
                'is_dismissed' => $validated['is_dismissed'] ?? false,
                'started_at' => now(),
                'last_seen_at' => now(),
                'completed_at' => $this->isCompleted($validated['checklist_state'] ?? null)
                    ? now()
                    : null,
            ]
        );

        return response()->json([
            'progress' => $progress->only([
                'completed_steps',
                'checklist_state',
                'started_at',
                'completed_at',
                'last_seen_at',
                'current_step_index',
                'is_dismissed',
            ]),
        ]);
    }

    private function isCompleted(?array $checklistState): bool
    {
        if (! is_array($checklistState)) {
            return false;
        }

        return collect($checklistState)->every(fn (bool $completed): bool => $completed);
    }
}
