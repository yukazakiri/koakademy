<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class FacultyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(function ($livewire): array {
                $operation = $livewire instanceof CreateRecord ? 'create' : 'edit';

                return $operation === 'create'
                    ? self::getCreateFormSchema()
                    : self::getEditFormSchema();
            });
    }

    public static function getCreateFormSchema(): array
    {
        return [
            Section::make('Essential Information')
                ->description('Required information to create a faculty member.')
                ->schema([
                    TextInput::make('faculty_id_number')
                        ->label('Faculty ID Number')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->default(fn (): string => self::generateNextFacultyId())
                        ->helperText('Auto-generated based on the latest faculty ID (can be modified)'),
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter first name'),
                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter last name'),
                    TextInput::make('middle_name')
                        ->maxLength(255)
                        ->placeholder('Enter middle name (optional)'),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->placeholder('Enter email address'),
                ])->columns(2),
        ];
    }

    public static function getEditFormSchema(): array
    {
        return [
            Section::make('Essential Information')
                ->description('Faculty member information.')
                ->schema([
                    TextInput::make('faculty_id_number')
                        ->label('Faculty ID Number')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->placeholder('Enter faculty ID number'),
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter first name'),
                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter last name'),
                    TextInput::make('middle_name')
                        ->maxLength(255)
                        ->placeholder('Enter middle name (optional)'),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->placeholder('Enter email address'),
                ])->columns(2),

            Section::make('Additional Information')
                ->description('Optional faculty details.')
                ->schema([
                    TextInput::make('department')
                        ->maxLength(255)
                        ->placeholder('Enter department'),
                    Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'on_leave' => 'On Leave',
                        ])
                        ->default('active'),
                    Select::make('gender')
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                            'other' => 'Other',
                        ]),
                    DatePicker::make('birth_date')
                        ->label('Birth Date'),
                    TextInput::make('phone_number')
                        ->tel()
                        ->maxLength(255)
                        ->placeholder('Enter phone number'),
                    Textarea::make('office_hours')
                        ->rows(3)
                        ->placeholder('Enter office hours'),
                    Textarea::make('address_line1')
                        ->label('Address')
                        ->rows(3)
                        ->placeholder('Enter address'),
                    FileUpload::make('photo_url')
                        ->label('Profile Photo')
                        ->image()
                        ->imageEditor()
                        ->directory('faculty-photos')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])
                        ->maxSize(2048)
                        ->helperText('Upload a profile photo (PNG, JPG, or WEBP, max 2MB)'),
                ])->columns(2),

        ];
    }

    private static function generateNextFacultyId(): string
    {
        $latestFaculty = \App\Models\Faculty::query()
            ->orderByRaw('CAST(faculty_id_number AS INTEGER) DESC')
            ->first();

        if (! $latestFaculty || ! $latestFaculty->faculty_id_number) {
            return '1';
        }

        $latestId = (int) $latestFaculty->faculty_id_number;

        return (string) ($latestId + 1);
    }
}
