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
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Campus';

    protected static ?int $navigationSort = 3;

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
}
