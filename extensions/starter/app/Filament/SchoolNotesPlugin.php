<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class SchoolNotesPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'school-notes-starter';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Resources',
            for: 'Koakademy\\SchoolNotes\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void {}
}
