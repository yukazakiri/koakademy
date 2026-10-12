<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Resources\FeatureResource\Pages;

use App\Filament\Plugins\PennantManager\Resources\FeatureResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

final class ListFeatures extends ListRecords
{
    protected static string $resource = FeatureResource::class;

    public function getSubheading(): ?string
    {
        return 'Legacy values are read-only. Manage role defaults, beta cohorts, and rollouts in Administrators → Feature access.';
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('featureAccess')->label('Manage feature access')->url(route('administrators.feature-toggles.index'))];
    }
}
