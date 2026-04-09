<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Tables;

use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class EventsTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Model $record): string => $record->description ?
                        \Illuminate\Support\Str::limit(strip_tags($record->description), 50) : ''
                    ),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'event' => 'primary',
                        'academic_calendar' => 'success',
                        'resource_booking' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'event' => 'Event',
                        'academic_calendar' => 'Academic',
                        'resource_booking' => 'Resource',
                        default => ucfirst($state),
                    }),

                TextColumn::make('category')
                    ->badge()
                    ->color('secondary')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->toggleable(),

                TextColumn::make('start_datetime')
                    ->label('Start')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->description(fn (Model $record): ?string => $record->is_all_day ? 'All Day' : null
                    ),

                TextColumn::make('end_datetime')
                    ->label('End')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('location')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->placeholder('No location')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'cancelled' => 'danger',
                        'postponed' => 'warning',
                        'completed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                IconColumn::make('requires_rsvp')
                    ->label('RSVP')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheck)
                    ->falseIcon(Heroicon::OutlinedXMark)
                    ->toggleable(),

                TextColumn::make('attendees_count')
                    ->label('Attendees')
                    ->state(function (Model $record): string {
                        $count = $record->total_attendees;
                        $max = $record->max_attendees;

                        if ($max) {
                            return "{$count}/{$max}";
                        }

                        return (string) $count;
                    })
                    ->icon(Heroicon::OutlinedUsers)
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'event' => 'General Event',
                        'academic_calendar' => 'Academic Calendar',
                        'resource_booking' => 'Resource Booking',
                    ])
                    ->multiple(),

                SelectFilter::make('category')
                    ->options([
                        'academic' => 'Academic',
                        'administrative' => 'Administrative',
                        'extracurricular' => 'Extracurricular',
                        'social' => 'Social',
                        'sports' => 'Sports',
                        'cultural' => 'Cultural',
                        'conference' => 'Conference',
                        'workshop' => 'Workshop',
                        'meeting' => 'Meeting',
                        'other' => 'Other',
                    ])
                    ->multiple(),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'cancelled' => 'Cancelled',
                        'postponed' => 'Postponed',
                        'completed' => 'Completed',
                    ])
                    ->multiple(),

                Filter::make('upcoming')
                    ->label('Upcoming Events')
                    ->query(fn (Builder $query): Builder => $query->upcoming())
                    ->toggle(),

                Filter::make('requires_rsvp')
                    ->label('RSVP Required')
                    ->query(fn (Builder $query): Builder => $query->where('requires_rsvp', true))
                    ->toggle(),

                Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('To Date'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['start_date'], fn ($query) => $query->where('start_datetime', '>=', $data['start_date'])
                        )
                        ->when($data['end_date'], fn ($query) => $query->where('end_datetime', '<=', $data['end_date'])
                        )),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('rsvp')
                        ->label('RSVP')
                        ->icon(Heroicon::Calendar)
                        ->color('primary')
                        ->visible(fn (Model $record): bool => $record->requires_rsvp && $record->isUpcoming()
                        )
                        ->action(function (Model $record): void {
                            // Handle RSVP action
                        }),
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->action(function (Model $record) {
                            $newEvent = $record->replicate();
                            $newEvent->title .= ' (Copy)';
                            $newEvent->created_by = auth()->id();
                            $newEvent->save();

                            return redirect()->route('filament.admin.resources.events.edit', $newEvent);
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_datetime', 'desc')
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
