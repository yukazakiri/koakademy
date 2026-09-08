<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books\Schemas;

use Filament\Infolists\Components\ImageEntry;
// use Librarysystem\Models\Book;
use Filament\Infolists\Components\TextEntry;
// use Filament\Infolists\Components\Section;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\LibrarySystem\Models\Book;

final class BookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Book Information')
                    ->schema(self::getBasicInfoSection())
                    ->columns(2),

                Section::make('Additional Details')
                    ->schema(self::getAdditionalInfoSection())
                    ->columns(3)
                    ->collapsed(),

                Section::make('Cover Image')
                    ->schema(self::getCoverImageSection())
                    ->collapsed()
                    ->hidden(fn (Book $record): bool => ! ($record->cover_image_path || $record->cover_image)),
            ]);
    }

    private static function getBasicInfoSection(): array
    {
        return [
            TextEntry::make('title')
                ->label('Title')
                ->size('lg')
                ->weight('bold')
                ->columnSpanFull(),

            TextEntry::make('isbn')
                ->label('ISBN')
                ->badge()
                ->color('info'),

            TextEntry::make('call_number')
                ->label('Call Number')
                ->badge()
                ->color('gray')
                ->placeholder('—'),

            TextEntry::make('accession_number')
                ->label('Accession Number')
                ->badge()
                ->color('gray')
                ->placeholder('—'),

            TextEntry::make('author.name')
                ->label('Author')
                ->badge()
                ->color('success'),

            TextEntry::make('category.name')
                ->label('Category')
                ->badge()
                ->color('warning'),

            TextEntry::make('publisher')
                ->label('Publisher'),

            TextEntry::make('publication_year')
                ->label('Publication Year')
                ->placeholder('—'),

            TextEntry::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'available' => 'success',
                    'borrowed' => 'warning',
                    'maintenance' => 'danger',
                    default => 'gray',
                }),

            TextEntry::make('total_copies')
                ->label('Total Copies')
                ->badge()
                ->color('primary'),

            TextEntry::make('available_copies')
                ->label('Available Copies')
                ->badge()
                ->color(fn (Book $record): string => $record->available_copies > 0 ? 'success' : 'danger'),
        ];
    }

    private static function getAdditionalInfoSection(): array
    {
        return [
            TextEntry::make('description')
                ->label('Description')
                ->columnSpanFull()
                ->markdown(),

            TextEntry::make('pages')
                ->label('Pages')
                ->suffix(' pages')
                ->placeholder('—'),

            TextEntry::make('location')
                ->label('Shelf Location')
                ->badge()
                ->color('gray')
                ->placeholder('—'),

            TextEntry::make('created_at')
                ->label('Added to Library')
                ->dateTime('M d, Y \a\t g:i A'),
        ];
    }

    private static function getCoverImageSection(): array
    {
        return [
            ImageEntry::make('cover_image')
                ->label('Cover Image')
                ->disk('public')
                ->state(fn (Book $record): ?string => $record->cover_image_path ?: $record->cover_image)
                ->height(300)
                ->columnSpanFull(),
        ];
    }
}
