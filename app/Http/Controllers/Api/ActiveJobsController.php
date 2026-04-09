<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JobTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ActiveJobsController extends Controller
{
    public function __construct(
        private readonly JobTrackerService $jobTracker
    ) {}

    /**
     * Get all active jobs for the authenticated user
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['jobs' => []], 200);
        }

        $jobs = $this->jobTracker->getActiveJobsForUser($user->id);

        return response()->json([
            'jobs' => $jobs,
            'count' => count($jobs),
            'has_active' => count(array_filter($jobs, fn (array $job): bool => in_array($job['status'], ['pending', 'processing']))) > 0,
        ]);
    }

    /**
     * Get status of a specific job
     */
    public function show(string $jobId): JsonResponse
    {
        $job = $this->jobTracker->getJobStatus($jobId);

        if ($job === null) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        return response()->json(['job' => $job]);
    }

    /**
     * Dismiss a job notification
     */
    public function dismiss(Request $request, string $jobId): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $this->jobTracker->dismissJob($jobId, $user->id);

        return response()->json(['success' => true]);
    }
}
