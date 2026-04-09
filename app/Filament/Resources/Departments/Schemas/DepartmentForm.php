<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departments\Schemas;

use Exception;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class DepartmentForm
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
                        Select::make('school_id')
                            ->label('School')
                            ->relationship('school', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('School Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('code')
                                    ->label('School Code')
                                    ->required()
                                    ->maxLength(10)
                                    ->rules(['alpha_num']),
                            ])
                            ->helperText('Select the school this department belongs to'),

                        TextInput::make('name')
                            ->label('Department Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Computer Science')
                            ->helperText('The full name of the department'),

                        TextInput::make('code')
                            ->label('Department Code')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('e.g., CS')
                            ->helperText('Short abbreviation for the department')
                            ->rules(['alpha_num'])
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('code', mb_strtoupper($state))),

                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->rows(3)
                            ->placeholder('Brief description of the department\'s mission and programs')
                            ->helperText('Optional description of the department\'s purpose and offerings'),
                    ]),

                Section::make('Administrative Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('head_name')
                            ->label('Department Head')
                            ->maxLength(255)
                            ->placeholder('Dr. John Doe')
                            ->helperText('Current head of the department'),

                        TextInput::make('head_email')
                            ->label('Head Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('head@university.edu')
                            ->helperText('Official email address of the department head'),
                    ]),

                Section::make('Contact Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('location')
                            ->label('Location')
                            ->maxLength(255)
                            ->placeholder('Room 101, Building A')
                            ->helperText('Physical location or room number'),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('+63 2 123-4567')
                            ->helperText('Department contact phone number'),

                        TextInput::make('email')
                            ->label('Department Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('department@university.edu')
                            ->helperText('Official department email address')
                            ->columnSpanFull(),
                    ]),

                Section::make('Settings')
                    ->columns(1)
                    ->schema([
                        Checkbox::make('is_active')
                            ->label('Active Department')
                            ->helperText('Inactive departments are hidden from most parts of the system')
                            ->default(true),
                    ]),

                Section::make('Additional Information')
                    ->columns(1)
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->helperText('Additional flexible data storage (e.g., faculty_count, student_count)')
                            ->addable()
                            ->deletable()
                            ->reorderable(),
                    ])
                    ->collapsed(),
            ]);
    }
}
