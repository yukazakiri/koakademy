<?php

declare(strict_types=1);

namespace App\Enterprise;

final readonly class CapabilityDecision
{
    public function __construct(public bool $allowed, public string $reason) {}

    /** @return array{allowed: bool, reason: string} */
    public function toArray(): array
    {
        return ['allowed' => $this->allowed, 'reason' => $this->reason];
    }
}
