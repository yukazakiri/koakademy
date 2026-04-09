<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Classes $class
 * @property-read Faculty|null $faculty
 * @property-read Schedule|null $schedule
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ClassAttendanceRecord> $records
 * @property array<string, int>|null $summary
 */
final class ClassAttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'schedule_id',
        'session_date',
        'starts_at',
        'ends_at',
        'taken_by',
        'topic',
        'notes',
        'is_locked',
        'locked_at',
        'is_no_meeting',
        'no_meeting_reason',
        'summary',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ClassAttendanceRecord::class, 'class_attendance_session_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'taken_by');
    }

    /**
     * @param  Builder<ClassAttendanceSession>  $query
     * @return Builder<ClassAttendanceSession>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('session_date')->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'starts_at' => 'datetime:H:i:s',
            'ends_at' => 'datetime:H:i:s',
            'locked_at' => 'datetime',
            'summary' => 'array',
            'is_locked' => 'boolean',
            'is_no_meeting' => 'boolean',
        ];
    }
}
