<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryProducts;

use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Inventory\Filament\Resources\InventoryProducts\Pages\CreateInventoryProduct;
use Modules\Inventory\Filament\Resources\InventoryProducts\Pages\EditInventoryProduct;
use Modules\Inventory\Filament\Resources\InventoryProducts\Pages\ListInventoryProducts;
use Modules\Inventory\Filament\Resources\InventoryProducts\Pages\ViewInventoryProduct;
use Modules\Inventory\Filament\Resources\InventoryProducts\Schemas\InventoryProductForm;
use Modules\Inventory\Filament\Resources\InventoryProducts\Schemas\InventoryProductInfolist;
use Modules\Inventory\Filament\Resources\InventoryProducts\Tables\InventoryProductsTable;
use Modules\Inventory\Models\InventoryProduct;
use UnitEnum;

final class InventoryProductResource extends Resource
{
    protected static ?string $model = InventoryProduct::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Products';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', InventoryProduct::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', InventoryProduct::class) ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view', $record) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('deleteAny', InventoryProduct::class) ?? false;
    }

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return InventoryProductForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return InventoryProductInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return InventoryProductsTable::configure($table);
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
            'index' => ListInventoryProducts::route('/'),
            'create' => CreateInventoryProduct::route('/create'),
            'view' => ViewInventoryProduct::route('/{record}'),
            'edit' => EditInventoryProduct::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewInventoryProduct::class,
            EditInventoryProduct::class,
        ]);
    }
}
