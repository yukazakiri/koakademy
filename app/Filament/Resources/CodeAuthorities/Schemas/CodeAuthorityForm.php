<?php

declare(strict_types=1);

namespace App\Filament\Resources\CodeAuthorities\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CodeAuthorityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Authority identity')
                    ->description('A regulatory list this school tracks (e.g. CHED). Lists are imported per school — nothing ships with the app.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., ched')
                            ->helperText('Short unique key for this school. Lowercase letters, numbers and underscores.'),
                        TextInput::make('name')
                            ->label('Authority name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., CHED'),
                        TextInput::make('country_code')
                            ->label('Country code')
                            ->maxLength(2)
                            ->placeholder('e.g., PH')
                            ->helperText('Two-letter code. Limits visibility to schools in the same country when set.')
                            ->afterStateUpdated(function (mixed $state, callable $set): void {
                                if (is_string($state)) {
                                    $set('country_code', mb_strtoupper($state));
                                }
                            }),
                        TextInput::make('curriculum_framework')
                            ->label('Curriculum framework')
                            ->maxLength(50)
                            ->placeholder('e.g., ched_psg')
                            ->helperText('Limits visibility to schools with this enabled curriculum capability when set.'),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Where to obtain the official spreadsheet, issuance reference, notes for staff.'),
                    ]),
                Section::make('Visibility')
                    ->schema([
                        Checkbox::make('is_active')
                            ->label('Authority is active')
                            ->helperText('Inactive authorities are hidden from pickers and imports.')
                            ->default(true),
                    ]),
            ]);
    }
}
