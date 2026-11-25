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
 * Audit record for tracked program shifts and block section transfers.
 *
 * @property int $id
 * @property int|null $school_id
 * @property int $student_enrollment_id
 * @property int $student_id
 * @property string $transfer_type
 * @property int|null $from_course_id
 * @property int|null $to_course_id
 * @property string|null $from_section
 * @property string|null $to_section
 * @property int $credited_subjects_count
 * @property int $reassigned_classes_count
 * @property int $unmatched_subjects_count
 * @property string|float $previous_tuition_total
 * @property string|float $new_tuition_total
 * @property string|float $fee_delta
 * @property array<string, mixed>|null $details
 * @property string $reason
 * @property int|null $performed_by
 *
 * @mixin \Eloquent
 */
final class EnrollmentTransferLog extends Model
{
    use BelongsToSchool;
    use LogsActivity;

    public const string TypeSectionTransfer = 'section_transfer';

    public const string TypeProgramShift = 'program_shift';

    #[Override]
    protected $table = 'enrollment_transfer_logs';

    #[Override]
    protected $fillable = [
        'school_id',
        'student_enrollment_id',
        'student_id',
        'transfer_type',
        'from_course_id',
        'to_course_id',
        'from_section',
        'to_section',
        'credited_subjects_count',
        'reassigned_classes_count',
        'unmatched_subjects_count',
        'previous_tuition_total',
        'new_tuition_total',
        'fee_delta',
        'details',
        'reason',
        'performed_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['transfer_type', 'from_course_id', 'to_course_id', 'from_section', 'to_section', 'fee_delta', 'reason'])
            ->logOnlyDirty()
            ->useLogName('enrollment_transfers');
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

    /** @return BelongsTo<Course, $this> */
    public function fromCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'from_course_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function toCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'to_course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected function casts(): array
    {
        return [
            'credited_subjects_count' => 'integer',
            'reassigned_classes_count' => 'integer',
            'unmatched_subjects_count' => 'integer',
            'previous_tuition_total' => 'decimal:2',
            'new_tuition_total' => 'decimal:2',
            'fee_delta' => 'decimal:2',
            'details' => 'array',
        ];
    }
}
