<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class StudentMedicalRecordsPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'StudentMedicalRecords';
    }

    public function getId(): string
    {
        return 'studentmedicalrecords';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }
}
