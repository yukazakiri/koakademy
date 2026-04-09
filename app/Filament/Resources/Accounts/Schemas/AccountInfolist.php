<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Account;
use Exception;
// use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\IconEntry;
// use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AccountInfolist
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Group::make()
                ->schema([
                    Section::make('Account Information')
                        ->description('Basic account details')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Full Name')
                                ->weight('bold')
                                ->size('lg'),

                            TextEntry::make('username')
                                ->label('Username'),

                            TextEntry::make('email')
                                ->label('Email Address')
                                ->copyable()
                                ->copyMessage('Email copied to clipboard')
                                ->copyMessageDuration(1500),

                            TextEntry::make('phone')
                                ->label('Phone Number')
                                ->placeholder('Not set'),

                            TextEntry::make('role')
                                ->label('Role')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'admin' => 'danger',
                                    'faculty' => 'success',
                                    'staff' => 'warning',
                                    'student' => 'info',
                                    'guest' => 'gray',
                                    default => 'gray',
                                }),

                            IconEntry::make('is_active')
                                ->label('Active Status')
                                ->boolean()
                                ->trueIcon('heroicon-o-check-circle')
                                ->falseIcon('heroicon-o-x-circle')
                                ->trueColor('success')
                                ->falseColor('danger'),
                        ])
                        ->columns(2),

                    Section::make('Person Linkage')
                        ->description('Linked person record information')
                        ->schema([
                            TextEntry::make('person_type')
                                ->label('Person Type')
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    \App\Models\Student::class => 'Student',
                                    \App\Models\Faculty::class => 'Faculty',
                                    \App\Models\ShsStudent::class => 'SHS Student',
                                    default => 'Not linked',
                                })
                                ->placeholder('Not linked'),

                            TextEntry::make('person_id')
                                ->label('Person ID')
                                ->placeholder('Not linked'),

                            TextEntry::make('person_name')
                                ->label('Person Name')
                                ->state(fn (Account $record): string => $record->getPerson()?->name ?? 'No linked person record')
                                ->placeholder('No linked person record'),
                        ])
                        ->columns(3)
                        ->visible(fn (Account $record): bool => $record->hasLinkedPerson()),

                    Section::make('Account Activity')
                        ->description('Account usage statistics')
                        ->schema([
                            TextEntry::make('last_login')
                                ->label('Last Login')
                                ->dateTime('M j, Y g:i A')
                                ->placeholder('Never logged in'),

                            TextEntry::make('created_at')
                                ->label('Created At')
                                ->dateTime('M j, Y g:i A'),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime('M j, Y g:i A'),

                            TextEntry::make('email_verified_at')
                                ->label('Email Verified')
                                ->dateTime('M j, Y g:i A')
                                ->placeholder('Not verified'),
                            // ->suffix(fn ($state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            // ->suffixIconColor(fn ($state): string => $state ? 'success' : 'danger'),
                        ])
                        ->columns(2),
                ]),
        ]);
    }
}
