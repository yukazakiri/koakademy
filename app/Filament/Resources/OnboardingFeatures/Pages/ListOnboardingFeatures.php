<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures\Pages;

use App\Filament\Resources\OnboardingFeatures\OnboardingFeatureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOnboardingFeatures extends ListRecords
{
    protected static string $resource = OnboardingFeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
