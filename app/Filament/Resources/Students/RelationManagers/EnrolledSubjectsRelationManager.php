<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\GradeEnum;
use App\Enums\SubjectEnrolledEnum;
use App\Filament\Exports\SubjectEnrollmentExporter;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
// use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
// use Filament\Tables\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
// use Filament\Tables\Actions\ActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
// use Filament\Tables\Actions\ExportAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
// use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;
use Livewire\Component as Livewire;

final class EnrolledSubjectsRelationManager extends RelationManager
{
    public ?array $data = [];

    protected static string $relationship = 'subjectEnrolled';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('classification')
                    ->options(SubjectEnrolledEnum::class)
                    ->live()
                    ->default('internal')
                    ->required()
                    ->afterStateUpdated(function ($set, $state): void {
                        if ($state === 'internal') {
                            $set('school_name', null);
                            $set('external_subject_code', null);
                            $set('external_subject_title', null);
                            $set('external_subject_units', null);
                            $set('credited_subject_id', null);
                        }
                        if ($state !== 'credited') {
                            $set('credited_subject_id', null);
                        }
                    })
                    ->enum(SubjectEnrolledEnum::class),

                TextInput::make('school_name')
                    ->label('Previous School Name')
                    ->visible(fn (Get $get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->required(fn (Get $get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->datalist(SubjectEnrollment::query()->distinct()->pluck('school_name')->toArray())
                    ->columnSpanFull(),

                TextInput::make('external_subject_code')
                    ->label('External Subject Code')
                    ->placeholder('Ex: CS101')
                    ->visible(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->required(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->maxLength(255),

                TextInput::make('external_subject_title')
                    ->label('External Subject Title')
                    ->placeholder('Ex: Introduction to Computer Science')
                    ->visible(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->required(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('external_subject_units')
                    ->label('External Subject Units')
                    ->numeric()
                    ->placeholder('Ex: 3')
                    ->visible(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->required(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED)
                    ->minValue(1)
                    ->maxValue(12),
                Select::make('subject_id')
                    ->label('Subject (from our curriculum)')
                    ->helperText(fn ($get): ?string => ($get('classification') === SubjectEnrolledEnum::CREDITED || $get('classification') === SubjectEnrolledEnum::NON_CREDITED) ? 'Optional: Select the equivalent subject from our curriculum to link this external subject.' : null)
                    ->options(function (Livewire $livewire, $get) {
                        $classification = $get('classification');

                        if ($classification === SubjectEnrolledEnum::NON_CREDITED) {
                            return Subject::query()->where('is_credited', false)
                                ->pluck('title', 'id');
                        }

                        $courseId = $livewire->ownerRecord->course_id;
                        $enrolledSubjectIds = $livewire->ownerRecord->subjectEnrolled
                            ->filter(fn ($subject): bool => $subject->grade === null ||
                                ($subject->grade >= 1.00 && $subject->grade <= 4.00) ||
                                $subject->grade >= 75)
                            ->pluck('subject_id')
                            ->toArray();
                        $builder = Subject::query()->whereNotIn('id', $enrolledSubjectIds)
                            ->where('course_id', $courseId);

                        return $builder->get()
                            ->mapWithKeys(fn ($subject): array => [
                                $subject->id => sprintf('%s - %s', $subject->code, $subject->title),
                            ])
                            ->filter();

                    })
                    ->loadingMessage('Loading subjects...')
                    ->noSearchResultsMessage('No subjects found.')
                    ->searchable()
                    ->required(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::INTERNAL)
                    ->nullable(),
                Select::make('credited_subject_id')
                    ->label('Credited Subject')
                    ->options(fn () => Subject::query()->where('is_credited', true)
                        ->get()
                        ->mapWithKeys(fn ($subject): array => [$subject->id => sprintf('%s - %s', $subject->code, $subject->title)]))
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->required()
                            ->columnSpanFull(),
                        // Select::make('course_id')
                        //     ->label('Course')
                        //     ->options(function () {
                        //         return \App\Models\Courses::pluck('title', 'id');
                        //     }),
                        TextInput::make('units')
                            ->numeric()
                            ->required(),
                        TextInput::make('lecture')
                            ->numeric()
                            ->required(),
                        TextInput::make('laboratory')
                            ->numeric(),
                        Hidden::make('is_credited')
                            ->default(1),
                    ])
                    ->createOptionUsing(fn ($data) => Subject::query()->create($data))
                    ->visible(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED)
                    ->required(fn ($get): bool => $get('classification') === SubjectEnrolledEnum::CREDITED)
                    ->nullable(),

                TextInput::make('grade')
                    ->numeric()
                    ->placeholder('Ex: 85.5')
                    ->label('Grade')
                    ->minValue(1.0)
                    ->maxValue(100.0)
                    ->step(0.01),

                Select::make('academic_year')
                    ->required()
                    ->label('Academic Year')
                    ->placeholder('Ex: 1st Year')
                    ->options([
                        1 => '1st Year',
                        2 => '2nd Year',
                        3 => '3rd Year',
                        4 => '4th Year',
                    ])
                    ->default(1),
                Select::make('school_year')
                    ->required()
                    ->placeholder('Ex: 2024 - 2025')
                    ->label('School Year')
                    ->options(function (): array {
                        $startYear = 2000;
                        $endYear = now()->year;
                        $years = [];
                        for ($year = $startYear; $year <= $endYear; $year++) {
                            $years[$year.' - '.($year + 1)] =
                                $year.' - '.($year + 1);
                        }

                        return $years;

                    })
                    ->default(now()->year.' - '.(now()->year + 1)),
                Select::make('semester')
                    ->required()
                    ->placeholder('Ex: 1st Semester')
                    ->label('Semester')
                    ->options([
                        1 => '1st Semester',
                        2 => '2nd Semester',
                        3 => 'Summer',
                    ])
                    ->default(1),
                Textarea::make('remarks')
                    ->placeholder('Any remarks about the student')
                    ->label('Remarks')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('grade')
            ->heading('List Of Enrolled Subjects')
            ->description(
                'List OF Subects that the Student Enrolled in the course, filtered by year and semester'
            )
            ->deferLoading()
            ->striped()
            ->columns([
                TextColumn::make('classification')
                    ->label('Classification')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        SubjectEnrolledEnum::CREDITED->value => 'success',
                        SubjectEnrolledEnum::NON_CREDITED->value => 'warning',
                        SubjectEnrolledEnum::INTERNAL->value => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('subject.code')
                    ->label('Subject Code')
                    ->searchable()
                    ->formatStateUsing(function ($record): string {
                        if (in_array($record->classification, [
                            SubjectEnrolledEnum::CREDITED->value,
                            SubjectEnrolledEnum::NON_CREDITED->value,
                        ]) && $record->external_subject_code) {
                            return $record->external_subject_code;
                        }

                        return $record->subject?->code ?? 'N/A';
                    })
                    ->description(fn ($record): ?string => in_array($record->classification, [
                        SubjectEnrolledEnum::CREDITED->value,
                        SubjectEnrolledEnum::NON_CREDITED->value,
                    ]) && $record->external_subject_code ? 'External: '.$record->school_name : null),
                TextColumn::make('subject.title')
                    ->label('Subject Title')
                    ->searchable()
                    ->formatStateUsing(function ($record): string {
                        if (in_array($record->classification, [
                            SubjectEnrolledEnum::CREDITED->value,
                            SubjectEnrolledEnum::NON_CREDITED->value,
                        ]) && $record->external_subject_title) {
                            return $record->external_subject_title;
                        }

                        return $record->subject?->title ?? 'N/A';
                    })
                    ->description(fn ($record): ?string => in_array($record->classification, [
                        SubjectEnrolledEnum::CREDITED->value,
                        SubjectEnrolledEnum::NON_CREDITED->value,
                    ]) && $record->external_subject_units ? $record->external_subject_units.' units' : null)
                    ->wrap(),
                TextColumn::make('creditedSubject.title')
                    ->label('Credited As')
                    ->searchable()
                    ->formatStateUsing(fn ($record): string => $record->credited_subject_id && $record->creditedSubject ? $record->creditedSubject->code.' - '.$record->creditedSubject->title : 'N/A')
                    ->visible(fn (): bool => true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('grade')
                    ->badge()
                    ->color(fn ($state): string => $state ? GradeEnum::fromGrade($state)->getColor() : 'gray')
                    ->formatStateUsing(function ($state): ?string {
                        if ($state === null) {
                            return null;
                        }

                        return number_format((float) $state, 2);
                    }),

                TextColumn::make('school_name')
                    ->label('School')
                    ->searchable()
                    ->visible(fn (): bool => true)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('N/A'),
                TextColumn::make('instructor')
                    ->label('Instructor Name')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('academic_year'),
                TextColumn::make('school_year'),
                TextColumn::make('semester'),
                TextColumn::make('subject.pre_riquisite')
                    ->label('Pre-Requisite')
                    ->badge()
                    ->icon('heroicon-o-check'),
            ])
            ->filters([
                SelectFilter::make('classification')
                    ->options(SubjectEnrolledEnum::class)
                    ->label('Classification'),
                SelectFilter::make('academic_year')
                    ->options([
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                    ])
                    ->label('Academic Year'),
                SelectFilter::make('semester')
                    ->options([
                        1 => '1st Semester',
                        2 => '2nd Semester',
                        3 => 'Summer',
                    ])
                    ->label('Semester'),
                SelectFilter::make('grade')
                    ->options(collect(GradeEnum::cases())->mapWithKeys(
                        fn (GradeEnum $gradeEnum): array => [$gradeEnum->value => $gradeEnum->getLabel()]
                    ))
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $enum = GradeEnum::from($data['value']);
                            $ranges = $enum->getGradeRanges();

                            return $query->where(function ($q) use ($ranges): void {
                                $q->whereBetween('grade', $ranges['point'])
                                    ->orWhereBetween('grade', $ranges['percentage']);
                            });
                        }
                    })
                    ->label('Grade'),
            ])
            ->headerActions([
                CreateAction::make()->after(function (array $data): void {
                    $this->setSessionData($data);
                }),

                ActionGroup::make([
                    // Export All Subjects
                    ExportAction::make('export_all')
                        ->label('All Subjects')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('warning')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-AllSubjects-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)),

                    Action::make('first_year_divider')
                        ->label('First Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export First Year - First Semester
                    ExportAction::make('export_1y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-1stYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 1)
                            ->where('semester', 1)),

                    // Export First Year - Second Semester
                    ExportAction::make('export_1y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-1stYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 1)
                            ->where('semester', 2)),

                    Action::make('second_year_divider')
                        ->label('Second Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export Second Year - First Semester
                    ExportAction::make('export_2y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-2ndYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 2)
                            ->where('semester', 1)),

                    // Export Second Year - Second Semester
                    ExportAction::make('export_2y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-2ndYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 2)
                            ->where('semester', 2)),

                    Action::make('third_year_divider')
                        ->label('Third Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export Third Year - First Semester
                    ExportAction::make('export_3y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-3rdYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 3)
                            ->where('semester', 1)),

                    // Export Third Year - Second Semester
                    ExportAction::make('export_3y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-3rdYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 3)
                            ->where('semester', 2)),

                    Action::make('fourth_year_divider')
                        ->label('Fourth Year')
                        ->disabled()
                        ->icon('heroicon-o-academic-cap'),

                    // Export Fourth Year - First Semester
                    ExportAction::make('export_4y_1s')
                        ->label('1st Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-4thYear-1stSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 4)
                            ->where('semester', 1)),

                    // Export Fourth Year - Second Semester
                    ExportAction::make('export_4y_2s')
                        ->label('2nd Semester')
                        ->icon('heroicon-o-document-arrow-down')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Student-4thYear-2ndSemester-'.now()->format('Y-m-d'))
                        ->modifyQueryUsing(fn (Builder $builder, $livewire) => $builder->where('student_id', $livewire->ownerRecord->id)
                            ->where('academic_year', 4)
                            ->where('semester', 2)),
                ])
                    ->label('Export Subjects')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->button(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->exporter(SubjectEnrollmentExporter::class)
                        ->fileName(fn (): string => 'Selected-Subjects-Export-'.now()->format('Y-m-d-H-i-s'))
                        ->formats([
                            'xlsx',
                            'csv',
                        ]),
                ]),
            ]);
    }

    private function setSessionData(array $data): void
    {
        Session::put(
            'enrolled_subjects_form_data',
            array_merge($data, [
                'subject_id' => null, // Reset unique fields
            ])
        );
    }
}
