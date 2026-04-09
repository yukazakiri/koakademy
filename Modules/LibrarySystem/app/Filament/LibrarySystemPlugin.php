<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class LibrarySystemPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'LibrarySystem';
    }

    public function getId(): string
    {
        return 'librarysystem';
    }

    public function boot(Panel $panel): void
    {
        // Intentionally left blank.
    }
}
