<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

final class EnrollmentRequirement extends Model
{
    public const string Pending = 'pending';

    public const string Verified = 'verified';

    public const string Waived = 'waived';

    #[Override]
    protected $fillable = [
        'student_enrollment_id',
        'enrollment_policy_snapshot_id',
        'requirement_key',
        'label',
        'description',
        'enforcement_step_key',
        'is_required',
        'status',
        'evidence_path',
        'evidence',
        'verified_by',
        'verified_at',
        'waived_by',
        'waived_at',
        'waiver_reason',
    ];

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<EnrollmentPolicySnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(EnrollmentPolicySnapshot::class, 'enrollment_policy_snapshot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function waivingActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'evidence' => 'array',
            'verified_at' => 'immutable_datetime',
            'waived_at' => 'immutable_datetime',
        ];
    }
}
