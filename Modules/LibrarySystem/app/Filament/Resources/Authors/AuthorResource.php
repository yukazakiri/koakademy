<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Authors;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\LibrarySystem\Filament\Resources\Authors\Pages\CreateAuthor;
use Modules\LibrarySystem\Filament\Resources\Authors\Pages\EditAuthor;
use Modules\LibrarySystem\Filament\Resources\Authors\Pages\ListAuthors;
use Modules\LibrarySystem\Filament\Resources\Authors\Pages\ViewAuthor;
use Modules\LibrarySystem\Filament\Resources\Authors\Schemas\AuthorForm;
use Modules\LibrarySystem\Filament\Resources\Authors\Schemas\AuthorInfolist;
use Modules\LibrarySystem\Filament\Resources\Authors\Tables\AuthorsTable;
use Modules\LibrarySystem\Models\Author;
use UnitEnum;

final class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::User;

    protected static UnitEnum|string|null $navigationGroup = 'Library';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AuthorForm::configure($schema);
    }

    // public static function infolist(Schema $schema): Schema
    // {
    //     return AuthorInfolist::configure($schema);
    // }

    public static function table(Table $table): Table
    {
        return AuthorsTable::configure($table);
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
            'index' => ListAuthors::route('/'),
            'create' => CreateAuthor::route('/create'),
            'view' => ViewAuthor::route('/{record}'),
            'edit' => EditAuthor::route('/{record}/edit'),
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
