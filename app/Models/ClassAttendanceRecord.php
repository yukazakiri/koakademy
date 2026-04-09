<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AttendanceStatus $status
 * @property-read ClassAttendanceSession $session
 * @property-read ClassEnrollment $enrollment
 * @property-read Student|null $student
 */
final class ClassAttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_attendance_session_id',
        'class_enrollment_id',
        'class_id',
        'student_id',
        'status',
        'remarks',
        'marked_by',
        'marked_at',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassAttendanceSession::class, 'class_attendance_session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class, 'class_enrollment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * @param  Builder<ClassAttendanceRecord>  $query
     * @return Builder<ClassAttendanceRecord>
     */
    public function scopeForClass(Builder $query, Classes $class): Builder
    {
        return $query->where('class_id', $class->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'marked_at' => 'datetime',
        ];
    }
}
