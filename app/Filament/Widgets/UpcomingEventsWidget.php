<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Event;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class UpcomingEventsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return true;
    }

    public static function getWidgetConfig(): array
    {
        return [
            'title' => 'Upcoming Events',
            'description' => 'Quick view of upcoming events and activities',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Event::query()
                    ->active()
                    ->upcoming()
                    ->with(['creator', 'rsvps'])
                    ->limit(10)
            )
            ->heading('Upcoming Events')
            ->description('Next 10 upcoming events')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->location ? "📍 {$record->location}" : null),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'event' => 'primary',
                        'academic_calendar' => 'success',
                        'resource_booking' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'academic_calendar' => 'Academic',
                        'resource_booking' => 'Resource',
                        default => ucfirst($state),
                    }),

                TextColumn::make('start_datetime')
                    ->label('Date & Time')
                    ->formatStateUsing(function ($record): string {
                        $start = $record->start_datetime;
                        $now = Carbon::now();

                        // Show relative time for events in the next 24 hours
                        if ($start->diffInHours($now) < 24) {
                            return $start->diffForHumans().' • '.$start->format('g:i A');
                        }

                        return $start->format('M j, Y').' • '.($record->is_all_day ? 'All Day' : $start->format('g:i A'));
                    })
                    ->icon(Heroicon::OutlinedClock)
                    ->color(function ($record): string {
                        $hours = $record->start_datetime->diffInHours(Carbon::now());
                        if ($hours < 2) {
                            return 'danger';
                        }
                        if ($hours < 24) {
                            return 'warning';
                        }

                        return 'gray';
                    }),

                TextColumn::make('attendees')
                    ->label('Attendees')
                    ->state(function ($record): string {
                        if (! $record->requires_rsvp) {
                            return 'No RSVP';
                        }

                        $attending = $record->rsvps()->where('response', 'attending')->count();
                        $total = $record->rsvps()->count();

                        if ($record->max_attendees) {
                            return "{$attending} / {$record->max_attendees}";
                        }

                        return "{$attending} attending";
                    })
                    ->icon(Heroicon::OutlinedUsers)
                    ->color(function ($record): string {
                        if (! $record->requires_rsvp || ! $record->max_attendees) {
                            return 'gray';
                        }

                        $attending = $record->rsvps()->where('response', 'attending')->count();
                        $percentage = ($attending / $record->max_attendees) * 100;

                        if ($percentage >= 90) {
                            return 'danger';
                        }
                        if ($percentage >= 70) {
                            return 'warning';
                        }

                        return 'success';
                    }),

                IconColumn::make('requires_rsvp')
                    ->label('RSVP')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheck)
                    ->falseIcon(Heroicon::OutlinedXMark)
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('creator.name')
                    ->label('Organizer')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::Eye)
                    ->color('primary')
                    ->url(fn ($record): string => route('filament.admin.resources.events.view', $record)),

                Action::make('RSVP')
                    ->label('RSVP')
                    ->icon(Heroicon::Calendar)
                    ->color('primary')
                    ->visible(fn ($record): bool => $record->requires_rsvp && $record->hasAvailableSpots())
                    ->action(function ($record): void {
                        // Handle RSVP action - could redirect to RSVP form
                        redirect()->route('filament.admin.resources.events.view', $record);
                    }),
            ])
            ->emptyStateHeading('No Upcoming Events')
            ->emptyStateDescription('There are no upcoming events scheduled.')
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->striped()
            ->paginated(false);
    }
}
