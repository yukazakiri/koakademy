<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'student_transaction_id',
        'student_id',
        'student_enrollment_id',
        'student_tuition_id',
        'charge_category',
        'target_type',
        'amount',
        'notes',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function studentTransaction(): BelongsTo
    {
        return $this->belongsTo(StudentTransaction::class, 'student_transaction_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
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
            'amount' => 'float',
        ];
    }
}
