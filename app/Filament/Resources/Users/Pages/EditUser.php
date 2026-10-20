<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserFeatureFlagService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditUser extends EditRecord
{
    #[Override]
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $state = $this->form->getRawState();
        app(\App\Services\UserSystemRoleService::class)->assign($this->record, \App\Enums\UserRole::from($state['role']), $state['additional_access'] ?? []);

        if (! array_key_exists('feature_flags', $state)) {
            return;
        }

        app(UserFeatureFlagService::class)->syncFeatureOverrides(
            $this->record,
            array_values(array_intersect($state['feature_flags'] ?? [], UserForm::getExperimentalFeatureKeys($state['role'] ?? $this->record->role))),
            $state['role'] ?? $this->record->role,
            true,
        );
    }
}
