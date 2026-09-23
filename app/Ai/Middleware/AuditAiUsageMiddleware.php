<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

final class AuditAiUsageMiddleware
{
    /**
     * Audit AI prompt executions and token metrics for administrative tracking.
     */
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $startTime = microtime(true);

        return $next($prompt)->then(function (AgentResponse $response) use ($startTime): void {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            try {
                Log::info('AI Agent Prompt Completed', [
                    'conversation_id' => $response->conversationId,
                    'duration_ms' => $durationMs,
                    'has_approvals' => $response->hasPendingApprovals(),
                    'steps_count' => count($response->steps),
                ]);
            } catch (Throwable) {
                // Audit logging failure must not interrupt the AI stream or prompt response
            }
        });
    }
}
