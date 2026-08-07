<?php

declare(strict_types=1);

namespace App\Filament\Plugins;

use App\Filament\Plugins\PennantManager\Resources\FeatureResource;
use App\Filament\Plugins\Widgets\PennantFeatureAdoptionWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class PennantManagerPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'pennant-manager';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                FeatureResource::class,
            ])
            ->widgets([
                PennantFeatureAdoptionWidget::class,
            ]);
    }

    public function boot(Panel $panel): void {}
}
