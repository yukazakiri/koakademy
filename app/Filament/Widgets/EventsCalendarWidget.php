<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Event;
use Carbon\WeekDay;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\CreateAction as CalendarCreateAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class EventsCalendarWidget extends CalendarWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected CalendarViewType $calendarView = CalendarViewType::DayGridMonth;

    protected ?string $locale = 'en';

    protected WeekDay $firstDay = WeekDay::Sunday;

    protected bool $dayMaxEvents = false;

    // Enable interactions
    protected bool $eventClickEnabled = true;

    protected bool $dateClickEnabled = true;

    /**
     * Check if the user can view the calendar widget.
     */
    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Event::class) ?? true;
    }

    /**
     * Configuration for the widget.
     */
    public static function getWidgetConfig(): array
    {
        return [
            'title' => 'Events Calendar',
            'description' => 'Interactive calendar view of all events and activities',
        ];
    }

    /**
     * Get the display title for the widget.
     */
    public function getHeading(): string
    {
        return 'Events Calendar';
    }

    /**
     * Get the display description for the widget.
     */
    public function getDescription(): string
    {
        return 'View and manage events in calendar format. Click on dates to create events, click on events to view details.';
    }

    /**
     * Create Event Action for the calendar using Guava's CreateAction
     */
    public function createEventAction(): CalendarCreateAction
    {
        return $this->createAction(Event::class)
            ->slideOver()
            ->form([
                TextInput::make('title')
                    ->label('Event Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->label('Description')
                    ->maxLength(1000)
                    ->columnSpanFull(),

                Select::make('type')
                    ->label('Event Type')
                    ->options([
                        'meeting' => 'Meeting',
                        'deadline' => 'Deadline',
                        'exam' => 'Exam',
                        'holiday' => 'Holiday',
                        'workshop' => 'Workshop',
                        'conference' => 'Conference',
                        'other' => 'Other',
                    ])
                    ->default('other')
                    ->required(),

                Select::make('category')
                    ->label('Category')
                    ->options([
                        'academic' => 'Academic',
                        'administrative' => 'Administrative',
                        'social' => 'Social',
                        'personal' => 'Personal',
                        'system' => 'System',
                    ])
                    ->default('academic')
                    ->required(),

                DateTimePicker::make('start_datetime')
                    ->label('Start Date & Time')
                    ->required()
                    ->default(fn () => session('calendar_selected_date', now()))
                    ->seconds(false),

                DateTimePicker::make('end_datetime')
                    ->label('End Date & Time')
                    ->required()
                    ->default(fn () => session('calendar_selected_date', now())->addHour())
                    ->seconds(false)
                    ->after('start_datetime'),

                Toggle::make('is_all_day')
                    ->label('All Day Event')
                    ->default(false),

                TextInput::make('location')
                    ->label('Location')
                    ->maxLength(255),

                Toggle::make('requires_rsvp')
                    ->label('Requires RSVP')
                    ->default(false),
            ])
            ->mutateFormDataUsing(fn (array $data): array => [
                ...$data,
                'created_by' => auth()->check() ? auth()->id() : 1,
                'status' => 'active',
                'visibility' => 'public',
            ])
            ->successNotification(
                Notification::make()
                    ->title('Event Created Successfully')
                    ->body('Your event has been created and added to the calendar.')
                    ->success()
            )
            ->authorize('create', Event::class);
    }

    /**
     * Get events for the calendar.
     * This method is called by Guava Calendar to fetch events for the visible date range.
     */
    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return Event::query()
            ->active()
            ->where(function ($query) use ($info): void {
                $query->whereBetween('start_datetime', [$info->start, $info->end])
                    ->orWhereBetween('end_datetime', [$info->start, $info->end])
                    ->orWhere(function ($subQuery) use ($info): void {
                        $subQuery->where('start_datetime', '<=', $info->start)
                            ->where('end_datetime', '>=', $info->end);
                    });
            })
            ->with('creator', 'rsvps')
            ->orderBy('start_datetime');
    }

    /**
     * Handle event clicks - redirect to event view
     */
    protected function onEventClick(EventClickInfo $info, Model $event, ?string $action = null): void
    {
        if (auth()->user()?->can('view', $event)) {
            // Use Filament's redirect helper for better navigation
            $this->redirect(route('filament.admin.resources.events.view', ['record' => $event]));
        } else {
            Notification::make()
                ->title('Access Denied')
                ->body('You do not have permission to view this event.')
                ->danger()
                ->send();
        }
    }

    /**
     * Handle date clicks - open create event modal
     */
    protected function onDateClick(DateClickInfo $info): void
    {
        if (auth()->user()?->can('create', Event::class)) {
            // Store the clicked date for use in the create form
            session(['calendar_selected_date' => $info->date]);

            // Dispatch browser event to open the create modal
            $this->dispatch('open-event-create-modal', date: $info->date);
        }
    }
}
