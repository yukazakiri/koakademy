<?php

declare(strict_types=1);

namespace Scripts;

use const PATHINFO_FILENAME;

use App\Support\ComposerPatchState;
use Composer\Composer;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Factory;
use Composer\Package\PackageInterface;
use Composer\Script\Event;

final class ComposerPatchGuard
{
    private const string PATCHES_PLUGIN_CLASS = 'cweagans\\Composer\\Plugin\\Patches';

    public static function evictStalePatchedPackages(Event $event): void
    {
        if (self::shouldSkipInstallMutation()) {
            return;
        }

        $composer = $event->getComposer();

        if (! self::isPatchesPluginLoaded($composer)) {
            return;
        }

        $localRepository = $composer->getRepositoryManager()->getLocalRepository();
        $installedPackages = $localRepository->getPackages();

        if ($installedPackages === []) {
            return;
        }

        $lockedHashes = ComposerPatchState::readLockedPackageHashes(self::patchesLockFilePath());
        $appliedHashes = ComposerPatchState::readAppliedPackageHashes(self::stateFilePath($composer));
        $installedNames = array_values(array_map(
            static fn (PackageInterface $package): string => $package->getName(),
            $installedPackages,
        ));
        $staleNames = ComposerPatchState::staleInstalledPackages($lockedHashes, $appliedHashes, $installedNames);

        if ($staleNames === []) {
            return;
        }

        $packagesToUninstall = array_values(array_filter(
            $installedPackages,
            static fn (PackageInterface $package): bool => in_array(mb_strtolower($package->getName()), $staleNames, true),
        ));

        if ($packagesToUninstall === []) {
            return;
        }

        $event->getIO()->write(sprintf(
            '  - Reinstalling stale patched package(s): <info>%s</info>',
            implode(', ', $staleNames),
        ));

        $installationManager = $composer->getInstallationManager();
        $promises = [];

        foreach ($packagesToUninstall as $package) {
            $promises[] = $installationManager->uninstall($localRepository, new UninstallOperation($package));
        }

        $promises = array_filter($promises);

        if ($promises !== []) {
            $composer->getLoop()->wait($promises);
        }

        $localRepository->write($event->isDevMode(), $installationManager);
    }

    public static function recordAppliedPatches(Event $event): void
    {
        if (self::shouldSkipInstallMutation()) {
            return;
        }

        $composer = $event->getComposer();

        if (! self::isPatchesPluginLoaded($composer)) {
            return;
        }

        $localRepository = $composer->getRepositoryManager()->getLocalRepository();
        $installedNames = array_values(array_map(
            static fn (PackageInterface $package): string => $package->getName(),
            $localRepository->getPackages(),
        ));
        $lockedHashes = ComposerPatchState::readLockedPackageHashes(self::patchesLockFilePath());

        ComposerPatchState::writeAppliedPackageHashes(self::stateFilePath($composer), $lockedHashes, $installedNames);
    }

    private static function shouldSkipInstallMutation(): bool
    {
        $argv = $_SERVER['argv'] ?? [];

        if (! is_array($argv)) {
            return false;
        }

        return in_array('--no-install', $argv, true) || in_array('--download-only', $argv, true);
    }

    private static function isPatchesPluginLoaded(Composer $composer): bool
    {
        foreach ($composer->getPluginManager()->getPlugins() as $plugin) {
            if (get_class($plugin) === self::PATCHES_PLUGIN_CLASS) {
                return true;
            }
        }

        return false;
    }

    private static function patchesLockFilePath(): string
    {
        $composerFile = Factory::getComposerFile();
        $resolved = realpath($composerFile);
        $path = $resolved !== false ? $resolved : (getcwd() ?: '.').'/'.basename($composerFile);
        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);

        return $base === 'composer'
            ? $dir.'/patches.lock.json'
            : $dir.'/'.$base.'-patches.lock.json';
    }

    private static function stateFilePath(Composer $composer): string
    {
        $vendorDir = (string) $composer->getConfig()->get('vendor-dir');

        return mb_rtrim($vendorDir, '/\\').'/'.ComposerPatchState::STATE_RELATIVE_PATH;
    }
}
