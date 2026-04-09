<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\RelationManagers;

use App\Services\GeneralSettingsService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CurrentClassesRelationManager extends RelationManager
{
    protected static string $relationship = 'classEnrollments';

    protected static ?string $title = 'Current Enrolled Classes';

    protected static ?string $modelLabel = 'Class';

    protected static ?string $pluralModelLabel = 'Classes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('class.subject_code')
            ->heading('Current Enrolled Classes & Teachers')
            ->description(function (): string {
                $generalSettingsService = app(GeneralSettingsService::class);
                $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
                $currentSemester = $generalSettingsService->getCurrentSemester();

                $count = $this->getOwnerRecord()->classEnrollments()
                    ->whereHas('class', function ($query) use ($currentSchoolYear, $currentSemester): void {
                        $schoolYearWithSpaces = $currentSchoolYear;
                        $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);
                        $query->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                            ->where('semester', $currentSemester);
                    })->count();

                return "Academic Period: {$currentSchoolYear} - Semester {$currentSemester} | Total Classes: {$count}";
            })
            ->defaultSort('class.subject_code')
            ->columns([
                Tables\Columns\TextColumn::make('class.subject_code')
                    ->label('Subject Code')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->tooltip('Click to copy subject code'),

                Tables\Columns\TextColumn::make('class.subject_title')
                    ->label('Subject Title')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (Model $record): ?string {
                        $class = $record->class;
                        if (! $class) {
                            return null;
                        }

                        // Get subject title using the same logic as in Classes model
                        if ($class->isShs()) {
                            return $class->shsSubject?->title ?? $class->subject_code;
                        }

                        return $class->subject?->title
                            ?? $class->subjectByCode?->title
                            ?? $class->subjectByCodeFallback?->title
                            ?? $class->subject_code;
                    })
                    ->state(function (Model $record): string {
                        $class = $record->class;
                        if (! $class) {
                            return 'N/A';
                        }

                        // Get subject title using the same logic as in Classes model
                        if ($class->isShs()) {
                            return $class->shsSubject?->title ?? $class->subject_code;
                        }

                        return $class->subject?->title
                            ?? $class->subjectByCode?->title
                            ?? $class->subjectByCodeFallback?->title
                            ?? $class->subject_code;
                    }),

                Tables\Columns\TextColumn::make('class.section')
                    ->label('Section')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('class.faculty.full_name')
                    ->label('Teacher')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->default('No Teacher Assigned')
                    ->icon('heroicon-m-user')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('class.faculty.departmentBelongsTo.name')->label('Department')
                    ->searchable()
                    ->default('N/A')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('class.faculty.email')
                    ->label('Teacher Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No email')
                    ->icon('heroicon-m-envelope')
                    ->iconColor('success')
                    ->url(fn (Model $record): ?string => $record->class?->faculty?->email ? 'mailto:'.$record->class->faculty->email : null),

                Tables\Columns\TextColumn::make('class_capacity')
                    ->label('Capacity')
                    ->state(function (Model $record): string {
                        $class = $record->class;
                        if (! $class) {
                            return 'N/A';
                        }

                        $current = $class->student_count ?? 0;
                        $max = $class->maximum_slots ?? 'N/A';

                        return "{$current} / {$max}";
                    })
                    ->badge()
                    ->color(function (Model $record): string {
                        $class = $record->class;
                        if (! $class || ! $class->maximum_slots) {
                            return 'gray';
                        }

                        $current = $class->student_count ?? 0;
                        $max = $class->maximum_slots;
                        $percentage = ($current / $max) * 100;

                        if ($percentage >= 90) {
                            return 'danger';
                        }
                        if ($percentage >= 75) {
                            return 'warning';
                        }

                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('class.classification')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => $state === 'shs' ? 'SHS' : 'College')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'shs' ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('enrollment_status')
                    ->label('Status')
                    ->state('Enrolled')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('classification')
                    ->label('Class Type')
                    ->relationship('class', 'classification')
                    ->options([
                        'college' => 'College',
                        'shs' => 'SHS',
                    ])
                    ->placeholder('All Types'),

                Tables\Filters\Filter::make('has_teacher')
                    ->label('Has Teacher Assigned')
                    ->query(fn (Builder $query): Builder => $query->whereHas('class.faculty')),

                Tables\Filters\Filter::make('no_teacher')
                    ->label('No Teacher Assigned')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('class.faculty')),
            ])
            ->headerActions([
                Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-m-arrow-path')
                    ->action(fn () => $this->resetTable()),
            ])
            ->actions([
                Action::make('view_class_details')
                    ->label('View Class')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Model $record): string => $record->class ? "/admin/classes/{$record->class->id}" : '#'
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record): bool => (bool) $record->class),

                Action::make('contact_teacher')
                    ->label('Email Teacher')
                    ->icon('heroicon-m-envelope')
                    ->url(fn (Model $record): ?string => $record->class?->faculty?->email ? 'mailto:'.$record->class->faculty->email : null
                    )
                    ->visible(fn (Model $record): bool => (bool) $record->class?->faculty?->email),
            ])
            ->emptyStateHeading('No Current Classes')
            ->emptyStateDescription('This student is not enrolled in any classes for the current academic period.')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->modifyQueryUsing(function (Builder $query) {
                $generalSettingsService = app(GeneralSettingsService::class);
                $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
                $currentSemester = $generalSettingsService->getCurrentSemester();

                $schoolYearWithSpaces = $currentSchoolYear;
                $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);

                return $query->whereHas('class', function ($subQuery) use ($schoolYearWithSpaces, $schoolYearNoSpaces, $currentSemester): void {
                    $subQuery->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                        ->where('semester', $currentSemester);
                })
                    ->with([
                        'class.subject',
                        'class.subjectByCode',
                        'class.subjectByCodeFallback',
                        'class.shsSubject',
                        'class.faculty',
                    ]);
            });
    }
}
