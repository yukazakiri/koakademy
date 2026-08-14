<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TuitionAdjustment extends Model
{
    protected $fillable = [
        'batch_id', 'actor_user_id', 'student_enrollment_id', 'student_tuition_id',
        'client_row_id', 'idempotency_key', 'source', 'reason', 'before_snapshot',
        'after_snapshot', 'configuration_snapshot', 'delivery_status',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TuitionAdjustmentBatch::class, 'batch_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function tuition(): BelongsTo
    {
        return $this->belongsTo(StudentTuition::class, 'student_tuition_id');
    }

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'configuration_snapshot' => 'array',
            'delivery_status' => 'array',
        ];
    }
}
