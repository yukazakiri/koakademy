<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Resources;

use App\Filament\Plugins\PennantManager\Models\Feature;
use App\Filament\Plugins\PennantManager\Resources\FeatureResource\Pages;
use App\Filament\Plugins\PennantManager\Support\FeatureValue;
use App\Filament\Plugins\PennantManager\Support\ScopeConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature as PennantFeature;

final class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-flag';

    public static function getNavigationLabel(): string
    {
        return 'Features';
    }

    public static function canAccess(): bool
    {
        return Schema::hasTable('features');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('pennant-manager.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('scope', '__laravel_null');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('specific_scopes')
                    ->label('Active Overrides')
                    ->getStateUsing(function ($record) {
                        return DB::table('features')
                            ->where('name', $record->name)
                            ->where('scope', '!=', '__laravel_null')
                            ->count();
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => $state.' scoped'),

                TextColumn::make('percentage')
                    ->label('Rollout')
                    ->getStateUsing(function ($record) {
                        $data = FeatureValue::decode((string) $record->value);

                        return isset($data['percentage']) ? $data['percentage'].'%' : null;
                    })
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),

                ToggleColumn::make('value')
                    ->label('Global Active')
                    ->getStateUsing(fn ($record) => FeatureValue::isActive((string) $record->value))
                    ->updateStateUsing(function ($record, bool $state) {
                        $current = FeatureValue::decode((string) $record->value);
                        $current['enabled'] = $state;

                        DB::table('features')
                            ->where('id', $record->id)
                            ->update(['value' => json_encode($current), 'updated_at' => now()]);

                        PennantFeature::flushCache();
                    }),
            ])
            ->actions([
                Action::make('manageScopes')
                    ->label('Manage Scopes')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->url(fn ($record) => FeatureResource::getUrl('scopes', ['record' => $record->id])),

                Action::make('settings')
                    ->label('Settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->fillForm(function ($record) {
                        $data = FeatureValue::decode($record->getRawOriginal('value') ?? 'false');

                        return [
                            'activate_at' => $data['activate_at'] ?? null,
                            'rich_value' => $data['rich_value'] ?? null,
                            'percentage' => $data['percentage'] ?? null,
                            'scope_type' => $data['scope_type'] ?? null,
                        ];
                    })
                    ->form([
                        Section::make('Schedule')
                            ->description('Automatically activate this feature at a specific date and time.')
                            ->icon('heroicon-o-clock')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                DateTimePicker::make('activate_at')
                                    ->label('Activate at')
                                    ->nullable(),
                            ]),

                        Section::make('Variant Value')
                            ->description('Return a custom value instead of true/false - read with Feature::value(\'name\').')
                            ->icon('heroicon-o-swatch')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextInput::make('rich_value')
                                    ->label('Value')
                                    ->placeholder('e.g. beta, v2, dark-theme')
                                    ->nullable(),
                            ]),

                        Section::make('Percentage Rollout')
                            ->description('Randomly activate for a percentage of users.')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Select::make('scope_type')
                                    ->label('Model')
                                    ->options(fn () => ScopeConfig::modelOptions())
                                    ->nullable(),

                                TextInput::make('percentage')
                                    ->label('Percentage')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->nullable(),

                                Toggle::make('rollout_now')
                                    ->label('Apply immediately')
                                    ->helperText('Turn off to apply the rollout when the scheduled date arrives instead.')
                                    ->default(true),
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        $current = FeatureValue::decode($record->getRawOriginal('value') ?? 'false');

                        // Schedule
                        if (empty($data['activate_at'])) {
                            unset($current['activate_at']);
                        } else {
                            $current['activate_at'] = $data['activate_at'];
                            $current['enabled'] = false;
                        }

                        // Variant
                        if (empty($data['rich_value'])) {
                            unset($current['rich_value']);
                        } else {
                            $current['rich_value'] = $data['rich_value'];
                        }

                        // Rollout
                        if (! empty($data['percentage']) && ! empty($data['scope_type'])) {
                            $modelClass = ScopeConfig::modelClass($data['scope_type']);
                            $percentage = (int) $data['percentage'];

                            // Always store these so the scheduled command can use them if needed
                            $current['percentage'] = $percentage;
                            $current['scope_type'] = $data['scope_type'];

                            $applyNow = $data['rollout_now'] ?? true;

                            if ($applyNow) {
                                $existingIds = DB::table('features')
                                    ->where('name', $record->name)
                                    ->where('scope', '!=', '__laravel_null')
                                    ->get()
                                    ->map(fn ($f) => explode('|', $f->scope)[1] ?? null)
                                    ->filter()
                                    ->toArray();

                                $ids = $modelClass::whereNotIn('id', $existingIds)
                                    ->pluck('id')
                                    ->shuffle()
                                    ->take((int) ceil($modelClass::count() * $percentage / 100));

                                DB::transaction(function () use ($modelClass, $ids, $record, $percentage) {
                                    foreach ($ids as $id) {
                                        DB::table('features')->updateOrInsert(
                                            ['name' => $record->name, 'scope' => $modelClass.'|'.$id],
                                            [
                                                'value' => FeatureValue::encode(
                                                    enabled: true,
                                                    meta: [
                                                        'source' => 'rollout',
                                                        'percentage' => $percentage,
                                                        'activated_at' => now()->toDateTimeString(),
                                                    ]
                                                ),
                                                'created_at' => now(),
                                                'updated_at' => now(),
                                            ]
                                        );
                                    }
                                });
                            }
                        } else {
                            unset($current['percentage'], $current['scope_type']);
                        }

                        DB::table('features')
                            ->where('id', $record->id)
                            ->update(['value' => json_encode($current), 'updated_at' => now()]);

                        PennantFeature::flushCache();

                        Notification::make()->title('Settings saved')->success()->send();
                    })
                    ->modalWidth('lg'),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        DB::table('features')
                            ->where('name', $record->name)
                            ->delete();

                        PennantFeature::purge($record->name);

                        Notification::make()
                            ->title('Feature deleted successfully!')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeatures::route('/'),
            'scopes' => Pages\ManageScopes::route('/{record}/scopes'),
        ];
    }
}
