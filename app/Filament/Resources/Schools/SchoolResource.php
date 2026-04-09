<?php

declare(strict_types=1);

namespace App\Filament\Resources\Schools;

use App\Filament\Resources\Schools\Pages\CreateSchool;
use App\Filament\Resources\Schools\Pages\EditSchool;
use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Filament\Resources\Schools\Pages\ViewSchool;
use App\Filament\Resources\Schools\Schemas\SchoolForm;
use App\Filament\Resources\Schools\Schemas\SchoolInfolist;
use App\Filament\Resources\Schools\Tables\SchoolsTable;
use App\Models\School;
use Exception;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

final class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Campus';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', School::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', School::class) ?? false;
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
        return auth()->user()?->can('deleteAny', School::class) ?? false;
    }

    /**
     * @throws Exception
     */
    public static function form(Schema $schema): Schema
    {
        return SchoolForm::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function infolist(Schema $schema): Schema
    {
        return SchoolInfolist::configure($schema);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return SchoolsTable::configure($table);
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
            'index' => ListSchools::route('/'),
            'create' => CreateSchool::route('/create'),
            'view' => ViewSchool::route('/{record}'),
            'edit' => EditSchool::route('/{record}/edit'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewSchool::class,
            EditSchool::class,
        ]);
    }
}
