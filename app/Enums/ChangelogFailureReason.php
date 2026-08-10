<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangelogFailureReason: string
{
    case MissingRepository = 'missing_repository';
    case Unauthorized = 'unauthorized';
    case NotFound = 'not_found';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';
}
