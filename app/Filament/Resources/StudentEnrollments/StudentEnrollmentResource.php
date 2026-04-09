<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments;

use App\Filament\Resources\StudentEnrollments\Pages\CreateStudentEnrollment;
use App\Filament\Resources\StudentEnrollments\Pages\EditStudentEnrollment;
use App\Filament\Resources\StudentEnrollments\Pages\ListStudentEnrollments;
use App\Filament\Resources\StudentEnrollments\Pages\ViewStudentEnrollment;
use App\Filament\Resources\StudentEnrollments\Schemas\StudentEnrollmentForm;
use App\Filament\Resources\StudentEnrollments\Schemas\StudentEnrollmentInfolist;
use App\Filament\Resources\StudentEnrollments\Tables\StudentEnrollmentsTable;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

final class StudentEnrollmentResource extends Resource
{
    protected static ?string $model = StudentEnrollment::class;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Enrollments';

    protected static ?string $recordTitleAttribute = 'student_id';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'id',
            'student_id',
            'status',
            'school_year',
            'student.last_name',
        ];
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $settingsService = app(GeneralSettingsService::class);
        $schoolYearWithSpaces = $settingsService->getCurrentSchoolYearString(); // e.g., "2020 - 2021"
        // $schoolYearNoSpaces = str_replace(' ', '', $schoolYearWithSpaces);      // e.g., "2020-2021"
        $semester = $settingsService->getCurrentSemester();
        $query = self::getModel()::query()
            ->with(['student', 'course'])
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhereExists(function ($subQuery) use ($search): void {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('students')
                            ->whereRaw(
                                'CAST(student_enrollment.student_id AS BIGINT) = students.id'
                            )
                            ->where(function ($nameQuery) use ($search): void {
                                $nameQuery
                                    ->where(
                                        'students.first_name',
                                        'ilike',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'students.last_name',
                                        'ilike',
                                        "%{$search}%"
                                    );
                            })
                            ->whereNull('students.deleted_at');
                    })
                    ->orWhereExists(function ($subQuery) use ($search): void {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('courses')
                            ->whereRaw(
                                'CAST(student_enrollment.course_id AS BIGINT) = courses.id'
                            )
                            ->where(function ($courseQuery) use ($search): void {
                                $courseQuery
                                    ->where(
                                        'courses.code',
                                        'ilike',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'courses.title',
                                        'ilike',
                                        "%{$search}%"
                                    );
                            });
                    });
            })
            ->where('school_year', $schoolYearWithSpaces)
            ->where('semester', $semester)
            ->withTrashed()
            ->limit(50);

        return $query->get()->map(fn (Model $record): GlobalSearchResult => new GlobalSearchResult(
            title: "Enrollment #{$record->id}",
            url: self::getUrl('view', ['record' => $record]),
            details: self::getGlobalSearchResultDetails($record),
            actions: []
        ));
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $statusLabels = app(EnrollmentPipelineService::class)->getStatusLabels();

        return [
            'ID' => $record->id,
            'Student' => $record->student->full_name ?? 'N/A',
            'Course' => $record->course?->code ?? 'N/A',
            'Status' => $statusLabels[(string) $record->status] ?? ($record->status ?: 'N/A'),
            'School Year' => $record->school_year,
            'Semester' => $record->semester,
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
        return StudentEnrollmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentEnrollmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentEnrollmentsTable::configure($table);
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
            'index' => ListStudentEnrollments::route('/'),
            'create' => CreateStudentEnrollment::route('/create'),
            'view' => ViewStudentEnrollment::route('/{record}'),
            'edit' => EditStudentEnrollment::route('/{record}/edit'),
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
