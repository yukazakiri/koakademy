<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\LibrarySystem\Filament\Resources\Books\Pages\CreateBook;
use Modules\LibrarySystem\Filament\Resources\Books\Pages\EditBook;
use Modules\LibrarySystem\Filament\Resources\Books\Pages\ListBooks;
use Modules\LibrarySystem\Filament\Resources\Books\Pages\ViewBook;
use Modules\LibrarySystem\Filament\Resources\Books\Schemas\BookForm;
use Modules\LibrarySystem\Filament\Resources\Books\Schemas\BookInfolist;
use Modules\LibrarySystem\Filament\Resources\Books\Tables\BooksTable;
use Modules\LibrarySystem\Models\Book;
use UnitEnum;

final class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return BookForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BooksTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BookInfolist::configure($schema);
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
            'index' => ListBooks::route('/'),
            'create' => CreateBook::route('/create'),
            'edit' => EditBook::route('/{record}/edit'),
            'view' => ViewBook::route('/{record}'),
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
