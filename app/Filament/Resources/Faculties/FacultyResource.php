<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties;

use App\Filament\Resources\Faculties\Pages\CreateFaculty;
use App\Filament\Resources\Faculties\Pages\EditFaculty;
use App\Filament\Resources\Faculties\Pages\ListFaculties;
use App\Filament\Resources\Faculties\Pages\ViewFaculty;
use App\Filament\Resources\Faculties\RelationManagers\ClassesRelationManager;
use App\Filament\Resources\Faculties\Schemas\FacultyForm;
use App\Filament\Resources\Faculties\Schemas\FacultyInfolist;
use App\Filament\Resources\Faculties\Tables\FacultiesTable;
use App\Models\Faculty;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Faculty';

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'faculty_id_number',
            'first_name',
            'last_name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Faculty $record */
        return [
            'Faculty ID' => $record->faculty_id_number ?? 'N/A',
            'Full Name' => $record->full_name,
            'Department' => $record->department ?? 'N/A',
        ];
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('view')
                ->url(self::getUrl('view', ['record' => $record]))
                ->icon('heroicon-o-eye'),
            Action::make('edit')
                ->url(self::getUrl('edit', ['record' => $record]))
                ->icon('heroicon-o-pencil-square'),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return self::getUrl('view', ['record' => $record]);
    }

    public static function form(Schema $schema): Schema
    {
        return FacultyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FacultyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacultiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ClassesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaculties::route('/'),
            'create' => CreateFaculty::route('/create'),
            'view' => ViewFaculty::route('/{record}'),
            'edit' => EditFaculty::route('/{record}/edit'),
        ];
    }
}
