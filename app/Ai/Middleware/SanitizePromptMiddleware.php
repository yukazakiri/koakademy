<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;

final class SanitizePromptMiddleware
{
    /**
     * Handle the incoming prompt and redact obvious sensitive PII tokens.
     */
    public function handle(PendingStep $step, Closure $next)
    {
        $messages = array_map(function (Message $message): Message {
            $content = $message->content;

            if ($message->role->value !== 'user' || ! is_string($content)) {
                return $message;
            }

            $sanitized = preg_replace('/\b(?:\d[ -]*?){13,16}\b/', '[REDACTED_PAYMENT_CARD]', $content);
            $sanitized = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[REDACTED_IDENTIFIER]', (string) $sanitized);

            if ($sanitized === $content) {
                return $message;
            }

            return $message instanceof UserMessage
                ? new UserMessage($sanitized, $message->attachments)
                : new Message($message->role, $sanitized);
        }, $step->messages);

        return $next($messages === $step->messages ? $step : $step->withMessages($messages));
    }
}
