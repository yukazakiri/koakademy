<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Services\UserFeatureFlagService;
use Exception;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

final class UserForm
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(function ($livewire): array {
                $isCreate = $livewire instanceof CreateRecord;

                return self::getFormSchema($isCreate);
            });
    }

    /**
     * @return array<string, string>
     */
    public static function getExperimentalFeatureOptions(UserRole|string|null $role = null): array
    {
        return self::featureFlagService()->featureOptionsForRole($role);
    }

    /**
     * @return array<int, string>
     */
    public static function getExperimentalFeatureKeys(UserRole|string|null $role = null): array
    {
        return self::featureFlagService()->featureKeysForRole($role);
    }

    /**
     * Get the form schema with conditional fields for create/edit modes.
     *
     * @return array<int, Section>
     */
    private static function getFormSchema(bool $isCreate): array
    {
        return [
            Section::make('Profile')
                ->description('User avatar and basic identification.')
                ->columns(2)
                ->schema([
                    FileUpload::make('avatar_url')
                        ->label('Profile Photo')
                        ->image()
                        ->imageEditor()
                        ->directory('avatars')
                        ->visibility('public')
                        ->avatar()
                        ->circleCropper()
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])
                        ->maxSize(2048)
                        ->helperText('Upload a profile photo (max 2MB)')
                        ->columnSpan(1),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Full name')
                        ->columnSpan(1),
                ]),

            Section::make('Contact Information')
                ->columns(2)
                ->schema([
                    TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('user@example.com'),
                    ColorPicker::make('theme_color')
                        ->label('Theme Color')
                        ->helperText('Personal theme preference'),
                ]),

            Section::make('Organizational Assignment')
                ->description('Assign user to organizational units.')
                ->columns(2)
                ->schema([
                    Select::make('school_id')
                        ->label('School / College')
                        ->relationship(
                            name: 'school',
                            titleAttribute: 'name',
                        )
                        ->searchable()
                        ->preload()
                        ->placeholder('Select a school')
                        ->live()
                        ->afterStateUpdated(function (callable $set): void {
                            $set('department_id', null);
                        })
                        ->helperText('Assign user to a specific school/college'),

                    TextInput::make('faculty_id_number')
                        ->label('Faculty ID Number')
                        ->maxLength(255)
                        ->placeholder('For faculty members')
                        ->helperText('Link to faculty record if applicable'),
                    TextInput::make('record_id')
                        ->label('External Record ID')
                        ->maxLength(255)
                        ->placeholder('External system ID')
                        ->helperText('For linking to external systems'),
                ]),

            Section::make('Access & Permissions')
                ->description('Configure user access levels and permissions.')
                ->columns(2)
                ->schema([
                    Select::make('role')
                        ->label('User Role')
                        ->options(fn (): array => self::getAvailableRoles())
                        ->required()
                        ->default(UserRole::User->value)
                        ->native(false)
                        ->live()
                        ->searchable()
                        ->helperText('Primary role determining access level'),
                    CheckboxList::make('roles')
                        ->label('Additional Permissions')
                        ->relationship(
                            name: 'roles',
                            titleAttribute: 'name',
                        )
                        ->searchable()
                        ->columns(2)
                        ->helperText('Spatie permission roles for fine-grained access'),
                ]),

            Section::make('Feature Flags')
                ->description('Control experimental feature access for this user.')
                ->schema([
                    CheckboxList::make('feature_flags')
                        ->label('Experimental Features')
                        ->options(fn (Get $get, $record): array => self::getExperimentalFeatureOptions($get('role') ?? $record?->role))
                        ->columns(2)
                        ->afterStateHydrated(function (
                            CheckboxList $component,
                            $state,
                            $record
                        ): void {
                            if (! $record) {
                                return;
                            }

                            $component->state(self::featureFlagService()->selectedFeatureKeysForUser($record, $record->role));
                        })
                        ->dehydrated(false)
                        ->hidden(fn (Get $get, $record): bool => self::getExperimentalFeatureOptions($get('role') ?? $record?->role) === []),
                ]),

            Section::make('Security')
                ->description($isCreate ? 'Set initial password.' : 'Update password (leave empty to keep current).')
                ->columns(2)
                ->schema([
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->required(fn (): bool => $isCreate)
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->minLength(8)
                        ->confirmed()
                        ->placeholder($isCreate ? 'Enter password' : 'Leave empty to keep current'),
                    TextInput::make('password_confirmation')
                        ->password()
                        ->revealable()
                        ->requiredWith('password')
                        ->dehydrated(false)
                        ->placeholder('Confirm password'),
                ]),
        ];
    }

    /**
     * Get available roles based on current user's authority level.
     *
     * @return array<string, string>
     */
    private static function getAvailableRoles(): array
    {
        /** @var \App\Models\User|null $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser || ! $currentUser->role) {
            return [UserRole::User->value => UserRole::User->getLabel()];
        }

        // Get roles this user can manage
        $manageableRoles = $currentUser->role->getManageableRoles();

        $roles = [];
        foreach ($manageableRoles as $role) {
            $roles[$role->value] = $role->getLabel();
        }

        // If no manageable roles, at least allow User role
        if ($roles === []) {
            $roles[UserRole::User->value] = UserRole::User->getLabel();
        }

        return $roles;
    }

    private static function featureFlagService(): UserFeatureFlagService
    {
        return app(UserFeatureFlagService::class);
    }
}
