<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * FIFO waitlist entry when class sections for a subject reach seat capacity.
 *
 * @property int $id
 * @property int|null $school_id
 * @property int $student_enrollment_id
 * @property int $student_id
 * @property int $subject_enrollment_id
 * @property int $subject_id
 * @property int|null $preferred_class_id
 * @property string $school_year
 * @property int $semester
 * @property int $position
 * @property string $status
 * @property string|null $notes
 * @property int|null $added_by
 * @property int|null $promoted_by
 * @property int|null $promoted_class_id
 * @property \Illuminate\Support\Carbon|null $promoted_at
 *
 * @method static Builder<static>|EnrollmentClassWaitlist waiting()
 *
 * @mixin \Eloquent
 */
final class EnrollmentClassWaitlist extends Model
{
    use BelongsToSchool;
    use LogsActivity;

    public const string StatusWaiting = 'waiting';

    public const string StatusPromoted = 'promoted';

    public const string StatusCancelled = 'cancelled';

    #[Override]
    protected $table = 'enrollment_class_waitlists';

    #[Override]
    protected $fillable = [
        'school_id',
        'student_enrollment_id',
        'student_id',
        'subject_enrollment_id',
        'subject_id',
        'preferred_class_id',
        'school_year',
        'semester',
        'position',
        'status',
        'notes',
        'added_by',
        'promoted_by',
        'promoted_class_id',
        'promoted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'position', 'preferred_class_id', 'promoted_class_id', 'notes'])
            ->logOnlyDirty()
            ->useLogName('enrollment_class_waitlists');
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return BelongsTo<SubjectEnrollment, $this> */
    public function subjectEnrollment(): BelongsTo
    {
        return $this->belongsTo(SubjectEnrollment::class, 'subject_enrollment_id');
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /** @return BelongsTo<Classes, $this> */
    public function preferredClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'preferred_class_id');
    }

    /** @return BelongsTo<Classes, $this> */
    public function promotedClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'promoted_class_id');
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** @return BelongsTo<User, $this> */
    public function promotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::StatusWaiting);
    }

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'position' => 'integer',
            'promoted_at' => 'datetime',
        ];
    }
}
