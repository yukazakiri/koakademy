<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\LibrarySystem\Models\Book;

final class BookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema(self::getBasicInfoSection())
                    ->columns(2)
                    ->columnSpan([
                        'lg' => fn (?Book $record): int => $record instanceof Book ? 2 : 3,
                    ]),
                Section::make('Additional Details')
                    ->schema(self::getAdditionalInfoSection())
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Book $record): bool => ! $record instanceof Book),
                Section::make('Book Details')
                    ->schema(self::getBookDetailsSection())
                    ->columns(2)
                    ->columnSpan([
                        'lg' => fn (?Book $record): int => $record instanceof Book ? 2 : 3,
                    ])
                    ->collapsible(),
            ])
            ->columns(3);
    }

    private static function getBasicInfoSection(): array
    {
        return [
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('isbn')
                ->label('ISBN')
                ->maxLength(50),

            TextInput::make('call_number')
                ->label('Call Number')
                ->maxLength(255),

            TextInput::make('accession_number')
                ->label('Accession Number')
                ->maxLength(255),

            Select::make('author_id')
                ->label('Author')
                ->relationship('author', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('nationality')
                        ->maxLength(100),
                    DatePicker::make('birth_date')
                        ->label('Birth Date')
                        ->maxDate('today'),
                    Textarea::make('biography')
                        ->maxLength(1000),
                ]),

            Select::make('category_id')
                ->label('Category')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('color')
                        ->label('Hex Color')
                        ->default('#6366f1')
                        ->maxLength(7)
                        ->placeholder('#6366f1'),
                    Textarea::make('description')
                        ->maxLength(500),
                ]),

            TextInput::make('publisher')
                ->maxLength(255),

            TextInput::make('publication_year')
                ->label('Publication Year')
                ->numeric()
                ->minValue(1500)
                ->maxValue((int) date('Y') + 1)
                ->placeholder('YYYY'),

            Select::make('status')
                ->label('Status')
                ->options([
                    'available' => 'Available',
                    'borrowed' => 'Borrowed',
                    'maintenance' => 'Maintenance',
                ])
                ->default('available')
                ->required(),

            TextInput::make('total_copies')
                ->label('Total Copies')
                ->numeric()
                ->required()
                ->default(1)
                ->minValue(1)
                ->live(onBlur: true)
                ->afterStateUpdated(function (mixed $state, callable $set, callable $get, ?Book $record): void {
                    if (! $record) {
                        $currentAvailable = $get('available_copies');
                        if ($currentAvailable === null || $currentAvailable === '' || (int) $currentAvailable === 1) {
                            $set('available_copies', $state);
                        }
                    }
                }),

            TextInput::make('available_copies')
                ->label('Available Copies')
                ->numeric()
                ->minValue(0)
                ->placeholder(fn (callable $get): ?string => $get('total_copies') ? (string) $get('total_copies') : '1')
                ->helperText('Defaults to total copies if left blank.'),
        ];
    }

    private static function getAdditionalInfoSection(): array
    {
        return [
            Placeholder::make('id')
                ->label('Book ID')
                ->content(fn (?Book $record): ?string => $record?->id ? (string) $record->id : null),

            Placeholder::make('created_at')
                ->label('Created at')
                ->content(fn (?Book $record): ?string => $record?->created_at?->diffForHumans()),

            Placeholder::make('updated_at')
                ->label('Updated at')
                ->content(fn (?Book $record): ?string => $record?->updated_at?->diffForHumans()),
        ];
    }

    private static function getBookDetailsSection(): array
    {
        return [
            TextInput::make('pages')
                ->numeric()
                ->minValue(1),

            TextInput::make('location')
                ->label('Shelf Location')
                ->maxLength(255)
                ->placeholder('e.g., Main Library · A-12'),

            TextInput::make('cover_image')
                ->label('Cover Image URL')
                ->maxLength(255)
                ->placeholder('https://…'),

            FileUpload::make('cover_image_path')
                ->label('Cover Image Upload')
                ->image()
                ->disk('public')
                ->directory('library/books/covers')
                ->visibility('public')
                ->columnSpanFull(),

            Textarea::make('description')
                ->columnSpanFull()
                ->rows(4)
                ->maxLength(2000),
        ];
    }
}

