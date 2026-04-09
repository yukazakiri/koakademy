<?php

declare(strict_types=1);

namespace App\Enums;

enum ClassPostType: string
{
    case Assignment = 'assignment';
    case Activity = 'activity';
    case Announcement = 'announcement';
    case Quiz = 'quiz';
}
