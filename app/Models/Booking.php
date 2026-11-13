<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * Listed explicitly because the model is guarded application-wide: an
     * empty allowlist would reject every column.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resource_booking_id',
        'booked_by',
        'start_datetime',
        'end_datetime',
        'purpose',
        'notes',
        'expected_attendees',
        'status',
        'total_cost',
        'approved_by',
        'approved_at',
        'approval_notes',
        'cancelled_at',
        'cancellation_reason',
        'additional_requirements',
    ];
}
