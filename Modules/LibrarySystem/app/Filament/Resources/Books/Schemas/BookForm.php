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
                    ->collapsed()
                    ->hidden(fn (?Book $record): bool => ! $record instanceof Book)
                    ->lazy(),
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
                ->unique(Book::class, 'isbn', ignoreRecord: true)
                ->maxLength(20),

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
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
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
                        ->maxLength(255),
                    Textarea::make('description')
                        ->maxLength(500),
                ]),

            TextInput::make('publisher')
                ->maxLength(255),

            DatePicker::make('published_date')
                ->maxDate('today'),

            TextInput::make('edition')
                ->numeric()
                ->default(1),

            TextInput::make('total_copies')
                ->label('Total Copies')
                ->numeric()
                ->required()
                ->default(1)
                ->minValue(1),
        ];
    }

    private static function getAdditionalInfoSection(): array
    {
        return [
            Placeholder::make('id')
                ->label('Book ID')
                ->content(fn (?Book $record): ?string => $record?->id),

            Placeholder::make('created_at')
                ->label('Created at')
                ->content(fn (Book $record): ?string => $record->created_at?->diffForHumans()),

            Placeholder::make('available_copies')
                ->label('Available Copies')
                ->content(fn (Book $record): ?string => $record?->available_copies ?? '0'),
        ];
    }

    private static function getBookDetailsSection(): array
    {
        return [
            Textarea::make('description')
                ->columnSpanFull()
                ->maxLength(1000),

            TextInput::make('language')
                ->maxLength(50)
                ->default('English'),

            TextInput::make('pages')
                ->numeric()
                ->minValue(1),

            TextInput::make('price')
                ->numeric()
                ->prefix('$')
                ->minValue(0),

            FileUpload::make('cover_image')
                ->label('Cover Image')
                ->image()
                ->disk('public')
                ->directory('book-covers')
                ->visibility('public')
                ->columnSpanFull(),

            TextInput::make('location')
                ->label('Shelf Location')
                ->maxLength(100)
                ->placeholder('e.g., A1-B2-C3'),
        ];
    }
}
