<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryCategories\Schemas;

use Exception;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class InventoryCategoryForm
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Office Supplies')
                            ->helperText('The name of the inventory category')
                            ->reactive()
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., office-supplies')
                            ->helperText('URL-friendly version of the name')
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->rows(3)
                            ->placeholder('Brief description of the category')
                            ->helperText('Optional description of what items belong in this category'),

                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship('parent', 'name')
                            ->placeholder('No parent (top-level category)')
                            ->searchable()
                            ->helperText('Optional parent category for hierarchical organization'),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0)
                            ->helperText('Lower numbers appear first'),
                    ]),

                Section::make('Settings')
                    ->columns(1)
                    ->schema([
                        Checkbox::make('is_active')
                            ->label('Active Category')
                            ->helperText('Inactive categories are hidden from product selection')
                            ->default(true),
                    ]),
            ]);
    }
}
