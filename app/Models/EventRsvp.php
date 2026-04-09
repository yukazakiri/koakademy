<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventRsvp extends Model
{
    /** @use HasFactory<\Database\Factories\EventRsvpFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'response',
        'guest_count',
        'dietary_requirements',
        'special_requests',
        'notes',
        'responded_at',
        'checked_in',
        'checked_in_at',
        'custom_responses',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'checked_in' => 'boolean',
        'checked_in_at' => 'datetime',
        'custom_responses' => 'array',
    ];

    /**
     * Get the event this RSVP belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who made this RSVP.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the response is attending.
     */
    public function isAttending(): bool
    {
        return $this->response === 'attending';
    }

    /**
     * Check if the user has checked in.
     */
    public function hasCheckedIn(): bool
    {
        return $this->checked_in;
    }

    /**
     * Get the total number of people (user + guests).
     */
    public function getTotalPeopleAttribute(): int
    {
        return 1 + $this->guest_count;
    }

    /**
     * Scope to filter attending RSVPs.
     */
    public function scopeAttending($query)
    {
        return $query->where('response', 'attending');
    }

    /**
     * Scope to filter not attending RSVPs.
     */
    public function scopeNotAttending($query)
    {
        return $query->where('response', 'not_attending');
    }

    /**
     * Scope to filter pending RSVPs.
     */
    public function scopePending($query)
    {
        return $query->where('response', 'pending');
    }

    /**
     * Scope to filter checked-in RSVPs.
     */
    public function scopeCheckedIn($query)
    {
        return $query->where('checked_in', true);
    }
}
