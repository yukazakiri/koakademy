<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tracked Add / Drop / Swap subject request with tuition adjustment and COL slip reference.
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $reference_number
 * @property int $student_enrollment_id
 * @property int $student_id
 * @property string $change_type
 * @property string $status
 * @property int|null $added_subject_id
 * @property int|null $added_class_id
 * @property string|null $added_subject_code
 * @property string|null $added_subject_title
 * @property int|null $dropped_subject_enrollment_id
 * @property int|null $dropped_subject_id
 * @property string|null $dropped_subject_code
 * @property string|null $dropped_subject_title
 * @property int $units_delta
 * @property string|float $tuition_delta
 * @property string|float $previous_tuition_total
 * @property string|float $new_tuition_total
 * @property bool $within_window
 * @property string $reason
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 *
 * @mixin \Eloquent
 */
final class EnrollmentLoadChange extends Model
{
    use BelongsToSchool;
    use LogsActivity;

    public const string TypeAdd = 'add';

    public const string TypeDrop = 'drop';

    public const string TypeSwap = 'swap';

    public const string StatusPending = 'pending';

    public const string StatusApproved = 'approved';

    public const string StatusRejected = 'rejected';

    #[Override]
    protected $table = 'enrollment_load_changes';

    #[Override]
    protected $fillable = [
        'school_id',
        'reference_number',
        'student_enrollment_id',
        'student_id',
        'change_type',
        'status',
        'added_subject_id',
        'added_class_id',
        'added_subject_code',
        'added_subject_title',
        'dropped_subject_enrollment_id',
        'dropped_subject_id',
        'dropped_subject_code',
        'dropped_subject_title',
        'units_delta',
        'tuition_delta',
        'previous_tuition_total',
        'new_tuition_total',
        'within_window',
        'reason',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['change_type', 'status', 'units_delta', 'tuition_delta', 'reason', 'rejection_reason'])
            ->logOnlyDirty()
            ->useLogName('enrollment_load_changes');
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

    /** @return BelongsTo<Subject, $this> */
    public function addedSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'added_subject_id');
    }

    /** @return BelongsTo<Classes, $this> */
    public function addedClass(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'added_class_id');
    }

    /** @return BelongsTo<Subject, $this> */
    public function droppedSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'dropped_subject_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    protected function casts(): array
    {
        return [
            'units_delta' => 'integer',
            'tuition_delta' => 'decimal:2',
            'previous_tuition_total' => 'decimal:2',
            'new_tuition_total' => 'decimal:2',
            'within_window' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
