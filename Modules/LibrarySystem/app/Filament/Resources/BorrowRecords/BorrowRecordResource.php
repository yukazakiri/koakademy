<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages\CreateBorrowRecord;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages\EditBorrowRecord;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages\ListBorrowRecords;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages\ViewBorrowRecord;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\Schemas\BorrowRecordForm;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\Schemas\BorrowRecordInfolist;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\Tables\BorrowRecordsTable;
use Modules\LibrarySystem\Models\BorrowRecord;
use UnitEnum;

final class BorrowRecordResource extends Resource
{
    protected static ?string $model = BorrowRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'book_id';

    public static function form(Schema $schema): Schema
    {
        return BorrowRecordForm::configure($schema);
    }

    // public static function infolist(Schema $schema): Schema
    // {
    //     return BorrowRecordInfolist::configure($schema);
    // }

    public static function table(Table $table): Table
    {
        return BorrowRecordsTable::configure($table);
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
            'index' => ListBorrowRecords::route('/'),
            'create' => CreateBorrowRecord::route('/create'),
            'view' => ViewBorrowRecord::route('/{record}'),
            'edit' => EditBorrowRecord::route('/{record}/edit'),
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
