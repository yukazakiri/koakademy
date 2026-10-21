<?php

declare(strict_types=1);

namespace App\Enums;

enum FeatureReleaseAudience: string
{
    case Stable = 'stable';
    case Beta = 'beta';
    case Selected = 'selected';
}
