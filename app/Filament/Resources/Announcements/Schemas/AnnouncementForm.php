<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Announcement Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (callable $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),

                        RichEditor::make('content')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Settings')
                    ->schema([
                        Select::make('type')
                            ->options([
                                'info' => 'Information',
                                'warning' => 'Warning',
                                'important' => 'Important',
                            ])
                            ->default('info')
                            ->required(),

                        Select::make('priority')
                            ->options([
                                'low' => 'Low',
                                'normal' => 'Normal',
                                'high' => 'High',
                            ])
                            ->default('normal')
                            ->required(),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),

                        Toggle::make('is_global')
                            ->label('Global Announcement')
                            ->helperText('Show to all users on dashboard')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Scheduling')
                    ->schema([
                        DateTimePicker::make('published_at')
                            ->label('Publish At')
                            ->helperText('Leave empty to publish immediately'),

                        DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->helperText('Leave empty for no expiration'),
                    ])
                    ->columns(2),
            ]);
    }
}
