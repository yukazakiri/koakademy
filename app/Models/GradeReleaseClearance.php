<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

final class GradeReleaseClearance extends Model
{
    #[Override]
    protected $fillable = [
        'student_id',
        'school_year',
        'semester',
        'is_cleared',
        'remarks',
        'cleared_by',
        'cleared_at',
        'updated_by',
    ];

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    protected function casts(): array
    {
        return ['semester' => 'integer', 'is_cleared' => 'boolean', 'cleared_at' => 'datetime'];
    }
}
