<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Authors\Schemas;

// use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\LibrarySystem\Models\Author;

final class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Author Information')
                    ->schema(self::getBasicInfoSection())
                    ->columns(2),
                Section::make('Additional Details')
                    ->schema(self::getAdditionalInfoSection())
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Author $record): bool => ! $record instanceof Author),
            ])
            ->columns(3);
    }

    private static function getBasicInfoSection(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('email')
                ->email()
                ->maxLength(255)
                ->unique(Author::class, 'email', ignoreRecord: true),

            TextInput::make('nationality')
                ->maxLength(100),

            TextInput::make('birth_year')
                ->numeric()
                ->minValue(1000)
                ->maxValue(date('Y')),

            TextInput::make('death_year')
                ->numeric()
                ->minValue(1000)
                ->maxValue(date('Y')),
        ];
    }

    private static function getAdditionalInfoSection(): array
    {
        return [
            Placeholder::make('id')
                ->label('Author ID')
                ->content(fn (?Author $record): ?string => $record?->id),

            Placeholder::make('created_at')
                ->label('Created at')
                ->content(fn (Author $record): ?string => $record->created_at?->diffForHumans()),

            Placeholder::make('books_count')
                ->label('Books Written')
                ->content(fn (Author $record): ?string => $record?->books_count ?? '0'),
        ];
    }
}
