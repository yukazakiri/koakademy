<?php

declare(strict_types=1);

namespace Modules\Cashier\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class CashierPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Cashier';
    }

    public function getId(): string
    {
        return 'cashier';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }
}
