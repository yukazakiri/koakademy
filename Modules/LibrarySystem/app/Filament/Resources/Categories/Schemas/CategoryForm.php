<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Librarysystem\Models\Category;

final class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Information')
                    ->schema(self::getBasicInfoSection())
                    ->columns(2),
                Section::make('Additional Details')
                    ->schema(self::getAdditionalInfoSection())
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Category $record): bool => ! $record instanceof Category),
            ])
            ->columns(3);
    }

    private static function getBasicInfoSection(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(Category::class, 'name', ignoreRecord: true),

            Textarea::make('description')
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }

    private static function getAdditionalInfoSection(): array
    {
        return [
            Placeholder::make('id')
                ->label('Category ID')
                ->content(fn (?Category $record): ?string => $record?->id),

            Placeholder::make('created_at')
                ->label('Created at')
                ->content(fn (Category $record): ?string => $record->created_at?->diffForHumans()),

            Placeholder::make('books_count')
                ->label('Books in Category')
                ->content(fn (Category $record): ?string => $record?->books_count ?? '0'),
        ];
    }
}
