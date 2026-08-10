<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangelogStatus: string
{
    case Live = 'live';
    case Stale = 'stale';
    case Empty = 'empty';
    case Unavailable = 'unavailable';
}
