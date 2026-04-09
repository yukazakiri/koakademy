<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes;

use App\Filament\Resources\Classes\Pages\CreateClasses;
use App\Filament\Resources\Classes\Pages\EditClasses;
use App\Filament\Resources\Classes\Pages\ListClasses;
use App\Filament\Resources\Classes\Pages\ViewClasses;
use App\Filament\Resources\Classes\RelationManagers\ClassEnrollmentsRelationManager;
use App\Filament\Resources\Classes\Schemas\ClassesForm;
use App\Filament\Resources\Classes\Schemas\ClassesInfolist;
use App\Filament\Resources\Classes\Tables\ClassesTable;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class ClassesResource extends Resource
{
    protected static ?string $model = Classes::class;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'record_title';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'subject_code',
            'section',
            'subject.title',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $query = parent::getGlobalSearchEloquentQuery()
            ->with(['Subject', 'ShsSubject'])
            ->withCount('class_enrollments');

        // Get the search query from the request
        $search = request()->input('search');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $searchPattern = preg_quote((string) $search, '/');

                // Search in subject_code field (when not null)
                $q->where(function ($subQ) use ($search, $searchPattern): void {
                    $subQ->whereNotNull('subject_code')
                        ->where(function ($codeQ) use ($search, $searchPattern): void {
                            $codeQ->whereRaw('subject_code ~* ?', ["\\y{$searchPattern}\\y"])
                                ->orWhere('subject_code', 'ilike', "%{$search}%");
                        });
                })
                    // Search in section
                    ->orWhere('section', 'ilike', "%{$search}%")
                    // Search via Subject relationship (subject_id) - code and title
                    ->orWhereHas('Subject', function ($subQuery) use ($search, $searchPattern): void {
                        $subQuery->where(function ($codeQ) use ($search, $searchPattern): void {
                            $codeQ->whereRaw('code ~* ?', ["\\y{$searchPattern}\\y"])
                                ->orWhere('code', 'ilike', "%{$search}%");
                        })->orWhere('title', 'ilike', "%{$search}%");
                    })
                    // Search via SHS subject - code and title
                    ->orWhereHas('ShsSubject', function ($subQuery) use ($search, $searchPattern): void {
                        $subQuery->where(function ($codeQ) use ($search, $searchPattern): void {
                            $codeQ->whereRaw('code ~* ?', ["\\y{$searchPattern}\\y"])
                                ->orWhere('code', 'ilike', "%{$search}%");
                        })->orWhere('title', 'ilike', "%{$search}%");
                    })
                    // Search via multiple subjects (subject_ids JSON array)
                    ->orWhereRaw('
                        subject_ids IS NOT NULL
                        AND EXISTS (
                            SELECT 1 FROM subject, jsonb_array_elements_text(classes.subject_ids::jsonb) AS subject_id
                            WHERE subject.id = subject_id::bigint
                            AND (subject.code ~* ? OR subject.code ILIKE ? OR subject.title ILIKE ?)
                        )', ["\\y{$searchPattern}\\y", "%{$search}%", "%{$search}%"]);
            });
        }

        return $query;
    }

    public static function getGlobalSearchResultDetails(
        Model $record
    ): array {
        $subjects = $record->subjects;
        $subjectDisplay = 'N/A';

        if (! $subjects->isEmpty()) {
            // Multiple subjects - show unique titles
            $subjectDisplay = $subjects->pluck('title')->unique()->implode(', ');
        } elseif ($record->Subject) {
            // Single subject
            $subjectDisplay = $record->Subject->title;
        } elseif ($record->ShsSubject) {
            // SHS subject
            $subjectDisplay = $record->ShsSubject->title;
        }

        return [
            'Subject(s)' => $subjectDisplay,
            'Section' => $record->section,
            'Enrolled' => sprintf('%s / %s', $record->class_enrollments_count ?? 0, $record->maximum_slots),
            'Type' => $record->isShs() ? 'SHS' : 'College',
        ];
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('edit')
                ->url(self::getUrl('edit', ['record' => $record])),
            Action::make('view')
                ->url(self::getUrl('view', ['record' => $record]), shouldOpenInNewTab: true),
        ];
    }

    public static function getGlobalSearchResultUrl(
        Model $record
    ): string {
        return self::getUrl('view', ['record' => $record]);
    }

    public static function form(Schema $schema): Schema
    {
        return ClassesForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClassesInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ClassEnrollmentsRelationManager::class,
            RelationManagers\ClassPostsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('class_enrollments')
            ->with([
                'subject',
                'subjectByCode',
                'subjectByCodeFallback',
                'shsSubject',
                'faculty',
                'shsTrack',
                'shsStrand',
            ])
            ->currentAcademicPeriod();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClasses::route('/'),
            'create' => CreateClasses::route('/create'),
            'view' => ViewClasses::route('/{record}'),
            'edit' => EditClasses::route('/{record}/edit'),
        ];
    }
}
