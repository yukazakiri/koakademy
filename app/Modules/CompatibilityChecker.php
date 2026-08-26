<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Contracts\ModuleManifest;
use Composer\InstalledVersions;
use Illuminate\Foundation\Application;
use OutOfBoundsException;

final class CompatibilityChecker
{
    public function __construct(
        private readonly ModuleManifestRepository $manifests,
        private readonly VersionConstraint $constraints = new VersionConstraint,
    ) {}

    public function check(ModuleManifest $manifest): CompatibilityResult
    {
        $errors = [];

        $this->checkConstraint($errors, 'core', $manifest->requires['core'] ?? null, (string) config('app.version', '0.0.0'));
        $this->checkConstraint($errors, 'PHP', $manifest->requires['php'] ?? null, PHP_VERSION);
        $this->checkConstraint($errors, 'Laravel', $manifest->compatibility['laravel'] ?? null, Application::VERSION);
        $this->checkConstraint($errors, 'Filament', $manifest->compatibility['filament'] ?? null, $this->installedVersion('filament/filament'));

        foreach ($manifest->requires['modules'] ?? [] as $requiredModule => $constraint) {
            $installed = $this->manifests->find($requiredModule);

            if ($installed === null) {
                $errors[] = "Requires module [{$requiredModule}] to be installed.";

                continue;
            }

            $this->checkConstraint($errors, "module {$requiredModule}", $constraint, $installed->version);
        }

        return new CompatibilityResult($errors);
    }

    /**
     * @param  list<string>  $errors
     */
    private function checkConstraint(array &$errors, string $subject, ?string $constraint, string $version): void
    {
        if ($constraint === null || $this->constraints->matches($version, $constraint)) {
            return;
        }

        $errors[] = "{$subject} {$version} does not satisfy [{$constraint}].";
    }

    private function installedVersion(string $package): string
    {
        try {
            return InstalledVersions::getPrettyVersion($package) ?: '0.0.0';
        } catch (OutOfBoundsException) {
            return '0.0.0';
        }
    }
}
