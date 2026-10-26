<?php

declare(strict_types=1);

namespace App\Filament\Plugins;

use App\Modules\ModuleAccessService;
use App\Modules\ModuleManifestRepository;
use Coolsam\Modules\Enums\ConfigMode;
use Coolsam\Modules\ModulesPlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Override;

final class DiscoveredModulesPlugin extends ModulesPlugin
{
    public function boot(Panel $panel): void
    {
        $mode = ConfigMode::tryFrom(config()->string('filament-modules.mode', ConfigMode::BOTH->value));
        if (! $mode?->shouldRegisterPanels()) {
            return;
        }
        $panels = $this->getModulePanels();
        if ($panels === []) {
            return;
        }
        $group = config()->string('filament-modules.panels.group', 'Panels');
        $panel->navigationGroups([NavigationGroup::make($group)]);
        $items = [];
        foreach ($panels as $modulePanel) {
            $items[] = NavigationItem::make($modulePanel->getBrandName() ?: $modulePanel->getId())
                ->group($group)
                ->sort(config()->integer('filament-modules.panels.group-sort', 0))
                ->url($modulePanel->getUrl())
                ->openUrlInNewTab(config()->boolean('filament-modules.panels.open-in-new-tab', false));
        }
        $panel->navigationItems($items);
    }

    /** @return list<class-string<Plugin>> */
    #[Override]
    protected function getModulePlugins(): array
    {
        if (! config('filament-modules.auto-register-plugins', false)) {
            return [];
        }
        $plugins = [];
        foreach (app(ModuleManifestRepository::class)->installed() as $module) {
            if (! app(ModuleAccessService::class)->isAvailable($module->manifest->name)) {
                continue;
            }
            foreach ($module->classesIn('app/Filament', 'Plugin.php') as $class) {
                if (is_subclass_of($class, Plugin::class)) {
                    $plugins[] = $class;
                }
            }
        }

        return $plugins;
    }

    /** @return list<Panel> */
    #[Override]
    protected function getModulePanels(): array
    {
        $panels = [];
        foreach (app(ModuleManifestRepository::class)->installed() as $module) {
            if (! app(ModuleAccessService::class)->isAvailable($module->manifest->name)) {
                continue;
            }
            foreach ($module->classesIn('app/Providers/Filament') as $class) {
                if (is_subclass_of($class, PanelProvider::class)) {
                    $provider = app($class);
                    if ($provider instanceof PanelProvider) {
                        $panels[] = $provider->panel(Panel::make());
                    }
                }
            }
        }

        return $panels;
    }
}
