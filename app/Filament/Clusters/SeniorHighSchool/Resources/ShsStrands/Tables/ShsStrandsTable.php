<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Tables;

use App\Models\ShsTrack;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ShsStrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('strand_name')
                    ->label('Strand Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('track.track_name')
                    ->label('Track')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('subjects_count')
                    ->label('Subjects')
                    ->counts('subjects')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('description')
                    ->limit(50)
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
            ->defaultSort('strand_name');
    }
}
