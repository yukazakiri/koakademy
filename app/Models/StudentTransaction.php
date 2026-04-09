<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
    protected $table = 'student_transactions';

    protected $fillable = [
        'student_id',
        'transaction_id',
        'amount',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id')
            ->withDefault();
    }

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'transaction_id' => 'integer',
            'amount' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
