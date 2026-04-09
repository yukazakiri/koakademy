<?php

declare(strict_types=1);

namespace Modules\Announcement\Filament\Resources\Announcements;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Announcement\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use Modules\Announcement\Filament\Resources\Announcements\Pages\EditAnnouncement;
use Modules\Announcement\Filament\Resources\Announcements\Pages\ListAnnouncements;
use Modules\Announcement\Filament\Resources\Announcements\Schemas\AnnouncementForm;
use Modules\Announcement\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use Modules\Announcement\Models\Announcement;
use UnitEnum;

final class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 1;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    public static function form(Schema $schema): Schema
    {
        return AnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnouncementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
