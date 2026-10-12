<?php

declare(strict_types=1);

namespace App\Features;

/** Distinguishes forbidden access from ordinary gradual-release availability. */
enum FeatureDenial: string
{
    case None = 'none';
    case Excluded = 'excluded';
    case NotReleased = 'not_released';
}
