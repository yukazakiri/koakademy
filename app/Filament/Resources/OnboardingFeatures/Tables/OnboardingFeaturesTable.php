<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures\Tables;

use App\Features\Onboarding\FeatureClassRegistry;
use App\Filament\Resources\OnboardingFeatures\OnboardingFeatureResource;
use App\Models\OnboardingFeature;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Pennant\Feature;

final class OnboardingFeaturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByDesc('is_active')->orderBy('name'))
            ->striped()
            ->columns([
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('gray')
                    ->toggleable()
                    ->afterStateUpdated(function (OnboardingFeature $record, bool $state): void {
                        if ($state) {
                            OnboardingFeatureResource::activateFeature($record->feature_key);
                        } else {
                            OnboardingFeatureResource::deactivateFeature($record->feature_key);
                        }
                    }),
                TextColumn::make('name')
                    ->label('Feature')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (?OnboardingFeature $record): ?string => $record?->summary),
                TextColumn::make('feature_key')
                    ->label('Key')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Feature key copied')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->limit(30),
                TextColumn::make('audience')
                    ->label('Audience')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'student' => 'info',
                        'faculty' => 'purple',
                        'all' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'student' => 'Students',
                        'faculty' => 'Faculty',
                        'all' => 'Everyone',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('pennant_type')
                    ->label('Pennant')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?OnboardingFeature $record): ?string => FeatureClassRegistry::classForKey($record?->feature_key ?? '') ? 'Class' : null)
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('pennant_global_state')
                    ->label('Global')
                    ->badge()
                    ->formatStateUsing(fn (?OnboardingFeature $record): string => ($record && self::resolveGlobalState($record)) ? 'On' : 'Default')
                    ->color(fn (?OnboardingFeature $record): string => ($record && self::resolveGlobalState($record)) ? 'success' : 'gray')
                    ->toggleable()
                    ->visible(fn (?OnboardingFeature $record): bool => $record !== null && FeatureClassRegistry::classForKey($record->feature_key) !== null),
                TextColumn::make('pennant_user_overrides_count')
                    ->label('Overrides')
                    ->numeric()
                    ->alignEnd()
                    ->formatStateUsing(fn (?OnboardingFeature $record): int => $record ? self::resolveOverrideCount($record) : 0)
                    ->color(fn (?OnboardingFeature $record): string => ($record && self::resolveOverrideCount($record) > 0) ? 'purple' : 'gray')
                    ->badge()
                    ->toggleable()
                    ->visible(fn (?OnboardingFeature $record): bool => $record !== null && FeatureClassRegistry::classForKey($record->feature_key) !== null),
                TextColumn::make('steps_count')
                    ->label('Steps')
                    ->numeric()
                    ->alignEnd()
                    ->formatStateUsing(fn (?OnboardingFeature $record): int => $record ? count(is_array($record->steps) ? $record->steps : []) : 0)
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('audience')
                    ->label('Audience')
                    ->options([
                        'student' => 'Students',
                        'faculty' => 'Faculty',
                        'all' => 'Everyone',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->where('is_active', true),
                        'inactive' => $query->where('is_active', false),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('manage_overrides')
                    ->label('User overrides')
                    ->icon(Heroicon::UserCircle)
                    ->visible(fn (OnboardingFeature $record): bool => FeatureClassRegistry::classForKey($record->feature_key) !== null)
                    ->modalHeading(fn (OnboardingFeature $record): string => "User Overrides — {$record->name}")
                    ->modalDescription('Manage per-user feature flag overrides. Users listed here have explicit overrides that bypass the default resolution.')
                    ->modalIcon(Heroicon::UserCircle)
                    ->schema(fn (OnboardingFeature $record) => self::getOverridesSchema($record))
                    ->modalFooterActions(fn (OnboardingFeature $record) => self::getOverridesFooterActions($record))
                    ->modalWidth('lg')
                    ->slideOver(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon(Heroicon::Eye)
                    ->modalHeading(fn (OnboardingFeature $record): string => $record->name)
                    ->modalDescription(fn (OnboardingFeature $record): ?string => $record->summary)
                    ->modalContent(fn (OnboardingFeature $record) => self::getPreviewContent($record))
                    ->modalWidth('xl')
                    ->slideOver(),
                EditAction::make()
                    ->icon(Heroicon::PencilSquare),
                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->before(function (OnboardingFeature $record): void {
                        OnboardingFeatureResource::deactivateFeature($record->feature_key);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $records->each(fn (OnboardingFeature $record) => OnboardingFeatureResource::deactivateFeature($record->feature_key));
                        }),
                ]),
            ]);
    }

    /**
     * Resolve the global activation state for a feature.
     */
    private static function resolveGlobalState(OnboardingFeature $record): bool
    {
        $featureClass = FeatureClassRegistry::classForKey($record->feature_key);

        if ($featureClass === null) {
            return false;
        }

        return OnboardingFeatureResource::isGloballyActivated($featureClass);
    }

    /**
     * Resolve the user override count for a feature.
     */
    private static function resolveOverrideCount(OnboardingFeature $record): int
    {
        $featureClass = FeatureClassRegistry::classForKey($record->feature_key);

        if ($featureClass === null) {
            return 0;
        }

        return OnboardingFeatureResource::getUserOverrideCount($featureClass);
    }

    /**
     * Get the form schema for the user overrides modal.
     *
     * @return array<int, mixed>
     */
    private static function getOverridesSchema(OnboardingFeature $record): array
    {
        return [
            TextInput::make('user_id_input')
                ->label('Add user override by ID')
                ->numeric()
                ->integer()
                ->placeholder('Enter user ID')
                ->helperText('Enter a user ID and click "Activate for User" to add an override.'),
        ];
    }

    /**
     * Get the footer actions for the user overrides modal.
     *
     * @return array<int, mixed>
     */
    private static function getOverridesFooterActions(OnboardingFeature $record): array
    {
        $featureClass = FeatureClassRegistry::classForKey($record->feature_key);

        return [
            Action::make('activate_for_user')
                ->label('Activate for User')
                ->icon(Heroicon::UserPlus)
                ->color('success')
                ->action(function (array $data, OnboardingFeature $record) use ($featureClass): void {
                    $featureRef = $featureClass ?? $record->feature_key;
                    $user = User::find($data['user_id_input'] ?? null);

                    if ($user === null) {
                        \Filament\Notifications\Notification::make()
                            ->title('User not found')
                            ->danger()
                            ->send();

                        return;
                    }

                    Feature::for($user)->activate($featureRef);

                    \Filament\Notifications\Notification::make()
                        ->title("Feature activated for {$user->name}")
                        ->success()
                        ->send();
                }),
            Action::make('purge_overrides')
                ->label('Purge All Overrides')
                ->icon(Heroicon::Trash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Purge all per-user overrides?')
                ->modalDescription('This will reset all per-user overrides back to the default resolution. This cannot be undone.')
                ->visible(fn (): bool => self::resolveOverrideCount($record) > 0)
                ->action(function () use ($featureClass): void {
                    $featureRef = $featureClass ?? $record->feature_key;
                    Feature::forget($featureRef);

                    \Filament\Notifications\Notification::make()
                        ->title('All per-user overrides purged')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Get the preview modal content for a feature.
     *
     * @return \Illuminate\Contracts\View\View
     */
    private static function getPreviewContent(OnboardingFeature $record)
    {
        $featureClass = FeatureClassRegistry::classForKey($record->feature_key);
        $steps = is_array($record->steps) ? $record->steps : [];
        $overrideCount = self::resolveOverrideCount($record);
        $globalState = self::resolveGlobalState($record);

        return view('filament.resources.onboarding-features.preview', [
            'feature' => $record,
            'featureClass' => $featureClass,
            'steps' => $steps,
            'overrideCount' => $overrideCount,
            'globalState' => $globalState,
        ]);
    }
}
