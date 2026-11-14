<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EventReminder extends Model
{
    /** @use HasFactory<\Database\Factories\EventReminderFactory> */
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
        'event_id',
        'user_id',
        'reminder_type',
        'minutes_before',
        'status',
        'scheduled_at',
        'sent_at',
        'message',
        'delivery_data',
        'failure_reason',
        'retry_count',
    ];
}
