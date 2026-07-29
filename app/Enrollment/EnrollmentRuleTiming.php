<?php

declare(strict_types=1);

namespace App\Enrollment;

final class EnrollmentRuleTiming
{
    public static function appliesAtEntry(string $handler): bool
    {
        return ! self::appliesAtCompletion($handler);
    }

    public static function appliesAtCompletion(string $handler): bool
    {
        return $handler === 'billing.minimum_payment';
    }
}
