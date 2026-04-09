<?php

declare(strict_types=1);

namespace App\Enums;

enum SubjectEnrolledEnum: string
{
    case INTERNAL = 'internal';
    case CREDITED = 'credited';
    case NON_CREDITED = 'non_credited';
}
