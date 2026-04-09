<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for tracking active background jobs per user.
 *
 * This service provides a unified way to track job progress across the application,
 * allowing the frontend to display real-time job status to users.
 */
final class JobTrackerService
{
    private const string CACHE_PREFIX = 'active_jobs:';

    private const string JOB_PREFIX = 'job_progress:';

    private const int DEFAULT_TTL = 3600; // 1 hour

    private const int USER_JOBS_TTL = 7200; // 2 hours for user job list

    /**
     * Register a new job for tracking
     */
    public function registerJob(
        string $jobId,
        int $userId,
        string $type,
        string $title,
        array $metadata = []
    ): void {
        // Store job progress data
        $jobData = [
            'id' => $jobId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'status' => 'pending',
            'percentage' => 0,
            'message' => 'Initializing...',
            'metadata' => $metadata,
            'created_at' => format_timestamp_now(),
            'updated_at' => format_timestamp_now(),
        ];

        Cache::put(self::JOB_PREFIX.$jobId, $jobData, self::DEFAULT_TTL);

        // Add to user's active jobs list
        $this->addToUserJobsList($userId, $jobId);

        Log::info('Job registered for tracking', [
            'job_id' => $jobId,
            'user_id' => $userId,
            'type' => $type,
        ]);
    }

    /**
     * Update job progress
     *
     * @param  array<string, mixed>  $metadata  Optional metadata to merge with existing
     */
    public function updateProgress(
        string $jobId,
        int $percentage,
        string $message,
        string $status = 'processing',
        array $metadata = []
    ): void {
        $jobData = Cache::get(self::JOB_PREFIX.$jobId);

        if ($jobData === null) {
            Log::warning('Attempted to update non-existent job', ['job_id' => $jobId]);

            return;
        }

        $jobData['percentage'] = min(100, max(0, $percentage));
        $jobData['message'] = $message;
        $jobData['status'] = $status;
        $jobData['updated_at'] = format_timestamp_now();

        // Merge new metadata with existing
        if ($metadata !== []) {
            $jobData['metadata'] = array_merge($jobData['metadata'] ?? [], $metadata);
        }

        Cache::put(self::JOB_PREFIX.$jobId, $jobData, self::DEFAULT_TTL);
    }

    /**
     * Mark job as completed
     */
    public function markCompleted(
        string $jobId,
        string $message = 'Completed successfully',
        ?string $downloadUrl = null,
        array $metadata = []
    ): void {
        $jobData = Cache::get(self::JOB_PREFIX.$jobId);

        if ($jobData === null) {
            return;
        }

        $jobData['percentage'] = 100;
        $jobData['message'] = $message;
        $jobData['status'] = 'completed';
        $jobData['download_url'] = $downloadUrl;
        $jobData['completed_at'] = format_timestamp_now();
        $jobData['updated_at'] = format_timestamp_now();

        if ($metadata !== []) {
            $jobData['metadata'] = array_merge($jobData['metadata'] ?? [], $metadata);
        }

        // Keep completed jobs visible for 5 minutes
        Cache::put(self::JOB_PREFIX.$jobId, $jobData, 300);

        Log::info('Job marked as completed', ['job_id' => $jobId]);
    }

    /**
     * Mark job as failed
     */
    public function markFailed(string $jobId, string $errorMessage): void
    {
        $jobData = Cache::get(self::JOB_PREFIX.$jobId);

        if ($jobData === null) {
            return;
        }

        $jobData['message'] = $errorMessage;
        $jobData['status'] = 'failed';
        $jobData['failed_at'] = format_timestamp_now();
        $jobData['updated_at'] = format_timestamp_now();

        // Keep failed jobs visible for 10 minutes
        Cache::put(self::JOB_PREFIX.$jobId, $jobData, 600);

        Log::error('Job marked as failed', ['job_id' => $jobId, 'error' => $errorMessage]);
    }

    /**
     * Get all active jobs for a user
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveJobsForUser(int $userId): array
    {
        $jobIds = $this->getUserJobsList($userId);
        $activeJobs = [];

        foreach ($jobIds as $jobId) {
            $jobData = Cache::get(self::JOB_PREFIX.$jobId);

            if ($jobData !== null) {
                $activeJobs[] = $jobData;
            }
        }

        // Sort by created_at descending (newest first)
        usort($activeJobs, fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        // Clean up completed/failed jobs from user's list
        $this->cleanupUserJobsList($userId, $activeJobs);

        return $activeJobs;
    }

    /**
     * Get a specific job's status
     */
    public function getJobStatus(string $jobId): ?array
    {
        return Cache::get(self::JOB_PREFIX.$jobId);
    }

    /**
     * Remove a job from tracking (user dismissed it)
     */
    public function dismissJob(string $jobId, int $userId): void
    {
        $jobIds = $this->getUserJobsList($userId);
        $jobIds = array_filter($jobIds, fn (string $id): bool => $id !== $jobId);
        Cache::put(self::CACHE_PREFIX.$userId, array_values($jobIds), self::USER_JOBS_TTL);

        // Also remove the job data if it's completed or failed
        $jobData = Cache::get(self::JOB_PREFIX.$jobId);
        if ($jobData !== null && in_array($jobData['status'], ['completed', 'failed'])) {
            Cache::forget(self::JOB_PREFIX.$jobId);
        }
    }

    /**
     * Add job ID to user's active jobs list
     */
    private function addToUserJobsList(int $userId, string $jobId): void
    {
        $jobIds = $this->getUserJobsList($userId);

        if (! in_array($jobId, $jobIds)) {
            $jobIds[] = $jobId;
            Cache::put(self::CACHE_PREFIX.$userId, $jobIds, self::USER_JOBS_TTL);
        }
    }

    /**
     * Get user's job IDs list
     *
     * @return array<int, string>
     */
    private function getUserJobsList(int $userId): array
    {
        return Cache::get(self::CACHE_PREFIX.$userId, []);
    }

    /**
     * Clean up old completed/failed jobs from user's list
     *
     * @param  array<int, array<string, mixed>>  $activeJobs
     */
    private function cleanupUserJobsList(int $userId, array $activeJobs): void
    {
        $validJobIds = array_map(fn (array $job): mixed => $job['id'], $activeJobs);
        $storedJobIds = $this->getUserJobsList($userId);

        // Only keep jobs that still have data in cache
        $cleanedJobIds = array_filter($storedJobIds, fn (string $id): bool => in_array($id, $validJobIds));

        if (count($cleanedJobIds) !== count($storedJobIds)) {
            Cache::put(self::CACHE_PREFIX.$userId, array_values($cleanedJobIds), self::USER_JOBS_TTL);
        }
    }
}
