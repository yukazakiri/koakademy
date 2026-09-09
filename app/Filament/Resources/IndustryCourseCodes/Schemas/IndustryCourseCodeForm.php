<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndustryCourseCodes\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class IndustryCourseCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Official code')
                    ->description('An entry from a regulator list. Import the list first, then register codes here only when they are missing from it.')
                    ->columns(2)
                    ->schema([
                        Select::make('code_authority_id')
                            ->label('Authority')
                            ->relationship('authority', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Which regulator list this code belongs to.'),
                        TextInput::make('code')
                            ->label('Official code')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., 464108')
                            ->helperText('Unique within the selected authority.'),
                        TextInput::make('title')
                            ->label('Official title')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->placeholder('e.g., Information Technology'),
                        Select::make('source')
                            ->label('Source')
                            ->options([
                                'manual' => 'Manually registered',
                                'import' => 'Spreadsheet import',
                            ])
                            ->default('manual')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Tracked automatically.'),
                    ]),
                Section::make('Authority-specific fields')
                    ->description('Flexible columns from this authority’s spreadsheet (e.g. discipline group). Keys are adopted from imports.')
                    ->schema([
                        KeyValue::make('attributes')
                            ->label('Attributes')
                            ->keyLabel('Column key')
                            ->valueLabel('Value')
                            ->helperText('Only keys present in the authority schema are used by templates and exports.')
                            ->addable()
                            ->deletable()
                            ->reorderable(),
                    ])
                    ->collapsed(),
                Section::make('Visibility')
                    ->schema([
                        Checkbox::make('is_active')
                            ->label('Code is active')
                            ->helperText('Inactive codes are hidden from program pickers.')
                            ->default(true),
                    ]),
            ]);
    }
}
