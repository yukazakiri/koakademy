<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Account;
use App\Models\Faculty;
use App\Models\ShsStudent;
use App\Models\Student;
use Closure;
use Exception;
// use Filament\Forms\Components\Grid;
// use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

final class AccountForm
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make()
                ->schema([
                    Section::make('Account Information')
                        ->description('Manage the basic account details')
                        ->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255)
                                ->autocomplete(false),

                            TextInput::make('username')
                                ->label('Username')
                                ->required()
                                ->unique(Account::class, 'username', ignoreRecord: true)
                                ->maxLength(255)
                                ->alphaDash()
                                ->autocomplete(false),

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->unique(Account::class, 'email', ignoreRecord: true)
                                ->maxLength(255)
                                ->autocomplete(false),

                            TextInput::make('phone')
                                ->label('Phone Number')
                                ->tel()
                                ->maxLength(20)
                                ->nullable(),

                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->nullable(fn (string $operation): bool => $operation === 'edit')
                                ->minLength(8)
                                ->rules([
                                    fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                        if (! preg_match('/[a-z]/', $value)) {
                                            $fail('The :attribute must contain at least one lowercase letter.');
                                        }
                                        if (! preg_match('/[A-Z]/', $value)) {
                                            $fail('The :attribute must contain at least one uppercase letter.');
                                        }
                                        if (! preg_match('/\d/', $value)) {
                                            $fail('The :attribute must contain at least one number.');
                                        }
                                        if (! preg_match('/[^a-zA-Z0-9]/', $value)) {
                                            $fail('The :attribute must contain at least one special character.');
                                        }
                                    },
                                ])
                                ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                                ->dehydrated(fn ($state): bool => filled($state))
                                ->autocomplete(false),

                            Select::make('role')
                                ->label('Role')
                                ->options([
                                    'admin' => 'Administrator',
                                    'student' => 'Student',
                                    'faculty' => 'Faculty',
                                    'staff' => 'Staff',
                                    'guest' => 'Guest',
                                ])
                                ->required()
                                ->native(false),

                            Toggle::make('is_active')
                                ->label('Active Status')
                                ->default(true)
                                ->helperText('Inactive accounts cannot log in to the portal'),
                        ])
                        ->columns(2),

                    Section::make('Person Linkage')
                        ->description('Link this account to a person record (optional)')
                        ->schema([
                            Select::make('person_type')
                                ->label('Person Type')
                                ->options([
                                    Student::class => 'Student',
                                    Faculty::class => 'Faculty',
                                    ShsStudent::class => 'SHS Student',
                                ])
                                ->reactive()
                                ->afterStateUpdated(fn ($state, callable $set) => $set('person_id', null))
                                ->nullable(),

                            TextInput::make('person_id')
                                ->label('Person ID')
                                ->numeric()
                                ->nullable()
                                ->visible(fn ($get): bool => filled($get('person_type')))
                                ->helperText(fn ($get): string => match ($get('person_type')) {
                                    Student::class => 'Enter the student ID from the students table',
                                    Faculty::class => 'Enter the faculty ID from the faculties table',
                                    ShsStudent::class => 'Enter the LRN from the SHS students table',
                                    default => 'Select a person type first',
                                }),
                        ])
                        ->columns(2),
                ]),
        ]);
    }
}
