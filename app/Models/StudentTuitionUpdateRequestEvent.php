<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StudentTuitionUpdateRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['student_tuition_update_request_id', 'actor_user_id', 'event', 'from_status', 'to_status', 'note', 'metadata'];

    public function tuitionUpdateRequest(): BelongsTo
    {
        return $this->belongsTo(StudentTuitionUpdateRequest::class, 'student_tuition_update_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
}
