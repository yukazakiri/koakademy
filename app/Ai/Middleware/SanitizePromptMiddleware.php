<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

final class SanitizePromptMiddleware
{
    /**
     * Handle the incoming prompt and redact obvious sensitive PII tokens.
     */
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        // PII redaction patterns: Credit card numbers (16 digits), SSN/TIN
        $content = $prompt->prompt;

        if (is_string($content)) {
            // Mask 16-digit card numbers
            $sanitized = preg_replace('/\b(?:\d[ -]*?){13,16}\b/', '[REDACTED_PAYMENT_CARD]', $content);
            // Mask TIN / National ID patterns
            $sanitized = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[REDACTED_IDENTIFIER]', (string) $sanitized);

            if ($sanitized !== $content) {
                $prompt = $prompt->revise((string) $sanitized);
            }
        }

        return $next($prompt);
    }
}
