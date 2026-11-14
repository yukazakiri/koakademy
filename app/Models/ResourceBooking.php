<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ResourceBooking extends Model
{
    /** @use HasFactory<\Database\Factories\ResourceBookingFactory> */
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
        'resource_type',
        'resource_name',
        'resource_description',
        'location',
        'capacity',
        'features',
        'hourly_rate',
        'availability_schedule',
        'requires_approval',
        'is_active',
        'booking_rules',
        'terms_and_conditions',
        'managed_by',
    ];
}
