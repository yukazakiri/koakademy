<?php

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an AI-supplied identifier matches several records.
 *
 * Rather than guessing, the exception hands the model the concrete candidate
 * keys so it can ask the administrator which record is meant.
 */
final class AmbiguousEntityException extends InvalidArgumentException
{
    /**
     * @param  array<int, string>  $candidates
     */
    public static function for(string $entityType, string $identifier, array $candidates): self
    {
        $list = implode('; ', $candidates);

        return new self(sprintf(
            'The identifier "%s" matches multiple %s records. Re-run the lookup with one of these exact keys: %s.',
            $identifier,
            str_replace('_', ' ', $entityType),
            $list,
        ));
    }
}
