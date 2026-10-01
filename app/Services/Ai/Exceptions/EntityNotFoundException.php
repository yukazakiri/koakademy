<?php

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an AI-supplied identifier does not match any record.
 *
 * The message is written for the model reading a tool result, not for a log
 * file: it states what was looked up and how to recover.
 */
final class EntityNotFoundException extends InvalidArgumentException
{
    public static function for(string $entityType, string $identifier): self
    {
        return new self(sprintf(
            'No %s matches "%s". Verify the identifier, or use the matching search tool to look up the correct %s first.',
            str_replace('_', ' ', $entityType),
            $identifier,
            str_replace('_', ' ', $entityType),
        ));
    }
}
