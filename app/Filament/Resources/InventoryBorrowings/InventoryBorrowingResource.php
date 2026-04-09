<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings;

use App\Filament\Resources\InventoryBorrowings\Pages\CreateInventoryBorrowing;
use App\Filament\Resources\InventoryBorrowings\Pages\EditInventoryBorrowing;
use App\Filament\Resources\InventoryBorrowings\Pages\ListInventoryBorrowings;
use App\Filament\Resources\InventoryBorrowings\Pages\ViewInventoryBorrowing;
use App\Filament\Resources\InventoryBorrowings\Schemas\InventoryBorrowingForm;
use App\Filament\Resources\InventoryBorrowings\Schemas\InventoryBorrowingInfolist;
use App\Filament\Resources\InventoryBorrowings\Tables\InventoryBorrowingsTable;
use App\Models\InventoryBorrowing;
use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class InventoryBorrowingResource extends Resource
{
    protected static ?string $model = InventoryBorrowing::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'borrower_name';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Borrowings';

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return InventoryBorrowingForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return InventoryBorrowingInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return InventoryBorrowingsTable::configure($table);
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
            'index' => ListInventoryBorrowings::route('/'),
            'create' => CreateInventoryBorrowing::route('/create'),
            'view' => ViewInventoryBorrowing::route('/{record}'),
            'edit' => EditInventoryBorrowing::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewInventoryBorrowing::class,
            EditInventoryBorrowing::class,
        ]);
    }
}
