<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicantCancellationReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * A registrar's decision to close an application.
 *
 * The student record carries the terminal status; this row explains why, what
 * the applicant was told, and whether the decision was later reversed.
 */
final class ApplicantCancellation extends Model
{
    #[Override]
    protected $fillable = [
        'student_id',
        'reason_code',
        'reason_label',
        'note',
        'email_subject',
        'email_body',
        'email_sent_at',
        'cancelled_by',
        'cancelled_at',
        'reverted_by',
        'reverted_at',
        'revert_reason',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return BelongsTo<User, $this> */
    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function reason(): ?ApplicantCancellationReason
    {
        return ApplicantCancellationReason::tryFrom((string) $this->reason_code);
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }

    public function isActive(): bool
    {
        return $this->reverted_at === null;
    }

    /**
     * Cancellations that have not been reversed.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('reverted_at');
    }

    /**
     * The moment the decision was made, tolerating a missing timestamp.
     */
    public function cancelledAtOrNull(): ?Carbon
    {
        return $this->cancelled_at;
    }

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'immutable_datetime',
            'reverted_at' => 'immutable_datetime',
            'email_sent_at' => 'immutable_datetime',
        ];
    }
}
