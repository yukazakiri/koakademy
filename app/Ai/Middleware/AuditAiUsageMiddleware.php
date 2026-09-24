<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\PendingStep;
use Throwable;

final class AuditAiUsageMiddleware
{
    /**
     * Audit AI prompt executions and token metrics for administrative tracking.
     */
    public function handle(PendingStep $step, Closure $next)
    {
        $startTime = microtime(true);

        return $next($step)->then(function (StepResponse $response) use ($startTime, $step): void {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            try {
                Log::channel('daily')->info('AI Agent Generation Step Completed', [
                    'invocation_id' => $step->invocationId,
                    'step' => $step->number,
                    'provider' => $step->provider,
                    'model' => $step->model,
                    'duration_ms' => $durationMs,
                    'tool_calls' => count($response->toolCalls),
                    'pending_approvals' => count($response->pendingApprovals),
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                ]);
            } catch (Throwable) {
                // Audit logging failure must not interrupt the AI generation step.
            }
        });
    }
}
