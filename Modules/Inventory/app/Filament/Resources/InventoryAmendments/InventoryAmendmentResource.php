<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryAmendments;

use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Inventory\Filament\Resources\InventoryAmendments\Pages\CreateInventoryAmendment;
use Modules\Inventory\Filament\Resources\InventoryAmendments\Pages\EditInventoryAmendment;
use Modules\Inventory\Filament\Resources\InventoryAmendments\Pages\ListInventoryAmendments;
use Modules\Inventory\Filament\Resources\InventoryAmendments\Pages\ViewInventoryAmendment;
use Modules\Inventory\Filament\Resources\InventoryAmendments\Schemas\InventoryAmendmentForm;
use Modules\Inventory\Filament\Resources\InventoryAmendments\Schemas\InventoryAmendmentInfolist;
use Modules\Inventory\Filament\Resources\InventoryAmendments\Tables\InventoryAmendmentsTable;
use Modules\Inventory\Models\InventoryAmendment;
use UnitEnum;

final class InventoryAmendmentResource extends Resource
{
    protected static ?string $model = InventoryAmendment::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Stock Adjustments';

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return InventoryAmendmentForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return InventoryAmendmentInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return InventoryAmendmentsTable::configure($table);
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
            'index' => ListInventoryAmendments::route('/'),
            'create' => CreateInventoryAmendment::route('/create'),
            'view' => ViewInventoryAmendment::route('/{record}'),
            'edit' => EditInventoryAmendment::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewInventoryAmendment::class,
            EditInventoryAmendment::class,
        ]);
    }
}
