<?php

declare(strict_types=1);

namespace App\Modules\Livewire;

use App\Modules\ModuleAccessService;
use Livewire\ComponentHook;
use LogicException;

final class ModuleAvailabilityHook extends ComponentHook
{
    public function boot(): void
    {
        if (! $this->component instanceof \Livewire\Component) {
            throw new LogicException('A module availability hook requires a Livewire component.');
        }
        $access = app(ModuleAccessService::class);
        $module = $access->owningClass($this->component::class);
        if ($module !== null) {
            abort_unless($access->isAvailable($module->manifest->name), 404, 'This extension is unavailable.');
        }
    }
}
