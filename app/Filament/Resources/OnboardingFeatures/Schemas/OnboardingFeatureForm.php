<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnboardingFeatures\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class OnboardingFeatureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Feature')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Overview')
                            ->icon(Heroicon::Sparkles)
                            ->schema([
                                Section::make('Feature Overview')
                                    ->description('Configure how this feature onboarding appears in the portal.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('feature_key')
                                            ->label('Feature Key')
                                            ->required()
                                            ->maxLength(100)
                                            ->unique(ignoreRecord: true)
                                            ->helperText('Matches the Pennant feature flag name.')
                                            ->placeholder('onboarding-faculty-toolkit'),
                                        TextInput::make('name')
                                            ->label('Display Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Faculty Toolkit'),
                                        Select::make('audience')
                                            ->label('Target Audience')
                                            ->options([
                                                'student' => 'Students',
                                                'faculty' => 'Faculty',
                                                'all' => 'Everyone',
                                            ])
                                            ->required(),
                                        TextInput::make('badge')
                                            ->label('Badge Label')
                                            ->maxLength(255)
                                            ->placeholder('New')
                                            ->helperText('Short label shown near the onboarding title.'),
                                        Textarea::make('summary')
                                            ->label('Summary')
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->placeholder('Brief description of what this feature does...'),
                                        TextInput::make('accent')
                                            ->label('Accent Color Class')
                                            ->maxLength(255)
                                            ->placeholder('text-primary')
                                            ->helperText('Tailwind class for accent styling.'),
                                    ]),
                            ]),
                        Tab::make('Call to Action')
                            ->icon(Heroicon::CursorArrowRays)
                            ->schema([
                                Section::make('Call To Action')
                                    ->description('Optional button to direct users after viewing the onboarding.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('cta_label')
                                            ->label('CTA Label')
                                            ->maxLength(255)
                                            ->placeholder('Get Started'),
                                        TextInput::make('cta_url')
                                            ->label('CTA URL')
                                            ->regex('/^(\/[\w\-\/]*|https?:\/\/.+)$/')
                                            ->maxLength(255)
                                            ->helperText('Relative path (e.g., /faculty/action-center) or full URL')
                                            ->placeholder('/faculty/action-center'),
                                    ]),
                            ]),
                        Tab::make('Steps')
                            ->icon(Heroicon::QueueList)
                            ->schema([
                                Section::make('Onboarding Steps')
                                    ->description('Add steps with copy, highlights, and preview images.')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->onColor('success'),
                                        Builder::make('steps')
                                            ->minItems(1)
                                            ->blocks([
                                                Block::make('step')
                                                    ->schema([
                                                        TextInput::make('title')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->columnSpanFull(),
                                                        Textarea::make('summary')
                                                            ->rows(2)
                                                            ->required()
                                                            ->columnSpanFull(),
                                                        TextInput::make('badge')
                                                            ->maxLength(255)
                                                            ->placeholder('Optional badge'),
                                                        TextInput::make('accent')
                                                            ->placeholder('text-primary')
                                                            ->maxLength(255),
                                                        TextInput::make('icon')
                                                            ->placeholder('sparkles')
                                                            ->helperText('Lucide icon name, e.g. sparkles, calendar-days.')
                                                            ->maxLength(255),
                                                        FileUpload::make('image')
                                                            ->label('Preview Image')
                                                            ->image()
                                                            ->disk('public')
                                                            ->directory('onboarding')
                                                            ->visibility('public')
                                                            ->maxSize(2048)
                                                            ->columnSpanFull(),
                                                        Fieldset::make('Highlights')
                                                            ->schema([
                                                                TextInput::make('highlights.0')
                                                                    ->label('Highlight 1'),
                                                                TextInput::make('highlights.1')
                                                                    ->label('Highlight 2'),
                                                                TextInput::make('highlights.2')
                                                                    ->label('Highlight 3'),
                                                            ])
                                                            ->columns(3)
                                                            ->columnSpanFull(),
                                                        Fieldset::make('Stats')
                                                            ->schema([
                                                                TextInput::make('stats.0.label')
                                                                    ->label('Stat 1 Label'),
                                                                TextInput::make('stats.0.value')
                                                                    ->label('Stat 1 Value'),
                                                                TextInput::make('stats.1.label')
                                                                    ->label('Stat 2 Label'),
                                                                TextInput::make('stats.1.value')
                                                                    ->label('Stat 2 Value'),
                                                            ])
                                                            ->columns(4)
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(2),
                                            ])
                                            ->collapsible()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
