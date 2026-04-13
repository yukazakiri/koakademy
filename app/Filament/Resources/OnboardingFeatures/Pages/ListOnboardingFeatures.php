<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures\Pages;

use App\Features\Onboarding\FeatureClassRegistry;
use App\Filament\Resources\OnboardingFeatures\OnboardingFeatureResource;
use App\Models\OnboardingFeature;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOnboardingFeatures extends ListRecords
{
    protected static string $resource = OnboardingFeatureResource::class;

    public function getSubheading(): ?string
    {
        $query = OnboardingFeatureResource::getEloquentQuery();
        $total = $query->count();
        $active = (clone $query)->where('is_active', true)->count();
        $classBased = (clone $query)
            ->get()
            ->filter(fn (OnboardingFeature $feature) => FeatureClassRegistry::classForKey($feature->feature_key) !== null)
            ->count();

        return "Total: {$total}  •  Active: {$active}  •  Class-based: {$classBased}";
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus'),
        ];
    }
}
