<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StudentTuitionUpdateRequest extends Model
{
    public const string ConcernMissingPayment = 'missing_payment';

    public const string ConcernDiscount = 'discount';

    public const string ConcernSubjectChange = 'subject_change';

    public const string ConcernOther = 'other';

    public const string StatusPending = 'pending';

    public const string StatusInReview = 'in_review';

    public const string StatusResolved = 'resolved';

    public const string StatusRejected = 'rejected';

    protected $fillable = [
        'submitted_by_user_id', 'student_id', 'student_enrollment_id', 'student_tuition_id',
        'school_year', 'semester', 'concern_type', 'receipt_number', 'details', 'status',
        'reviewed_by_user_id', 'reviewed_at', 'resolution_note', 'resolved_transaction_id',
        'tuition_adjustment_id', 'resolved_at', 'open_key',
    ];

    protected $attributes = ['status' => self::StatusPending];

    /** @return list<string> */
    public static function concernTypes(): array
    {
        return [self::ConcernMissingPayment, self::ConcernDiscount, self::ConcernSubjectChange, self::ConcernOther];
    }

    /** @return list<string> */
    public static function openStatuses(): array
    {
        return [self::StatusPending, self::StatusInReview];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function tuition(): BelongsTo
    {
        return $this->belongsTo(StudentTuition::class, 'student_tuition_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function resolvedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'resolved_transaction_id');
    }

    public function tuitionAdjustment(): BelongsTo
    {
        return $this->belongsTo(TuitionAdjustment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(StudentTuitionUpdateRequestEvent::class)->orderBy('created_at');
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::openStatuses());
    }

    protected function casts(): array
    {
        return ['semester' => 'integer', 'reviewed_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
