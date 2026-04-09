<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Event extends Model implements Eventable
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'category',
        'location',
        'start_datetime',
        'end_datetime',
        'is_all_day',
        'recurrence_type',
        'recurrence_data',
        'recurrence_end_date',
        'max_attendees',
        'requires_rsvp',
        'allow_guests',
        'status',
        'visibility',
        'custom_fields',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'recurrence_end_date' => 'datetime',
        'is_all_day' => 'boolean',
        'requires_rsvp' => 'boolean',
        'allow_guests' => 'boolean',
        'recurrence_data' => 'array',
        'custom_fields' => 'array',
    ];

    /**
     * Get the user who created the event.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all RSVPs for this event.
     */
    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    /**
     * Get all reminders for this event.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }

    /**
     * Get attending RSVPs.
     */
    public function attendingRsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class)->where('response', 'attending');
    }

    /**
     * Get the total number of attendees (including guests).
     */
    public function totalAttendees(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendingRsvps()
                ->selectRaw('SUM(1 + guest_count) as total')
                ->value('total') ?? 0
        );
    }

    /**
     * Check if the event has available spots.
     */
    public function hasAvailableSpots(): bool
    {
        if (! $this->max_attendees) {
            return true;
        }

        return $this->total_attendees < $this->max_attendees;
    }

    /**
     * Get available spots count.
     */
    public function availableSpots(): int
    {
        if (! $this->max_attendees) {
            return PHP_INT_MAX;
        }

        return max(0, $this->max_attendees - $this->total_attendees);
    }

    /**
     * Check if the event is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->start_datetime->isFuture();
    }

    /**
     * Check if the event is currently happening.
     */
    public function isHappening(): bool
    {
        $now = Carbon::now();

        return $now->between($this->start_datetime, $this->end_datetime);
    }

    /**
     * Check if the event has ended.
     */
    public function hasEnded(): bool
    {
        return $this->end_datetime->isPast();
    }

    /**
     * Get the duration of the event in minutes.
     */
    public function durationInMinutes(): int
    {
        return (int) $this->start_datetime->diffInMinutes($this->end_datetime);
    }

    /**
     * Scope to filter events by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter active events.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter upcoming events.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>', now());
    }

    /**
     * Scope to filter events within a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_datetime', [$startDate, $endDate]);
    }

    /**
     * Scope to filter public events.
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Convert the Event to a CalendarEvent for Guava Calendar.
     * This method is required by the Eventable interface.
     */
    public function toCalendarEvent(): CalendarEvent
    {
        return CalendarEvent::make()
            ->title((string) $this->title)
            ->start($this->start_datetime)
            ->end($this->end_datetime)
            ->allDay((bool) $this->is_all_day)
            ->backgroundColor($this->getEventColor())
            ->textColor($this->getTextColor())
            ->url(route('filament.admin.resources.events.view', $this))
            ->extendedProp('description', (string) ($this->description ?? ''))
            ->extendedProp('location', (string) ($this->location ?? ''))
            ->extendedProp('type', (string) ($this->type ?? 'other'))
            ->extendedProp('category', (string) ($this->category ?? 'academic'))
            ->extendedProp('status', (string) ($this->status ?? 'active'))
            ->extendedProp('visibility', (string) ($this->visibility ?? 'public'))
            ->extendedProp('requires_rsvp', (bool) $this->requires_rsvp)
            ->extendedProp('max_attendees', (int) ($this->max_attendees ?? 0))
            ->extendedProp('total_attendees', (int) $this->total_attendees)
            ->extendedProp('creator_name', (string) ($this->creator?->name ?? 'Unknown'))
            ->extendedProp('model_id', (int) $this->id)
            ->extendedProp('model_type', self::class);
    }

    /**
     * Get the background color for the event based on type and category.
     */
    private function getEventColor(): string
    {
        return match ($this->type) {
            'academic_calendar' => '#10b981', // green
            'resource_booking' => '#f59e0b',  // amber
            default => match ($this->category) {
                'academic' => '#3b82f6',      // blue
                'administrative' => '#8b5cf6', // purple
                'extracurricular' => '#ec4899', // pink
                'social' => '#6366f1',        // indigo
                'sports' => '#ef4444',        // red
                'cultural' => '#f97316',      // orange
                'holiday' => '#22c55e',       // green
                default => '#6b7280',         // gray
            }
        };
    }

    /**
     * Get the text color for the event (always white for good contrast).
     */
    private function getTextColor(): string
    {
        return '#ffffff';
    }
}
