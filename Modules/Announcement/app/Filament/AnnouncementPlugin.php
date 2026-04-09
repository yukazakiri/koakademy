<?php

declare(strict_types=1);

namespace Modules\Announcement\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class AnnouncementPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Announcement';
    }

    public function getId(): string
    {
        return 'announcement';
    }

    public function boot(Panel $panel): void {}
}
