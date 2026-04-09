<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StudentStatusRecord extends Model
{
    use BelongsToSchool;

    protected $table = 'student_statuses';

    protected $fillable = [
        'student_id',
        'academic_year',
        'semester',
        'status',
        'school_id',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'semester' => 'integer',
            'status' => StudentStatus::class,
        ];
    }
}
