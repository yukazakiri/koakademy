<?php

declare(strict_types=1);

namespace App\Modules;

final class VersionConstraint
{
    public function matches(string $version, string $constraint): bool
    {
        $version = $this->normalizeVersion($version);
        $constraint = mb_trim($constraint);

        foreach (preg_split('/\s*\|\|\s*/', mb_trim($constraint)) ?: [] as $alternative) {
            if ($this->matchesAlternative($version, mb_trim($alternative))) {
                return true;
            }
        }

        return false;
    }

    private function matchesAlternative(string $version, string $constraint): bool
    {
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        if (str_ends_with($constraint, '.*')) {
            return str_starts_with($version, mb_substr($constraint, 0, -1));
        }

        if (str_starts_with($constraint, '^')) {
            $lower = mb_substr($constraint, 1);
            $upper = $this->caretUpperBound($lower);

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (str_starts_with($constraint, '~')) {
            $lower = mb_ltrim(mb_substr($constraint, 1));
            $parts = array_map('intval', explode('.', preg_replace('/[-+].*$/', '', $lower)));
            $upper = count($parts) <= 1
                ? ($parts[0] + 1).'.0.0'
                : $parts[0].'.'.($parts[1] + 1).'.0';

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)\s*(.+)$/', $constraint, $matches)) {
            return version_compare($version, $matches[2], $matches[1] === '=' ? '==' : $matches[1]);
        }

        return version_compare($version, $constraint, '==');
    }

    private function caretUpperBound(string $version): string
    {
        $parts = array_map('intval', explode('.', preg_replace('/[-+].*$/', '', $version)));
        $major = $parts[0] ?? 0;
        $minor = $parts[1] ?? 0;
        $patch = $parts[2] ?? 0;

        if ($major > 0) {
            return ($major + 1).'.0.0';
        }

        if ($minor > 0) {
            return '0.'.($minor + 1).'.0';
        }

        return '0.0.'.($patch + 1);
    }

    private function normalizeVersion(string $version): string
    {
        $normalized = preg_replace('/^v/i', '', mb_trim($version)) ?: mb_trim($version);

        return preg_replace('/[-+].*$/', '', $normalized) ?: $normalized;
    }
}
