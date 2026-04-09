<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers;

use App\Filament\Resources\InventorySuppliers\Pages\CreateInventorySupplier;
use App\Filament\Resources\InventorySuppliers\Pages\EditInventorySupplier;
use App\Filament\Resources\InventorySuppliers\Pages\ListInventorySuppliers;
use App\Filament\Resources\InventorySuppliers\Pages\ViewInventorySupplier;
use App\Filament\Resources\InventorySuppliers\Schemas\InventorySupplierForm;
use App\Filament\Resources\InventorySuppliers\Schemas\InventorySupplierInfolist;
use App\Filament\Resources\InventorySuppliers\Tables\InventorySuppliersTable;
use App\Models\InventorySupplier;
use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class InventorySupplierResource extends Resource
{
    protected static ?string $model = InventorySupplier::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Suppliers';

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return InventorySupplierForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return InventorySupplierInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return InventorySuppliersTable::configure($table);
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
            'index' => ListInventorySuppliers::route('/'),
            'create' => CreateInventorySupplier::route('/create'),
            'view' => ViewInventorySupplier::route('/{record}'),
            'edit' => EditInventorySupplier::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewInventorySupplier::class,
            EditInventorySupplier::class,
        ]);
    }
}
