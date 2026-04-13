<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Tables;

use App\Models\Department;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['school'])->withCount('subjects'))
            ->defaultSort('code')
            ->striped()
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record): ?string => filled($record->curriculum_year)
                        ? "Curriculum: {$record->curriculum_year}"
                        : null),
                TextColumn::make('title')
                    ->label('Program')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('department')
                    ->label('Department')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('school.name')
                    ->label('School')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('units')
                    ->label('Units')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('subjects_count')
                    ->label('Subjects')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('lec_per_unit')
                    ->label('Lec / unit')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lab_per_unit')
                    ->label('Lab / unit')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('curriculum_year')
                    ->label('Curriculum')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('miscelaneous')
                    ->label('Misc. fee')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All programs')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                SelectFilter::make('department')
                    ->label('Department')
                    ->options(fn (): array => Department::query()
                        ->orderBy('name')
                        ->pluck('name', 'code')
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
