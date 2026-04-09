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
use Filament\Schemas\Schema;

final class OnboardingFeatureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Feature Overview')
                    ->description('Configure how this feature onboarding appears in the portal.')
                    ->schema([
                        TextInput::make('feature_key')
                            ->label('Feature Key')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Matches the Pennant feature flag name.'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('audience')
                            ->options([
                                'student' => 'Student',
                                'faculty' => 'Faculty',
                                'all' => 'All Users',
                            ])
                            ->required(),
                        Textarea::make('summary')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('badge')
                            ->maxLength(255)
                            ->helperText('Short label shown near the onboarding title.'),
                        TextInput::make('accent')
                            ->maxLength(255)
                            ->placeholder('text-primary')
                            ->helperText('Tailwind class used for accent color.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Call To Action')
                    ->schema([
                        TextInput::make('cta_label')
                            ->label('CTA Label')
                            ->maxLength(255),
                        TextInput::make('cta_url')
                            ->label('CTA URL')
                            ->regex('/^(\/[\w\-\/]*|https?:\/\/.+)$/')
                            ->maxLength(255)
                            ->helperText('Relative path (e.g., /faculty/action-center) or full URL'),
                    ])
                    ->columns(2),

                Section::make('Onboarding Steps')
                    ->description('Add steps with copy, highlights, and preview images.')
                    ->schema([
                        Builder::make('steps')
                            ->minItems(1)
                            ->blocks([
                                Block::make('step')
                                    ->schema([
                                        TextInput::make('title')
                                            ->required()
                                            ->maxLength(255),
                                        Textarea::make('summary')
                                            ->rows(2)
                                            ->required(),
                                        TextInput::make('badge')
                                            ->maxLength(255),
                                        TextInput::make('accent')
                                            ->placeholder('text-primary')
                                            ->maxLength(255),
                                        TextInput::make('icon')
                                            ->placeholder('sparkles')
                                            ->helperText('Lucide icon name, e.g. sparkles, calendar-days.'),
                                        FileUpload::make('image')
                                            ->label('Preview Image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('onboarding')
                                            ->visibility('public')
                                            ->maxSize(2048),
                                        Fieldset::make('Highlights')
                                            ->schema([
                                                TextInput::make('highlights.0')
                                                    ->label('Highlight 1')
                                                    ->required(),
                                                TextInput::make('highlights.1')
                                                    ->label('Highlight 2'),
                                                TextInput::make('highlights.2')
                                                    ->label('Highlight 3'),
                                            ])
                                            ->columns(1),
                                        Fieldset::make('Stats')
                                            ->schema([
                                                TextInput::make('stats.0.label')
                                                    ->label('Stat 1 Label')
                                                    ->required(),
                                                TextInput::make('stats.0.value')
                                                    ->label('Stat 1 Value')
                                                    ->required(),
                                                TextInput::make('stats.1.label')
                                                    ->label('Stat 2 Label'),
                                                TextInput::make('stats.1.value')
                                                    ->label('Stat 2 Value'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columns(2),
                            ])
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
