<?php

declare(strict_types=1);

namespace App\Features;

final readonly class FeatureDecision
{
    public function __construct(
        public bool $active,
        public string $reason,
        public FeatureDenial $denial = FeatureDenial::None,
    ) {}

    /** @return array{active: bool, reason: string, denial: string} */
    public function toArray(): array
    {
        return ['active' => $this->active, 'reason' => $this->reason, 'denial' => $this->denial->value];
    }
}
