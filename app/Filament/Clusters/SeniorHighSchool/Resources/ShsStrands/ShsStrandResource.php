<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands;

use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Pages\CreateShsStrand;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Pages\EditShsStrand;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Pages\ListShsStrands;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Pages\ViewShsStrand;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\RelationManagers\StudentsRelationManager;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\RelationManagers\SubjectsRelationManager;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Schemas\ShsStrandForm;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Schemas\ShsStrandInfolist;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Tables\ShsStrandsTable;
use App\Filament\Clusters\SeniorHighSchool\SeniorHighSchoolCluster;
use App\Models\ShsStrand;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ShsStrandResource extends Resource
{
    protected static ?string $model = ShsStrand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = SeniorHighSchoolCluster::class;

    protected static ?string $recordTitleAttribute = 'strand_name';

    protected static ?string $modelLabel = 'Strand';

    protected static ?string $pluralModelLabel = 'Strands';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'strand_name',
            'description',
            'track.track_name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['track']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ShsStrand $record */
        return [
            'Track' => $record->track?->track_name ?? 'N/A',
            'Students' => $record->students()->count(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ShsStrandForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShsStrandInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShsStrandsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationGroup::make('Enrolled Students', [
                StudentsRelationManager::class,
            ]),
            RelationGroup::make('Strand Subjects', [
                SubjectsRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShsStrands::route('/'),
            'create' => CreateShsStrand::route('/create'),
            'view' => ViewShsStrand::route('/{record}'),
            'edit' => EditShsStrand::route('/{record}/edit'),
        ];
    }
}
