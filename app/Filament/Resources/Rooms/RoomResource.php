<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rooms;

use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Pages\ViewRoom;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Resources\Rooms\Schemas\RoomInfolist;
use App\Filament\Resources\Rooms\Tables\RoomsTable;
use App\Models\Room;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class RoomResource extends Resource
{
    #[Override]
    protected static ?string $model = Room::class;

    #[Override]
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Campus';

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected static ?string $navigationLabel = 'Rooms';

    public static function getNavigationBadge(): ?string
    {
        return (string) Room::query()->where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if ($user?->role?->isAdministrative() || $user?->role?->isRegistrar()) {
            return true;
        }

        return $user?->can('viewAny', Room::class) ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if ($user?->role?->isAdministrative() || $user?->role?->isRegistrar()) {
            return true;
        }

        return $user?->can('create', Room::class) ?? false;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        if ($user?->role?->isAdministrative() || $user?->role?->isRegistrar()) {
            return true;
        }

        return $user?->can('view', $record) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if ($user?->role?->isAdministrative() || $user?->role?->isRegistrar()) {
            return true;
        }

        return $user?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        if ($user?->role?->isAdministrative()) {
            return true;
        }

        return $user?->can('delete', $record) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if ($user?->role?->isAdministrative()) {
            return true;
        }

        return $user?->can('deleteAny', Room::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoomInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomsTable::configure($table);
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
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'view' => ViewRoom::route('/{record}'),
            'edit' => EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewRoom::class,
            EditRoom::class,
        ]);
    }
}
