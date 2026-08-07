<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Resources\FeatureResource\Pages;

use App\Filament\Plugins\PennantManager\Resources\FeatureResource;
use App\Filament\Plugins\PennantManager\Services\FeatureDiscovery;
use App\Filament\Plugins\PennantManager\Support\FeatureValue;
use App\Filament\Plugins\Widgets\PennantFeatureAdoptionWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

final class ListFeatures extends ListRecords
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            PennantFeatureAdoptionWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('discoverFeatures')
                ->label('Discover Features')
                ->icon('heroicon-o-magnifying-glass')
                ->color('info')
                ->form([
                    CheckboxList::make('features_to_import')
                        ->label('Found features not in database:')
                        ->options(function () {
                            $defined = FeatureDiscovery::discover();
                            $inDb = DB::table('features')->pluck('name')->toArray();
                            $missing = array_diff($defined, $inDb);

                            return array_combine($missing, $missing);
                        })
                        ->default(function () {
                            $defined = FeatureDiscovery::discover();
                            $inDb = DB::table('features')->pluck('name')->toArray();

                            return array_values(array_diff($defined, $inDb));
                        })
                        ->visible(function () {
                            $configFeatures = FeatureDiscovery::discover();
                            $dbFeatures = DB::table('features')->pluck('name')->toArray();

                            return count(array_diff($configFeatures, $dbFeatures)) > 0;
                        }),

                    Placeholder::make('all_good')
                        ->label('Up to date!')
                        ->content('All features defined in your config are already in the database.')
                        ->visible(function () {
                            $configFeatures = FeatureDiscovery::discover();
                            $dbFeatures = DB::table('features')->pluck('name')->toArray();

                            return count(array_diff($configFeatures, $dbFeatures)) === 0;
                        }),
                ])
                ->action(function (array $data, Component $livewire) {
                    if (empty($data['features_to_import'])) {
                        return;
                    }

                    foreach ($data['features_to_import'] as $featureName) {
                        DB::table('features')->updateOrInsert(
                            ['name' => $featureName, 'scope' => '__laravel_null'],
                            [
                                'value' => FeatureValue::encode(enabled: false),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }

                    Notification::make()
                        ->title('Features Imported Successfully!')
                        ->success()
                        ->send();

                    $livewire->dispatch('$refresh');
                })
                ->modalWidth('md'),

            Action::make('createFeature')
                ->label('Create Feature')
                ->icon('heroicon-o-plus')
                ->form([
                    TextInput::make('name')
                        ->label('Feature Name')
                        ->placeholder('e.g., new-dashboard')
                        ->required(),
                ])
                ->action(function (array $data, Component $livewire) {
                    DB::table('features')->insert([
                        'name' => $data['name'],
                        'scope' => '__laravel_null',
                        'value' => FeatureValue::encode(enabled: true),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Created!')
                        ->success()
                        ->send();

                    $livewire->dispatch('$refresh');
                })
                ->modalWidth('md')
                ->closeModalByClickingAway(false),
        ];
    }
}
