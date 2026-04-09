<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Tables;

use App\Models\Classes;
use App\Models\Faculty;
use App\Services\ClassAssignmentService;
use App\Services\GeneralSettingsService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FacultiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_url')
                    ->label('Avatar')
                    ->circular(),
                TextColumn::make('faculty_id_number')
                    ->label('Faculty ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('department')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('gender')
                    ->toggleable(),
                TextColumn::make('age')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('birth_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone_number')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('office_hours')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address_line1')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->searchable(),
                TextColumn::make('current_classes_count')
                    ->label('Current Classes')
                    ->getStateUsing(function ($record) {
                        $generalSettingsService = app(GeneralSettingsService::class);
                        $schoolYearWithSpaces = $generalSettingsService->getCurrentSchoolYearString();
                        $schoolYearNoSpaces = str_replace(' ', '', $schoolYearWithSpaces);
                        $semester = $generalSettingsService->getCurrentSemester();

                        return Classes::query()->whereRaw('faculty_id::text = ?', [$record->id])
                            ->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                            ->where('semester', $semester)
                            ->count();
                    })
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'on_leave' => 'On Leave',
                    ]),
                SelectFilter::make('department')
                    ->options(fn () => Faculty::query()->whereNotNull('department')
                        ->distinct()
                        ->pluck('department', 'department')
                        ->toArray()),
                Filter::make('has_current_classes')
                    ->label('Has Current Classes')
                    ->query(function (Builder $builder): Builder {
                        $generalSettingsService = app(GeneralSettingsService::class);
                        $schoolYearWithSpaces = $generalSettingsService->getCurrentSchoolYearString();
                        $schoolYearNoSpaces = str_replace(' ', '', $schoolYearWithSpaces);
                        $semester = $generalSettingsService->getCurrentSemester();

                        return $builder->whereExists(function ($subQuery) use ($schoolYearWithSpaces, $schoolYearNoSpaces, $semester): void {
                            $subQuery->select(DB::raw(1))
                                ->from('classes')
                                ->whereRaw('classes.faculty_id::text = faculty.id::text')
                                ->whereIn('classes.school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                                ->where('classes.semester', $semester);
                        });
                    }),
                Filter::make('no_current_classes')
                    ->label('No Current Classes')
                    ->query(function (Builder $builder): Builder {
                        $generalSettingsService = app(GeneralSettingsService::class);
                        $schoolYearWithSpaces = $generalSettingsService->getCurrentSchoolYearString();
                        $schoolYearNoSpaces = str_replace(' ', '', $schoolYearWithSpaces);
                        $semester = $generalSettingsService->getCurrentSemester();

                        return $builder->whereNotExists(function ($subQuery) use ($schoolYearWithSpaces, $schoolYearNoSpaces, $semester): void {
                            $subQuery->select(DB::raw(1))
                                ->from('classes')
                                ->whereRaw('classes.faculty_id::text = faculty.id::text')
                                ->whereIn('classes.school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                                ->where('classes.semester', $semester);
                        });
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('assignClasses')
                    ->label('Assign Classes')
                    ->icon('heroicon-o-academic-cap')
                    ->color('info')
                    ->form([
                        Select::make('class_ids')
                            ->label('Select Classes to Assign')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => app(ClassAssignmentService::class)->getUnassignedClassOptions())
                            ->hint('Only showing unassigned classes for current academic period'),
                    ])
                    ->action(function (array $data, Faculty $faculty): void {
                        if (! empty($data['class_ids'])) {
                            $count = app(ClassAssignmentService::class)->assignClassesToFaculty(
                                $data['class_ids'],
                                (string) $faculty->id
                            );

                            Notification::make()
                                ->title('Classes Assigned Successfully')
                                ->body(sprintf('Assigned %d class(es) to %s', $count, $faculty->full_name))
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('updateFacultyIdNumber')
                    ->label('Update Faculty ID Number')
                    ->icon('heroicon-o-identification')
                    ->color('info')
                    ->form([
                        TextInput::make('new_faculty_id_number')
                            ->label('New Faculty ID Number')
                            ->required()
                            ->unique('faculty', 'faculty_id_number')
                            ->maxLength(255)
                            ->placeholder('Enter new faculty ID number')
                            ->helperText('This will update the faculty ID number displayed in the system'),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('This action will update the faculty ID number. The internal UUID will remain unchanged.')
                    ->action(function (array $data, Faculty $faculty): void {
                        try {
                            $oldIdNumber = $faculty->faculty_id_number;
                            $newIdNumber = $data['new_faculty_id_number'];

                            $faculty->update([
                                'faculty_id_number' => $newIdNumber,
                            ]);

                            Notification::make()
                                ->title('Faculty ID Number Updated Successfully')
                                ->body(sprintf("Faculty ID number updated from '%s' to '%s'", $oldIdNumber, $newIdNumber))
                                ->success()
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->title('Error Updating Faculty ID Number')
                                ->body('Failed to update faculty ID number: '.$exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
