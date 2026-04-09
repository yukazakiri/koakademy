<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Categories;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\LibrarySystem\Filament\Resources\Categories\Pages\CreateCategory;
use Modules\LibrarySystem\Filament\Resources\Categories\Pages\EditCategory;
use Modules\LibrarySystem\Filament\Resources\Categories\Pages\ListCategories;
use Modules\LibrarySystem\Filament\Resources\Categories\Pages\ViewCategory;
use Modules\LibrarySystem\Filament\Resources\Categories\Schemas\CategoryForm;
use Modules\LibrarySystem\Filament\Resources\Categories\Schemas\CategoryInfolist;
use Modules\LibrarySystem\Filament\Resources\Categories\Tables\CategoriesTable;
use Modules\LibrarySystem\Models\Category;
use UnitEnum;

final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?string $navigationLabel = 'Book Categories';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'view' => ViewCategory::route('/{record}'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
