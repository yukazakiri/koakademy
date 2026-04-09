<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Tables;

use App\Models\ShsStrand;
use App\Models\ShsTrack;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ShsStudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_lrn')
                    ->label('LRN')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('LRN copied'),
                TextColumn::make('fullname')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('track.track_name')
                    ->label('Track')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('strand.strand_name')
                    ->label('Strand')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('success'),
                TextColumn::make('grade_level')
                    ->sortable(),
                TextColumn::make('gender')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student_contact')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('guardian_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birthdate')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('track_id')
                    ->label('Track')
                    ->options(fn (): array => ShsTrack::pluck('track_name', 'id')->toArray())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('strand_id')
                    ->label('Strand')
                    ->options(fn (): array => ShsStrand::pluck('strand_name', 'id')->toArray())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('grade_level')
                    ->options([
                        'Grade 11' => 'Grade 11',
                        'Grade 12' => 'Grade 12',
                    ]),
                SelectFilter::make('gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fullname');
    }
}
