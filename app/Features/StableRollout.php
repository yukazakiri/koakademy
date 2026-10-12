<?php

declare(strict_types=1);

namespace App\Features;

final class StableRollout
{
    public static function includes(string $seed, string $key, int $userId, ?int $schoolId, int $percentage): bool
    {
        $identity = json_encode([$seed, $key, $schoolId, $userId], JSON_THROW_ON_ERROR);
        $bucket = (int) hexdec(mb_substr(hash('sha256', $identity), 0, 7)) % 10000;

        return $bucket < max(0, min(100, $percentage)) * 100;
    }
}
