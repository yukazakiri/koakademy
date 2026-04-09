<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures\Pages;

use App\Filament\Resources\OnboardingFeatures\OnboardingFeatureResource;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Laravel\Pennant\Feature;

final class CreateOnboardingFeature extends CreateRecord
{
    protected static string $resource = OnboardingFeatureResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        try {
            if ($record->is_active) {
                Feature::activateForEveryone($record->feature_key);
            }

            Notification::make()
                ->title('Onboarding feature created')
                ->body("Feature '{$record->name}' has been created".($record->is_active ? ' and activated for all users.' : '.'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Feature flag activation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
