<?php

declare(strict_types=1);

namespace App\Filament\Resources\Schools\Tables;

use App\Models\School;
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

final class SchoolsTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('School Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (School $record): ?string => $record->description ?
                        (mb_strlen((string) $record->description) > 100 ?
                            mb_substr((string) $record->description, 0, 100).'...' :
                            $record->description) : null
                    ),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('dean_name')
                    ->label('Dean')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No dean assigned')
                    ->description(fn (School $record): ?string => $record->dean_email),

                TextColumn::make('departments_count')
                    ->label('Departments')
                    ->counts('departments')
                    ->badge()
                    ->color('info')
                    ->suffix(' depts'),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->color('success')
                    ->suffix(' users'),

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
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All schools')
                    ->trueLabel('Active schools')
                    ->falseLabel('Inactive schools'),

                SelectFilter::make('has_departments')
                    ->label('Department Status')
                    ->placeholder('All schools')
                    ->options([
                        'with_departments' => 'Has departments',
                        'without_departments' => 'No departments',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with_departments' => $query->has('departments'),
                        'without_departments' => $query->doesntHave('departments'),
                        default => $query,
                    }),

                SelectFilter::make('has_users')
                    ->label('User Status')
                    ->placeholder('All schools')
                    ->options([
                        'with_users' => 'Has users',
                        'without_users' => 'No users',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with_users' => $query->has('users'),
                        'without_users' => $query->doesntHave('users'),
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
                    ->modalHeading('Delete School')
                    ->modalDescription('Are you sure you want to delete this school? This will remove all associated departments and unassign users from this school.')
                    ->modalSubmitActionLabel('Delete School'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Schools')
                        ->modalDescription('Are you sure you want to delete the selected schools? This will remove all associated departments and unassign users from these schools.')
                        ->modalSubmitActionLabel('Delete Schools'),
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
