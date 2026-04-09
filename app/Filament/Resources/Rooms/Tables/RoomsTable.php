<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rooms\Tables;

use App\Models\Room;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Room Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('class_code')
                    ->label('Class Code')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No class code'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('classes_count')
                    ->label('Active Classes')
                    ->counts('classes')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('schedules_count')
                    ->label('Scheduled Sessions')
                    ->counts('schedules')
                    ->alignCenter()
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
                    ->placeholder('All rooms')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->indicator('Status'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('toggleStatus')
                    ->label(fn (Room $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Room $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Room $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Room $record): string => $record->is_active ? 'Deactivate Room' : 'Activate Room')
                    ->modalDescription(fn (Room $record): string => $record->is_active
                        ? 'Are you sure you want to deactivate this room? It will no longer appear in class assignment options.'
                        : 'Are you sure you want to activate this room? It will be available for class assignments.')
                    ->action(function (Room $record): void {
                        $record->is_active = ! $record->is_active;
                        $record->save();

                        Notification::make()
                            ->title('Room status updated')
                            ->body("Room '{$record->name}' has been ".($record->is_active ? 'activated' : 'deactivated').'.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
