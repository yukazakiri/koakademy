<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryCategories;

use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Inventory\Filament\Resources\InventoryCategories\Pages\CreateInventoryCategory;
use Modules\Inventory\Filament\Resources\InventoryCategories\Pages\EditInventoryCategory;
use Modules\Inventory\Filament\Resources\InventoryCategories\Pages\ListInventoryCategories;
use Modules\Inventory\Filament\Resources\InventoryCategories\Pages\ViewInventoryCategory;
use Modules\Inventory\Filament\Resources\InventoryCategories\Schemas\InventoryCategoryForm;
use Modules\Inventory\Filament\Resources\InventoryCategories\Schemas\InventoryCategoryInfolist;
use Modules\Inventory\Filament\Resources\InventoryCategories\Tables\InventoryCategoriesTable;
use Modules\Inventory\Models\InventoryCategory;
use UnitEnum;

final class InventoryCategoryResource extends Resource
{
    protected static ?string $model = InventoryCategory::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Categories';

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return InventoryCategoryForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return InventoryCategoryInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return InventoryCategoriesTable::configure($table);
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
            'index' => ListInventoryCategories::route('/'),
            'create' => CreateInventoryCategory::route('/create'),
            'view' => ViewInventoryCategory::route('/{record}'),
            'edit' => EditInventoryCategory::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewInventoryCategory::class,
            EditInventoryCategory::class,
        ]);
    }
}
