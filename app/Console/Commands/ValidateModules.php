<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CompatibilityChecker;
use App\Modules\ModuleManifestRepository;
use Illuminate\Console\Command;
use Override;
use Throwable;

final class ValidateModules extends Command
{
    #[Override]
    protected $signature = 'modules:validate {--all : include Composer-installed extensions even when vendor scanning is disabled}';

    #[Override]
    protected $description = 'Validate installed extension identities, providers, and compatibility before deployment.';

    public function handle(ModuleManifestRepository $modules, CompatibilityChecker $compatibility): int
    {
        if ($this->option('all')) {
            config(['modules.scan.enabled' => true]);
        }
        try {
            $installed = $modules->installed();
            $errors = [];
            foreach ($installed as $module) {
                foreach ($compatibility->check($module->manifest)->errors as $error) {
                    $errors[] = $module->manifest->name.': '.$error;
                }
                foreach ($module->manifest->providers as $provider) {
                    if (! is_subclass_of($provider, \Illuminate\Support\ServiceProvider::class)) {
                        $errors[] = $module->manifest->name.": Provider [{$provider}] cannot be loaded.";
                    }
                }
            }
            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }
            $this->info('Validated '.count($installed).' installed extension(s).');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
