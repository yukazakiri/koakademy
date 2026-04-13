<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures\Pages;

use App\Filament\Resources\OnboardingFeatures\OnboardingFeatureResource;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditOnboardingFeature extends EditRecord
{
    protected static string $resource = OnboardingFeatureResource::class;

    protected function afterSave(): void
    {
        $record = $this->record;

        try {
            if ($record->is_active) {
                OnboardingFeatureResource::activateFeature($record->feature_key);
            } else {
                OnboardingFeatureResource::deactivateFeature($record->feature_key);
            }

            Notification::make()
                ->title('Onboarding feature saved')
                ->body("Feature '{$record->name}' has been ".($record->is_active ? 'activated' : 'deactivated').' for all users.')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Feature flag update failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
