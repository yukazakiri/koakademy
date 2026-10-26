<?php

declare(strict_types=1);

namespace App\Modules;

use Composer\Semver\Semver;
use UnexpectedValueException;

final class VersionConstraint
{
    public function matches(string $version, string $constraint): bool
    {
        // Koakademy edge images intentionally satisfy the matching stable core.
        $normalized = preg_replace('/-edge(?:[.+-].*)?$/', '', mb_trim($version)) ?: $version;

        try {
            return Semver::satisfies($normalized, $constraint);
        } catch (UnexpectedValueException) {
            return false;
        }
    }
}
