<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentWorkflowEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property array<string, mixed> $result
 */
final class EnrollmentWorkflowEvent extends Model
{
    /** @use HasFactory<EnrollmentWorkflowEventFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'student_enrollment_id', 'enrollment_policy_snapshot_id', 'actor_id', 'event_type',
        'from_step_key', 'to_step_key', 'status', 'terminal_outcome', 'idempotency_key',
        'reason', 'result',
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

    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
