<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Schemas;

use Exception;
// use Filament\Infolists\Components\Actions;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class EventInfolist
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Event Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),

                        TextEntry::make('type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'event' => 'primary',
                                'academic_calendar' => 'success',
                                'resource_booking' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('category')
                            ->badge()
                            ->color('secondary'),

                        TextEntry::make('location')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->placeholder('No location specified'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'cancelled' => 'danger',
                                'postponed' => 'warning',
                                'completed' => 'gray',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Schedule')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('start_datetime')
                            ->label('Start')
                            ->dateTime('M j, Y g:i A')
                            ->icon(Heroicon::OutlinedClock),

                        TextEntry::make('end_datetime')
                            ->label('End')
                            ->dateTime('M j, Y g:i A')
                            ->icon(Heroicon::OutlinedClock),

                        IconEntry::make('is_all_day')
                            ->label('All Day')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheck)
                            ->falseIcon(Heroicon::OutlinedXMark),

                        TextEntry::make('duration')
                            ->label('Duration')
                            ->state(function ($record): string {
                                if ($record->is_all_day) {
                                    return 'All Day';
                                }

                                $minutes = $record->durationInMinutes();
                                $hours = floor($minutes / 60);
                                $mins = $minutes % 60;

                                if ($hours > 0) {
                                    return "{$hours}h ".($mins > 0 ? "{$mins}m" : '');
                                }

                                return "{$mins}m";
                            }),
                    ]),

                Section::make('Recurrence')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('recurrence_type')
                            ->label('Repeat')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'none' => 'No Repeat',
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                                default => ucfirst($state),
                            }),

                        TextEntry::make('recurrence_end_date')
                            ->label('Repeat Until')
                            ->date('M j, Y')
                            ->placeholder('Forever'),

                        KeyValueEntry::make('recurrence_data')
                            ->label('Recurrence Rules')
                            ->columnSpanFull()
                            ->visible(fn ($record): bool => $record->recurrence_type !== 'none'),
                    ])
                    ->visible(fn ($record): bool => $record->recurrence_type !== 'none'),

                Section::make('RSVP & Attendance')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('requires_rsvp')
                            ->label('Requires RSVP')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheck)
                            ->falseIcon(Heroicon::OutlinedXMark),

                        TextEntry::make('max_attendees')
                            ->label('Maximum Attendees')
                            ->placeholder('Unlimited'),

                        IconEntry::make('allow_guests')
                            ->label('Allow Guests')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheck)
                            ->falseIcon(Heroicon::OutlinedXMark),

                        TextEntry::make('total_attendees')
                            ->label('Current Attendees')
                            ->state(fn ($record) => $record->total_attendees)
                            ->suffix(fn ($record): string => $record->max_attendees ? " / {$record->max_attendees}" : ''),
                    ]),

                Section::make('Organizer')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('creator.name')
                            ->label('Created By')
                            ->icon(Heroicon::OutlinedUser),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y g:i A'),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        KeyValueEntry::make('custom_fields')
                            ->label('Custom Fields')
                            ->visible(fn ($record): bool => ! empty($record->custom_fields)),

                        TextEntry::make('notes')
                            ->label('Internal Notes')
                            ->placeholder('No internal notes')
                            ->visible(fn ($record): bool => ! empty($record->notes)),
                    ]),

                Actions::make([
                    Action::make('rsvp')
                        ->label('RSVP to Event')
                        ->icon(Heroicon::Calendar)
                        ->color('primary')
                        ->visible(fn ($record): bool => $record->requires_rsvp && $record->isUpcoming())
                        ->action(function ($record): void {
                            // Redirect to RSVP form or modal
                        }),

                    Action::make('export')
                        ->label('Export Event')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(function ($record): void {
                            // Export event details
                        }),
                ]),
            ]);
    }
}
