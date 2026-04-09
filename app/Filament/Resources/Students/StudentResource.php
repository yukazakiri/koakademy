<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students;

use App\Filament\Resources\Students\Pages\ChangeCourse;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\StudentRevisions;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ClassesRelationManager;
use App\Filament\Resources\Students\RelationManagers\CurrentClassesRelationManager;
use App\Filament\Resources\Students\RelationManagers\EnrolledSubjectsRelationManager;
use App\Filament\Resources\Students\RelationManagers\StatementOfAccountRelationManager;
use App\Filament\Resources\Students\Schemas\StudentForm;
use App\Filament\Resources\Students\Schemas\StudentInfolist;
use App\Filament\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

final class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'student_id',
            'first_name',
            'last_name',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['course']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Student $record */
        return [
            'Student ID' => $record->student_id ?? 'N/A',
            'Full Name' => $record->full_name,
            'Course' => $record->course?->title ?? 'N/A',
            'Year Level' => $record->academic_year ?? 'N/A',
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
        return StudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // AccounsRelationManager::class,
            // AccountsRelationManager::class,
            CurrentClassesRelationManager::class,
            RelationGroup::make('Enrolled Subject', [
                ClassesRelationManager::class,
            ]),
            RelationGroup::make('Academic Information', [
                EnrolledSubjectsRelationManager::class,

            ]),
            RelationGroup::make('financial records', [
                StatementOfAccountRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'view' => ViewStudent::route('/{record}'),
            'edit' => EditStudent::route('/{record}/edit'),
            'revisions' => StudentRevisions::route('/{record}/revisions'),
            'change-course' => ChangeCourse::route('/{record}/change-course'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
