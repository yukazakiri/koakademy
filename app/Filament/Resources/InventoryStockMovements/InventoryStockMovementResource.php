<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements;

use App\Filament\Resources\InventoryStockMovements\Pages\CreateInventoryStockMovement;
use App\Filament\Resources\InventoryStockMovements\Pages\EditInventoryStockMovement;
use App\Filament\Resources\InventoryStockMovements\Pages\ListInventoryStockMovements;
use App\Filament\Resources\InventoryStockMovements\Pages\ViewInventoryStockMovement;
use App\Filament\Resources\InventoryStockMovements\Schemas\InventoryStockMovementForm;
use App\Filament\Resources\InventoryStockMovements\Schemas\InventoryStockMovementInfolist;
use App\Filament\Resources\InventoryStockMovements\Tables\InventoryStockMovementsTable;
use App\Models\InventoryStockMovement;
use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class InventoryStockMovementResource extends Resource
{
    protected static ?string $model = InventoryStockMovement::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Stock Movements';

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return InventoryStockMovementForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return InventoryStockMovementInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return InventoryStockMovementsTable::configure($table);
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
            'index' => ListInventoryStockMovements::route('/'),
            'create' => CreateInventoryStockMovement::route('/create'),
            'view' => ViewInventoryStockMovement::route('/{record}'),
            'edit' => EditInventoryStockMovement::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewInventoryStockMovement::class,
            EditInventoryStockMovement::class,
        ]);
    }
}
