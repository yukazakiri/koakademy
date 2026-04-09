<?php

declare(strict_types=1);

namespace App\Filament\Resources\Departments\Tables;

use App\Models\Department;
use DB;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class DepartmentsTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Department Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Department $record): ?string => $record->description ?
                        (mb_strlen((string) $record->description) > 80 ?
                            mb_substr((string) $record->description, 0, 80).'...' :
                            $record->description) : null
                    ),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->description(fn (Department $record): ?string => $record->school?->code),

                TextColumn::make('head_name')
                    ->label('Department Head')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No head assigned')
                    ->description(fn (Department $record): ?string => $record->head_email),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->color('success')
                    ->suffix(' users'),

                TextColumn::make('faculty_count')
                    ->label('Faculty')
                    ->getStateUsing(fn (Department $record): int => $record->getFacultyCount())
                    ->badge()
                    ->color('info')
                    ->suffix(' faculty'),

                TextColumn::make('courses_count')
                    ->label('Courses')
                    ->getStateUsing(fn (Department $record): int => $record->getCoursesCount())
                    ->badge()
                    ->color('warning')
                    ->suffix(' courses'),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->placeholder('No location')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('No phone')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('No email')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (bool $state): string => $state ? 'Active' : 'Inactive'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All departments')
                    ->trueLabel('Active departments')
                    ->falseLabel('Inactive departments'),

                SelectFilter::make('has_users')
                    ->label('User Status')
                    ->placeholder('All departments')
                    ->options([
                        'with_users' => 'Has users',
                        'without_users' => 'No users',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with_users' => $query->has('users'),
                        'without_users' => $query->doesntHave('users'),
                        default => $query,
                    }),

                SelectFilter::make('has_faculty')
                    ->label('Faculty Status')
                    ->placeholder('All departments')
                    ->options([
                        'with_faculty' => 'Has faculty',
                        'without_faculty' => 'No faculty',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with_faculty' => $query->where(function ($q): void {
                            $q->whereExists(function ($subQuery): void {
                                $subQuery->select(DB::raw(1))
                                    ->from('faculty')
                                    ->whereColumn('faculty.department', 'departments.code')
                                    ->orWhereColumn('faculty.department', 'departments.name');
                            });
                        }),
                        'without_faculty' => $query->where(function ($q): void {
                            $q->whereNotExists(function ($subQuery): void {
                                $subQuery->select(DB::raw(1))
                                    ->from('faculty')
                                    ->whereColumn('faculty.department', 'departments.code')
                                    ->orWhereColumn('faculty.department', 'departments.name');
                            });
                        }),
                        default => $query,
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Department')
                    ->modalDescription('Are you sure you want to delete this department? This will unassign all users from this department.')
                    ->modalSubmitActionLabel('Delete Department'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Departments')
                        ->modalDescription('Are you sure you want to delete the selected departments? This will unassign all users from these departments.')
                        ->modalSubmitActionLabel('Delete Departments'),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s') // Refresh data every 30 seconds
            ->deferLoading()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
