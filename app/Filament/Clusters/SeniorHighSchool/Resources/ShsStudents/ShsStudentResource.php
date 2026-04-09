<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents;

use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Pages\CreateShsStudent;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Pages\EditShsStudent;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Pages\ListShsStudents;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Pages\ViewShsStudent;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Schemas\ShsStudentForm;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Schemas\ShsStudentInfolist;
use App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Tables\ShsStudentsTable;
use App\Filament\Clusters\SeniorHighSchool\SeniorHighSchoolCluster;
use App\Models\ShsStudent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ShsStudentResource extends Resource
{
    protected static ?string $model = ShsStudent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $cluster = SeniorHighSchoolCluster::class;

    protected static ?string $recordTitleAttribute = 'fullname';

    protected static ?string $modelLabel = 'SHS Student';

    protected static ?string $pluralModelLabel = 'SHS Students';

    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'student_lrn',
            'fullname',
            'email',
            'track.track_name',
            'strand.strand_name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['track', 'strand']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ShsStudent $record */
        return [
            'LRN' => $record->student_lrn ?? 'N/A',
            'Track' => $record->track?->track_name ?? 'N/A',
            'Strand' => $record->strand?->strand_name ?? 'N/A',
            'Grade Level' => $record->grade_level ?? 'N/A',
        ];
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('view')
                ->url(self::getUrl('view', ['record' => $record]))
                ->icon(Heroicon::OutlinedEye),
            Action::make('edit')
                ->url(self::getUrl('edit', ['record' => $record]))
                ->icon(Heroicon::OutlinedPencilSquare),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return self::getUrl('view', ['record' => $record]);
    }

    public static function form(Schema $schema): Schema
    {
        return ShsStudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShsStudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShsStudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShsStudents::route('/'),
            'create' => CreateShsStudent::route('/create'),
            'view' => ViewShsStudent::route('/{record}'),
            'edit' => EditShsStudent::route('/{record}/edit'),
        ];
    }
}
