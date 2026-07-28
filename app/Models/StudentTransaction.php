<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Class StudentTransaction
 *
 * @property-read Student|null $student
 * @property-read Transaction|null $transaction
 *
 * @method static Builder<static>|StudentTransaction newModelQuery()
 * @method static Builder<static>|StudentTransaction newQuery()
 * @method static Builder<static>|StudentTransaction query()
 *
 * @mixin \Eloquent
 */
final class StudentTransaction extends Model
{
    #[Override]
    protected $table = 'student_transactions';

    #[Override]
    protected $fillable = [
        'student_id',
        'student_enrollment_id',
        'transaction_id',
        'amount',
        'status',
        'idempotency_key',
    ];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id')
            ->withDefault();
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'student_enrollment_id' => 'integer',
            'transaction_id' => 'integer',
            'amount' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
