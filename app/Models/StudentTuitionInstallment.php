<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StudentTuitionInstallment extends Model
{
    protected $fillable = ['student_tuition_id', 'term', 'sequence', 'percentage', 'amount', 'source'];

    public function tuition(): BelongsTo
    {
        return $this->belongsTo(StudentTuition::class, 'student_tuition_id');
    }

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'percentage' => 'decimal:2', 'amount' => 'decimal:2'];
    }
}
