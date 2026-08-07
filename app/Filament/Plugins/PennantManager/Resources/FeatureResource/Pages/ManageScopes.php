<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Resources\FeatureResource\Pages;

use App\Filament\Plugins\PennantManager\Models\Feature;
use App\Filament\Plugins\PennantManager\Resources\FeatureResource;
use App\Filament\Plugins\PennantManager\Support\FeatureValue;
use App\Filament\Plugins\PennantManager\Support\ScopeConfig;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Laravel\Pennant\Feature as PennantFeature;

final class ManageScopes extends Page implements HasForms
{
    use InteractsWithForms;

    public Feature $record;

    public ?array $formData = [];

    protected static string $resource = FeatureResource::class;

    protected string $view = 'filament.plugins.pennant-manager.pages.manage-scopes';

    public function mount($record): void
    {
        $this->record = $record instanceof Feature ? $record : Feature::findOrFail($record);
        $models = config('pennant-manager.scope_models', ['User' => \App\Models\User::class]);
        $this->form->fill(['scope_type' => array_key_first($models), 'scope_ids' => []]);
    }

    public function getTitle(): string
    {
        return 'Manage Scopes: '.$this->record->name;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('scope_type')
                    ->options(fn () => ScopeConfig::modelOptions())
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set) {
                        $set('scope_ids', []);
                    }),

                Select::make('scope_ids')
                    ->label('Select Scopes')
                    ->multiple()
                    ->searchable()
                    ->key(fn (Get $get) => $get('scope_type') ?? 'default')
                    ->placeholder(fn (Get $get) => $get('scope_type') ? 'Search by '.ScopeConfig::searchColumn($get('scope_type')) : 'Select scope type first')
                    ->getSearchResultsUsing(function (string $search, Get $get) {
                        $type = $get('scope_type');

                        if (! $type) {
                            return [];
                        }

                        $modelClass = ScopeConfig::modelClass($type);
                        $searchColumn = ScopeConfig::searchColumn($type);
                        $labelColumn = ScopeConfig::labelColumn($type);

                        if (! $modelClass) {
                            return [];
                        }

                        return $modelClass::where($searchColumn, 'like', "%{$search}%")
                            ->orWhere($labelColumn, 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => isset($item->email)
                                    ? "{$item->{$labelColumn}} · {$item->email}"
                                    : $item->{$labelColumn},
                            ])
                            ->toArray();
                    })
                    ->getOptionLabelsUsing(function (array $values, Get $get) {
                        $type = $get('scope_type');

                        if (! $type) {
                            return [];
                        }

                        $modelClass = ScopeConfig::modelClass($type);
                        $labelColumn = ScopeConfig::labelColumn($type);

                        if (! $modelClass) {
                            return [];
                        }

                        return $modelClass::whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn ($item) => [
                                $item->id => isset($item->email)
                                    ? "{$item->{$labelColumn}} · {$item->email}"
                                    : $item->{$labelColumn},
                            ])
                            ->toArray();
                    }),

                Section::make('Bulk Import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Get $get) => ! empty($get('scope_type')))
                    ->schema([
                        Textarea::make('bulk_import_text')
                            ->label(fn (Get $get) => 'Paste '.(ScopeConfig::searchColumn($get('scope_type') ?? '') ?: 'values').' (one per line)')
                            ->placeholder("admin@example.com\nuser@example.com")
                            ->rows(4)
                            ->live(false),
                    ]),

                Section::make('Activate by Segment')
                    ->icon('heroicon-o-funnel')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Get $get) => ! empty($get('scope_type')))
                    ->footerActions([
                        Action::make('activateBySegment')
                            ->label('Activate Segment')
                            ->color('warning')
                            ->icon('heroicon-o-funnel')
                            ->action(fn () => $this->activateBySegment()),
                    ])
                    ->schema([
                        Select::make('segment_column_select')
                            ->label('Column')
                            ->options(fn (Get $get) => array_combine(
                                ScopeConfig::segmentColumns($get('scope_type') ?? ''),
                                ScopeConfig::segmentColumns($get('scope_type') ?? '')
                            ))
                            ->visible(fn (Get $get) => ! empty(ScopeConfig::segmentColumns($get('scope_type') ?? ''))),

                        TextInput::make('segment_column_free')
                            ->label('Column')
                            ->placeholder('e.g., plan, role, country')
                            ->helperText('Must be a valid column on the selected model.')
                            ->visible(fn (Get $get) => empty(ScopeConfig::segmentColumns($get('scope_type') ?? ''))),

                        TextInput::make('segment_value')
                            ->label('Value')
                            ->placeholder('e.g., pro, admin, US'),
                    ]),

            ])
            ->statePath('formData');
    }

    public function activateScope(): void
    {
        $data = $this->form->getState();
        $type = $data['scope_type'];

        $bulkText = mb_trim($data['bulk_import_text'] ?? '');

        if (! empty($bulkText)) {
            $lines = array_filter(array_map('trim', explode("\n", $bulkText)));

            $modelClass = ScopeConfig::modelClass($type);
            $searchColumn = ScopeConfig::searchColumn($type);

            if ($modelClass && ! empty($lines)) {
                $foundIds = $modelClass::whereIn($searchColumn, $lines)
                    ->pluck('id')
                    ->toArray();

                if (empty($foundIds)) {
                    Notification::make()
                        ->title('No matching records found for pasted values')
                        ->warning()
                        ->send();

                    return;
                }

                $data['scope_ids'] = array_unique(
                    array_merge($data['scope_ids'] ?? [], $foundIds)
                );

                Notification::make()
                    ->title('Matched '.count($foundIds).' of '.count($lines).' pasted values')
                    ->success()
                    ->send();
            }
        }

        $ids = $data['scope_ids'];

        $modelClass = ScopeConfig::modelClass($type);

        if ($modelClass && ! empty($ids)) {
            DB::transaction(function () use ($modelClass, $ids) {
                $records = $modelClass::whereIn('id', $ids)->get();

                foreach ($records as $recordModel) {
                    $scopeKey = $modelClass.'|'.$recordModel->id;

                    DB::table('features')->updateOrInsert(
                        ['name' => $this->record->name, 'scope' => $scopeKey],
                        [
                            'value' => FeatureValue::encode(
                                enabled: true,
                                meta: [
                                    'source' => 'manual',
                                    'activated_at' => now()->toDateTimeString(),
                                ]
                            ),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });

            PennantFeature::flushCache();

            Notification::make()
                ->title(count($ids).' scopes activated successfully!')
                ->success()
                ->send();

            $this->form->fill(['scope_type' => $type, 'scope_ids' => []]);
            $this->dispatch('$refresh');
        }
    }

    public function removeScope($id): void
    {
        $feature = DB::table('features')->find($id);

        if ($feature) {
            $parts = explode('|', $feature->scope);

            if (count($parts) === 2) {
                $modelClass = $parts[0];
                $model = $modelClass::find($parts[1]);

                DB::table('features')->where('id', $id)->delete();

                if ($model) {
                    PennantFeature::for($model)->forget($feature->name);
                }

                Notification::make()
                    ->title('Scope removed successfully!')
                    ->success()
                    ->send();

                $this->dispatch('$refresh');
            }
        }
    }

    public function activateBySegment(): void
    {
        $data = $this->form->getState();
        $type = $data['scope_type'];
        $column = mb_trim($data['segment_column_select'] ?? $data['segment_column_free'] ?? '');
        $value = mb_trim($data['segment_value'] ?? '');

        if (! $column || ! $value) {
            Notification::make()->title('Column and value are required')->warning()->send();

            return;
        }

        $modelClass = ScopeConfig::modelClass($type);

        if (! $modelClass || ! SchemaFacade::hasColumn((new $modelClass)->getTable(), $column)) {
            Notification::make()->title("Column '{$column}' not found on {$type}")->danger()->send();

            return;
        }

        $records = $modelClass::where($column, $value)->get();

        if ($records->isEmpty()) {
            Notification::make()->title('No matching records found')->warning()->send();

            return;
        }

        DB::transaction(function () use ($modelClass, $records, $column, $value) {
            foreach ($records as $model) {
                $scopeKey = $modelClass.'|'.$model->id;

                DB::table('features')->updateOrInsert(
                    ['name' => $this->record->name, 'scope' => $scopeKey],
                    [
                        'value' => FeatureValue::encode(
                            enabled: true,
                            meta: [
                                'source' => 'segment',
                                'segment_column' => $column,
                                'segment_value' => $value,
                                'activated_at' => now()->toDateTimeString(),
                            ]
                        ),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        PennantFeature::flushCache();

        Notification::make()
            ->title("Activated for {$records->count()} {$type}(s) where {$column} = {$value}")
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    protected function getViewData(): array
    {
        $scopes = Feature::where('name', $this->record->name)
            ->where('scope', '!=', '__laravel_null')
            ->get();

        $groupedScopes = [];

        foreach ($scopes as $scope) {
            $parts = explode('|', $scope->scope);

            if (count($parts) === 2) {
                $groupedScopes[$parts[0]][$parts[1]] = $scope;
            }
        }

        $activeScopes = [];

        foreach ($groupedScopes as $modelClass => $modelScopes) {
            if (class_exists($modelClass)) {
                $modelKey = null;
                $scopeModels = config('pennant-manager.scope_models', []);

                foreach ($scopeModels as $key => $config) {
                    $configModel = is_array($config) ? $config['model'] : $config;

                    if ($configModel === $modelClass) {
                        $modelKey = $key;
                        break;
                    }
                }

                $modelIds = array_keys($modelScopes);
                $records = $modelClass::whereIn('id', $modelIds)->get()->keyBy('id');

                foreach ($modelScopes as $modelId => $scope) {
                    $record = $records->get($modelId);

                    if ($record) {
                        $labelColumn = $modelKey ? ScopeConfig::labelColumn($modelKey) : null;
                        $labelValue = $labelColumn && isset($record->{$labelColumn}) ? $record->{$labelColumn} : null;
                        $emailValue = isset($record->email) ? $record->email : null;

                        if ($labelValue && $emailValue && $labelValue !== $emailValue) {
                            $displayName = $labelValue.' · '.$emailValue;
                        } elseif ($labelValue) {
                            $displayName = $labelValue;
                        } elseif ($emailValue) {
                            $displayName = $emailValue;
                        } else {
                            $displayName = "ID: {$record->id}";
                        }

                        $data = FeatureValue::decode((string) $scope->getRawOriginal('value'));
                        $meta = $data['meta'] ?? [];

                        $activeScopes[] = [
                            'id' => $scope->id,
                            'scope' => $scope->scope,
                            'user_name' => $displayName,
                            'type' => class_basename($modelClass),
                            'active' => FeatureValue::isActive((string) $scope->getRawOriginal('value')),
                            'source' => $meta['source'] ?? 'manual',
                            'segment' => isset($meta['segment_column'])
                                ? $meta['segment_column'].' = '.$meta['segment_value']
                                : null,
                            'activated_at' => $meta['activated_at'] ?? null,
                        ];
                    }
                }
            }
        }

        return [
            'activeScopes' => $activeScopes,
        ];
    }
}
