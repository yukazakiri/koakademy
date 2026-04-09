<?php

declare(strict_types=1);

namespace App\Filament\Resources\Schools\Schemas;

use Exception;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SchoolForm
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('School Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., School of Information Technology')
                            ->helperText('The full name of the school or college'),

                        TextInput::make('code')
                            ->label('School Code')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., SIT')
                            ->helperText('Short abbreviation for the school')
                            ->rules(['alpha_num'])
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('code', mb_strtoupper($state))),

                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->rows(3)
                            ->placeholder('Brief description of the school\'s mission and programs')
                            ->helperText('Optional description of the school\'s purpose and offerings'),
                    ]),

                Section::make('Administrative Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('dean_name')
                            ->label('Dean Name')
                            ->maxLength(255)
                            ->placeholder('Dr. John Doe')
                            ->helperText('Current dean or head of the school'),

                        TextInput::make('dean_email')
                            ->label('Dean Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('dean@university.edu')
                            ->helperText('Official email address of the dean'),
                    ]),

                Section::make('Contact Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('location')
                            ->label('Location')
                            ->maxLength(255)
                            ->placeholder('Main Campus Building A')
                            ->helperText('Physical location or building name'),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('+63 2 123-4567')
                            ->helperText('Main contact phone number'),

                        TextInput::make('email')
                            ->label('School Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('school@university.edu')
                            ->helperText('Official school email address')
                            ->columnSpanFull(),
                    ]),

                Section::make('Settings')
                    ->columns(1)
                    ->schema([
                        Checkbox::make('is_active')
                            ->label('Active School')
                            ->helperText('Inactive schools are hidden from most parts of the system')
                            ->default(true),
                    ]),

                Section::make('Additional Information')
                    ->columns(1)
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->helperText('Additional flexible data storage (e.g., established_year, accreditation_status)')
                            ->addable()
                            ->deletable()
                            ->reorderable(),
                    ])
                    ->collapsed(),
            ]);
    }
}
