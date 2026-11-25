<?php

declare(strict_types=1);

namespace App\Services;

final class AppBuildVersionService
{
    /**
     * Resolve the current frontend asset hash matching Inertia's default
     * version calculation (ASSET_URL, Vite manifest, or Mix manifest).
     */
    public function resolveAssetVersion(): ?string
    {
        $assetUrl = config('app.asset_url');

        if (is_string($assetUrl) && $assetUrl !== '') {
            return hash('xxh128', $assetUrl);
        }

        $viteManifest = public_path('build/manifest.json');

        if (is_file($viteManifest)) {
            $hash = hash_file('xxh128', $viteManifest);

            return is_string($hash) ? $hash : null;
        }

        $mixManifest = public_path('mix-manifest.json');

        if (is_file($mixManifest)) {
            $hash = hash_file('xxh128', $mixManifest);

            return is_string($hash) ? $hash : null;
        }

        return null;
    }

    /**
     * Optional operator-controlled epoch that forces a hard reload when bumped.
     */
    public function forceReloadVersion(): string
    {
        return mb_trim((string) config('app.updates.force_reload_version', ''));
    }

    /**
     * Resolve the Inertia asset version string for this deployment.
     *
     * When no force-reload epoch is set, this returns the exact asset hash so
     * existing Inertia clients and tests remain fully compatible.
     */
    public function inertiaVersion(): ?string
    {
        $assetVersion = $this->resolveAssetVersion();
        $forceEpoch = $this->forceReloadVersion();

        if ($forceEpoch === '') {
            return $assetVersion;
        }

        if ($assetVersion === null || $assetVersion === '') {
            return $forceEpoch;
        }

        return "{$forceEpoch}:{$assetVersion}";
    }

    public function isSoftReloadEnabled(): bool
    {
        return (bool) config('app.updates.soft_reload', true);
    }

    public function pollIntervalSeconds(): int
    {
        return max(30, (int) config('app.updates.poll_interval_seconds', 300));
    }

    /**
     * Determine whether an Inertia version mismatch must fall back to a hard
     * 409 reload instead of a non-interruptive soft update response.
     */
    public function requiresHardReload(?string $clientVersion, ?string $serverVersion): bool
    {
        if (! $this->isSoftReloadEnabled()) {
            return true;
        }

        if ($clientVersion === null || $clientVersion === '') {
            return true;
        }

        if ($serverVersion === null || $serverVersion === '') {
            return false;
        }

        $forceEpoch = $this->forceReloadVersion();

        if ($forceEpoch !== '') {
            $clientEpoch = str_contains($clientVersion, ':')
                ? mb_substr($clientVersion, 0, (int) mb_strpos($clientVersion, ':'))
                : '';

            if ($clientEpoch !== $forceEpoch) {
                return true;
            }
        }

        return false;
    }

    /**
     * Opaque build metadata safe to share with both authenticated and public
     * clients without exposing human-readable release version strings.
     *
     * @return array{buildId: string|null, softReload: bool, pollIntervalSeconds: int}
     */
    public function buildPayload(): array
    {
        return [
            'buildId' => $this->inertiaVersion(),
            'softReload' => $this->isSoftReloadEnabled(),
            'pollIntervalSeconds' => $this->pollIntervalSeconds(),
        ];
    }
}
