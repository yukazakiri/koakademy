<?php

declare(strict_types=1);

namespace Scripts;

use const PATHINFO_FILENAME;

use App\Support\ComposerPatchState;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Factory;
use Composer\Installer\InstallerEvent;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Package\PackageInterface;
use Composer\Script\Event;

final class ComposerPatchGuard
{
    private const string PATCHES_PLUGIN_CLASS = 'cweagans\\Composer\\Plugin\\Patches';

    /**
     * @var array<string, true>
     */
    private static array $touchedPackages = [];

    /**
     * @var array<int, true>
     */
    private static array $registeredDispatchers = [];

    public static function preparePatchTracking(Event $event): void
    {
        self::$touchedPackages = [];

        if (self::shouldSkipInstallMutation()) {
            return;
        }

        $composer = $event->getComposer();

        if (! self::isPatchesPluginLoaded($composer)) {
            return;
        }

        self::registerPackageListeners($composer);
    }

    public static function recordTouchedPackage(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if ($operation instanceof InstallOperation) {
            $packageName = $operation->getPackage()->getName();
        } elseif ($operation instanceof UpdateOperation) {
            $packageName = $operation->getTargetPackage()->getName();
        } else {
            return;
        }

        self::$touchedPackages[mb_strtolower($packageName)] = true;
    }

    public static function reinstallStalePatchedPackages(InstallerEvent $event): void
    {
        if (! $event->isExecutingOperations() || self::shouldSkipInstallMutation()) {
            return;
        }

        $composer = $event->getComposer();

        if (! self::isPatchesPluginLoaded($composer)) {
            return;
        }

        self::registerPackageListeners($composer);

        $localRepository = $composer->getRepositoryManager()->getLocalRepository();
        $installedPackages = $localRepository->getCanonicalPackages();

        if ($installedPackages === []) {
            return;
        }

        $plannedLookup = self::plannedPackageLookup($event);
        $lockedHashes = ComposerPatchState::readLockedPackageHashes(self::patchesLockFilePath());
        $appliedHashes = ComposerPatchState::readAppliedPackageHashes(self::stateFilePath($composer));
        $installedNames = array_values(array_map(
            static fn (PackageInterface $package): string => $package->getName(),
            $installedPackages,
        ));
        $staleNames = array_values(array_filter(
            ComposerPatchState::staleInstalledPackages($lockedHashes, $appliedHashes, $installedNames),
            static fn (string $packageName): bool => ! isset($plannedLookup[$packageName]),
        ));

        if ($staleNames === []) {
            return;
        }

        $stalePackages = array_values(array_filter(
            $installedPackages,
            static fn (PackageInterface $package): bool => in_array(mb_strtolower($package->getName()), $staleNames, true),
        ));

        if ($stalePackages === []) {
            return;
        }

        $lockedByName = self::lockedPackagesByName($composer, $event->isDevMode());
        $uninstallOperations = [];
        $installOperations = [];

        foreach ($stalePackages as $package) {
            $normalized = mb_strtolower($package->getName());
            $uninstallOperations[] = new UninstallOperation($package);
            $installOperations[] = new InstallOperation($lockedByName[$normalized] ?? $package);
        }

        $event->getIO()->write(sprintf(
            '  - Reinstalling stale patched package(s): <info>%s</info>',
            implode(', ', $staleNames),
        ));

        $installationManager = $composer->getInstallationManager();
        $installationManager->execute($localRepository, $uninstallOperations, $event->isDevMode(), true);
        $installationManager->execute($localRepository, $installOperations, $event->isDevMode(), true);
    }

    public static function recordAppliedPatches(Event $event): void
    {
        if (self::shouldSkipInstallMutation()) {
            self::$touchedPackages = [];

            return;
        }

        $composer = $event->getComposer();

        if (! self::isPatchesPluginLoaded($composer)) {
            self::$touchedPackages = [];

            return;
        }

        $statePath = self::stateFilePath($composer);
        $previousAppliedHashes = ComposerPatchState::readAppliedPackageHashes($statePath);
        $lockedHashes = ComposerPatchState::readLockedPackageHashes(self::patchesLockFilePath());
        $localRepository = $composer->getRepositoryManager()->getLocalRepository();
        $installedNames = array_values(array_map(
            static fn (PackageInterface $package): string => $package->getName(),
            $localRepository->getCanonicalPackages(),
        ));

        $resolvedHashes = ComposerPatchState::resolveAppliedPackageHashes(
            $previousAppliedHashes,
            $lockedHashes,
            $installedNames,
            array_keys(self::$touchedPackages),
        );

        ComposerPatchState::writeAppliedPackageHashes($statePath, $resolvedHashes);
        self::$touchedPackages = [];
    }

    private static function registerPackageListeners(Composer $composer): void
    {
        $dispatcher = $composer->getEventDispatcher();
        $dispatcherId = spl_object_id($dispatcher);

        if (isset(self::$registeredDispatchers[$dispatcherId])) {
            return;
        }

        $listener = [self::class, 'recordTouchedPackage'];
        $dispatcher->addListener(PackageEvents::POST_PACKAGE_INSTALL, $listener, -10);
        $dispatcher->addListener(PackageEvents::POST_PACKAGE_UPDATE, $listener, -10);
        self::$registeredDispatchers[$dispatcherId] = true;
    }

    /**
     * @return array<string, true>
     */
    private static function plannedPackageLookup(InstallerEvent $event): array
    {
        $transaction = $event->getTransaction();
        if ($transaction === null) {
            return [];
        }

        $planned = [];

        foreach ($transaction->getOperations() as $operation) {
            if ($operation instanceof InstallOperation || $operation instanceof UninstallOperation) {
                $planned[mb_strtolower($operation->getPackage()->getName())] = true;
            } elseif ($operation instanceof UpdateOperation) {
                $planned[mb_strtolower($operation->getInitialPackage()->getName())] = true;
                $planned[mb_strtolower($operation->getTargetPackage()->getName())] = true;
            }
        }

        return $planned;
    }

    /**
     * @return array<string, PackageInterface>
     */
    private static function lockedPackagesByName(Composer $composer, bool $devMode): array
    {
        $locker = $composer->getLocker();
        if (! $locker->isLocked()) {
            return [];
        }

        $packages = [];

        foreach ($locker->getLockedRepository($devMode)->getCanonicalPackages() as $package) {
            $packages[mb_strtolower($package->getName())] = $package;
        }

        return $packages;
    }

    private static function shouldSkipInstallMutation(): bool
    {
        $argv = $_SERVER['argv'] ?? [];

        if (! is_array($argv)) {
            return false;
        }

        return in_array('--no-install', $argv, true)
            || in_array('--download-only', $argv, true)
            || in_array('--dry-run', $argv, true);
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
        $base = pathinfo($composerFile, PATHINFO_FILENAME);

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
