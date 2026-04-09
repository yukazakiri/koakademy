<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Schemas;

use Exception;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class EventForm
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        MarkdownEditor::make('description')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'link',
                                'bulletList',
                                'orderedList',
                            ]),

                        Select::make('type')
                            ->required()
                            ->options([
                                'event' => 'General Event',
                                'academic_calendar' => 'Academic Calendar',
                                'resource_booking' => 'Resource Booking',
                            ])
                            ->default('event'),

                        Select::make('category')
                            ->options([
                                'academic' => 'Academic',
                                'administrative' => 'Administrative',
                                'extracurricular' => 'Extracurricular',
                                'social' => 'Social',
                                'sports' => 'Sports',
                                'cultural' => 'Cultural',
                                'conference' => 'Conference',
                                'workshop' => 'Workshop',
                                'meeting' => 'Meeting',
                                'other' => 'Other',
                            ])
                            ->searchable(),

                        TextInput::make('location')
                            ->maxLength(255)
                            ->placeholder('Enter event location'),
                    ]),

                Section::make('Date & Time')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('start_datetime')
                            ->label('Start Date & Time')
                            ->required()
                            ->native(false)
                            ->displayFormat('M j, Y g:i A')
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, Get $get): void {
                                // Auto-set end datetime to 1 hour later if not set
                                if ($state && ! $get('end_datetime')) {
                                    $set('end_datetime', \Carbon\Carbon::parse($state)->addHour());
                                }
                            }),

                        DateTimePicker::make('end_datetime')
                            ->label('End Date & Time')
                            ->required()
                            ->native(false)
                            ->displayFormat('M j, Y g:i A')
                            ->after('start_datetime'),

                        Toggle::make('is_all_day')
                            ->label('All Day Event')
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state): void {
                                if ($state) {
                                    // Set times to start and end of day
                                    $set('start_datetime', now()->startOfDay());
                                    $set('end_datetime', now()->endOfDay());
                                }
                            }),
                    ]),

                Section::make('Recurrence')
                    ->columns(2)
                    ->schema([
                        Select::make('recurrence_type')
                            ->label('Repeat')
                            ->options([
                                'none' => 'No Repeat',
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->default('none')
                            ->live(),

                        DateTimePicker::make('recurrence_end_date')
                            ->label('Repeat Until')
                            ->native(false)
                            ->displayFormat('M j, Y')
                            ->visible(fn (Get $get): bool => $get('recurrence_type') !== 'none'),

                        KeyValue::make('recurrence_data')
                            ->label('Recurrence Rules')
                            ->keyLabel('Rule')
                            ->valueLabel('Value')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('recurrence_type') !== 'none')
                            ->helperText('Additional rules for recurrence (e.g., interval: 2 for every 2 weeks)'),
                    ]),

                Section::make('RSVP & Attendance')
                    ->columns(2)
                    ->schema([
                        Toggle::make('requires_rsvp')
                            ->label('Requires RSVP'),

                        TextInput::make('max_attendees')
                            ->label('Maximum Attendees')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Leave empty for unlimited'),

                        Toggle::make('allow_guests')
                            ->label('Allow Guests')
                            ->default(true),
                    ]),

                Section::make('Visibility & Status')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'cancelled' => 'Cancelled',
                                'postponed' => 'Postponed',
                                'completed' => 'Completed',
                            ])
                            ->default('active'),

                        Select::make('visibility')
                            ->required()
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'internal' => 'Internal Only',
                            ])
                            ->default('public'),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        KeyValue::make('custom_fields')
                            ->label('Custom Fields')
                            ->keyLabel('Field Name')
                            ->valueLabel('Field Value')
                            ->helperText('Add any custom information for this event'),

                        Textarea::make('notes')
                            ->label('Internal Notes')
                            ->rows(3)
                            ->placeholder('Internal notes (not visible to attendees)'),
                    ]),
            ]);
    }
}
