<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\CompatibilityChecker;
use App\Modules\Contracts\ModuleManifest;
use App\Modules\Contracts\RegistryEntry;
use App\Modules\Exceptions\ModuleRegistryException;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use App\Modules\RegistryClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;
use Throwable;

final class AdministratorModuleMarketplaceController extends Controller
{
    public function __construct(
        private readonly ModuleManifestRepository $manifests,
        private readonly RegistryClient $registry,
        private readonly CompatibilityChecker $compatibility,
        private readonly ModuleStateRepository $states,
        private readonly RepositoryInterface $modules,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->administrator();
        $registryEntries = [];
        $registryError = null;

        if ((bool) config('modules-marketplace.enabled', false)) {
            try {
                $registryEntries = $this->registry->all($request->boolean('refresh'));
            } catch (ModuleRegistryException) {
                $registryError = 'The signed module catalog is temporarily unavailable. Installed modules are still shown below.';
            } catch (Throwable $exception) {
                report($exception);
                $registryError = 'The module catalog could not be reached. Installed modules are still shown below.';
            }
        }

        return Inertia::render('administrators/module-marketplace/index', [
            'user' => $this->userPayload($user),
            'marketplace' => [
                'enabled' => (bool) config('modules-marketplace.enabled', false),
                'registry_url' => config('modules-marketplace.registry_url'),
                'registry_error' => $registryError,
                'modules' => $this->modulePayload($registryEntries),
            ],
        ]);
    }

    public function enable(string $module): RedirectResponse
    {
        return $this->setStatus($module, true);
    }

    public function disable(string $module): RedirectResponse
    {
        return $this->setStatus($module, false);
    }

    /**
     * @param  list<RegistryEntry>  $registryEntries
     * @return list<array<string, mixed>>
     */
    private function modulePayload(array $registryEntries): array
    {
        $catalog = [];

        foreach ($registryEntries as $entry) {
            $catalog[mb_strtolower($entry->manifest->name)] = [
                'manifest' => $entry->manifest,
                'entry' => $entry,
            ];
        }

        foreach ($this->manifests->all() as $manifest) {
            $key = mb_strtolower($manifest->name);

            if (! isset($catalog[$key])) {
                $catalog[$key] = [
                    'manifest' => $manifest,
                    'entry' => null,
                ];
            }
        }

        $modules = array_map(
            fn (array $item): array => $this->serializeModule($item['manifest'], $item['entry']),
            array_values($catalog),
        );

        usort($modules, static fn (array $first, array $second): int => strcasecmp((string) $first['name'], (string) $second['name']));

        return array_values($modules);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeModule(ModuleManifest $manifest, ?RegistryEntry $entry): array
    {
        $installedManifest = $this->manifests->find($manifest->name);
        $installedModule = $this->modules->find($manifest->name);
        $compatibility = $this->compatibility->check($manifest);
        $activationErrors = $this->activationErrors($manifest, $installedModule);
        $latestRelease = $entry?->latestRelease();
        $restartRequired = $installedModule instanceof Module && $this->states->restartRequired($manifest->name);
        $status = ! ($installedModule instanceof Module)
            ? 'not_installed'
            : ($restartRequired ? 'restart_required' : ($installedModule->isEnabled() ? 'enabled' : 'disabled'));

        return [
            'name' => $manifest->name,
            'alias' => $manifest->alias,
            'version' => $latestRelease?->version ?? $manifest->version,
            'installed_version' => $installedManifest?->version,
            'description' => $manifest->description,
            'author' => $manifest->author,
            'license' => $manifest->license,
            'composer_package' => $manifest->composerPackage,
            'repository' => $entry?->repository ?? $manifest->repository,
            'homepage' => $entry?->homepage ?? $manifest->homepage,
            'installed' => $installedModule instanceof Module,
            'enabled' => $installedModule?->isEnabled() ?? false,
            'status' => $status,
            'restart_required' => $restartRequired,
            'update_available' => $installedManifest !== null
                && $latestRelease !== null
                && version_compare($latestRelease->version, $installedManifest->version, '>'),
            'compatible' => $compatibility->isCompatible() && $activationErrors === [],
            'compatibility_errors' => [...$compatibility->errors, ...$activationErrors],
            'asset_url' => $latestRelease?->assetUrl,
            'released_at' => $latestRelease?->releasedAt,
        ];
    }

    private function setStatus(string $moduleName, bool $enabled): RedirectResponse
    {
        $this->administrator();
        abort_unless((bool) config('modules-marketplace.enabled', false), 404);

        $module = $this->modules->find($moduleName);
        abort_unless($module instanceof Module, 404);

        $manifest = $this->manifests->find($module->getName());
        abort_unless($manifest instanceof ModuleManifest, 404);

        $activationErrors = $this->activationErrors($manifest, $module);

        if ($enabled && $activationErrors !== []) {
            throw ValidationException::withMessages(['module' => $activationErrors]);
        }

        if (! $enabled) {
            $dependent = collect($this->manifests->enabled())
                ->first(function (ModuleManifest $candidate) use ($manifest): bool {
                    if (strcasecmp($candidate->name, $manifest->name) === 0) {
                        return false;
                    }

                    return collect(array_keys($candidate->requires['modules'] ?? []))
                        ->contains(fn (string $required): bool => strcasecmp($required, $manifest->name) === 0);
                });

            if ($dependent instanceof ModuleManifest) {
                throw ValidationException::withMessages([
                    'module' => "Disable dependent module [{$dependent->name}] before disabling [{$manifest->name}].",
                ]);
            }
        }

        if ($enabled) {
            $module->enable();
        } else {
            $module->disable();
        }

        $status = $enabled ? 'enabled' : 'disabled';

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Module [{$module->getName()}] has been {$status}. Restart or redeploy the application so every worker loads the new module state.",
        ]);
    }

    /**
     * @return list<string>
     */
    private function activationErrors(ModuleManifest $manifest, ?Module $module): array
    {
        if (! $module instanceof Module || ! $module->isDisabled()) {
            return [];
        }

        $errors = [];

        foreach (array_keys($manifest->requires['modules'] ?? []) as $requiredModule) {
            $dependency = $this->modules->find($requiredModule);

            if ($dependency instanceof Module && $dependency->isDisabled()) {
                $errors[] = "Enable module [{$dependency->getName()}] first.";
            }
        }

        return $errors;
    }

    private function administrator(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->is_super_admin, 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url,
            'role' => $user->role?->getLabel() ?? 'Administrator',
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
