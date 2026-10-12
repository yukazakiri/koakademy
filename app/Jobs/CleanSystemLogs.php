<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LogCleanupRun;
use App\Services\LogRetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class CleanSystemLogs implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 5;

    public int $backoff = 15;

    public function __construct(public int $runId, public string $source) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('system-log-cleanup:'.$this->source))->releaseAfter(10)->expireAfter(90)];
    }

    public function handle(LogRetentionService $retention): void
    {
        $run = LogCleanupRun::query()->findOrFail($this->runId);
        if (in_array($run->status, ['completed', 'failed'], true)) {
            return;
        }

        $run->update(['status' => 'running', 'started_at' => $run->started_at ?? now()]);
        $deadline = microtime(true) + 35;

        do {
            if ($retention->processBatch($run)) {
                $run->update(['status' => 'completed', 'finished_at' => now()]);

                return;
            }
        } while (microtime(true) < $deadline || config('queue.default') === 'sync');

        // Continue large cleanups in a fresh job, below the worker timeout. The delay
        // lets this job release the overlap lock before the next one asks for it.
        self::dispatch($this->runId, $this->source)->delay(now()->addSeconds(15));
    }

    public function failed(?Throwable $exception): void
    {
        LogCleanupRun::query()->whereKey($this->runId)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'finished_at' => now()]);
    }
}
