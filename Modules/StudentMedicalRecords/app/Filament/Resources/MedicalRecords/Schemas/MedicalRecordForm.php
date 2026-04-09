<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\Schemas;

use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MedicalRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Main Information Section - Most Important
                Section::make('📋 Student Medical Record')
                    ->description('Basic information about the medical record')
                    ->schema([
                        Select::make('student_id')
                            ->label('👤 Student Name')
                            ->relationship('student', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Student $record): string => "{$record->full_name} (ID: {$record->student_id})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Search and select the student')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Select::make('record_type')
                                    ->label('📝 Type of Medical Record')
                                    ->options([
                                        'checkup' => '🏥 Regular Checkup',
                                        'vaccination' => '💉 Vaccination',
                                        'allergy' => '⚠️ Allergy/Allergic Reaction',
                                        'medication' => '💊 Medication/Prescription',
                                        'emergency' => '🚨 Emergency Visit',
                                        'dental' => '🦷 Dental Care',
                                        'vision' => '👁️ Vision/Eye Care',
                                        'mental_health' => '🧠 Mental Health',
                                        'laboratory' => '🧪 Lab Test Results',
                                        'surgery' => '🏥 Surgery/Procedure',
                                        'follow_up' => '🔄 Follow-up Visit',
                                    ])
                                    ->required()
                                    ->helperText('What type of medical care was provided?'),

                                TextInput::make('title')
                                    ->label('📋 Record Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Brief description (e.g., "Annual Checkup", "Flu Vaccination")'),
                            ])
                            ->columnSpanFull(),

                        DatePicker::make('visit_date')
                            ->label('📅 Visit Date')
                            ->required()
                            ->default(now())
                            ->helperText('When did this medical visit occur?')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Medical Information Section
                Section::make('🏥 Medical Information')
                    ->description('Details about the medical condition and treatment')
                    ->schema([
                        Textarea::make('description')
                            ->label('📝 What happened?')
                            ->rows(4)
                            ->helperText('Describe the student\'s condition, symptoms, or reason for visit')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Textarea::make('diagnosis')
                                    ->label('🩺 Diagnosis (if any)')
                                    ->rows(3)
                                    ->helperText('What did the doctor find or diagnose?'),

                                Textarea::make('treatment')
                                    ->label('💊 Treatment Given')
                                    ->rows(3)
                                    ->helperText('What treatment or care was provided?'),
                            ])
                            ->columnSpanFull(),

                        Textarea::make('prescription')
                            ->label('💊 Prescription/Medication')
                            ->rows(3)
                            ->helperText('Any medications prescribed? Include dosage and instructions')
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('📝 Additional Notes')
                            ->rows(3)
                            ->helperText('Any other important information or instructions')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Basic Health Measurements
                Section::make('📊 Basic Health Measurements')
                    ->description('Simple health measurements (optional)')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('height')
                                    ->label('📏 Height')
                                    ->numeric()
                                    ->suffix('cm')
                                    ->helperText('Student\'s height in centimeters'),

                                TextInput::make('weight')
                                    ->label('⚖️ Weight')
                                    ->numeric()
                                    ->suffix('kg')
                                    ->helperText('Student\'s weight in kilograms'),

                                TextInput::make('temperature')
                                    ->label('🌡️ Temperature')
                                    ->numeric()
                                    ->suffix('°C')
                                    ->helperText('Body temperature in Celsius'),
                            ])
                            ->columnSpan(2),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('blood_pressure_systolic')
                                    ->label('🩸 Blood Pressure (Top Number)')
                                    ->numeric()
                                    ->suffix('mmHg')
                                    ->helperText('Systolic pressure (higher number)'),

                                TextInput::make('blood_pressure_diastolic')
                                    ->label('🩸 Blood Pressure (Bottom Number)')
                                    ->numeric()
                                    ->suffix('mmHg')
                                    ->helperText('Diastolic pressure (lower number)'),
                            ]),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                // Follow-up and Status
                Section::make('📅 Follow-up & Status')
                    ->description('Important dates and current status')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->label('📊 Current Status')
                                    ->options([
                                        'active' => '✅ Active (ongoing treatment)',
                                        'resolved' => '✅ Resolved (fully recovered)',
                                        'ongoing' => '🔄 Ongoing (still being treated)',
                                        'cancelled' => '❌ Cancelled',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->helperText('Current status of this medical record'),

                                Select::make('priority')
                                    ->label('⚠️ Priority Level')
                                    ->options([
                                        'low' => '🟢 Low (routine care)',
                                        'normal' => '🟡 Normal (standard care)',
                                        'high' => '🟠 High (needs attention)',
                                        'urgent' => '🔴 Urgent (immediate attention)',
                                    ])
                                    ->default('normal')
                                    ->required()
                                    ->helperText('How urgent is this medical issue?'),

                                Toggle::make('is_confidential')
                                    ->label('🔒 Confidential Record')
                                    ->default(false)
                                    ->helperText('Check if this is a sensitive/confidential medical record'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('next_appointment')
                                    ->label('📅 Next Appointment')
                                    ->helperText('When is the next scheduled appointment?'),

                                DatePicker::make('follow_up_date')
                                    ->label('🔄 Follow-up Date')
                                    ->helperText('When should we follow up on this case?'),
                            ]),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                // Emergency Information (Collapsed by default)
                Section::make('🚨 Emergency Information')
                    ->description('Emergency contact notification (only for serious cases)')
                    ->schema([
                        Toggle::make('emergency_contact_notified')
                            ->label('📞 Emergency Contact Notified')
                            ->default(false)
                            ->helperText('Check if parents/guardians have been notified about this emergency')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columns(1),

                // Hidden fields for system use
                Hidden::make('created_by'),
                Hidden::make('updated_by'),
            ]);
    }
}
