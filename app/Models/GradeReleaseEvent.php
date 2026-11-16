<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

final class GradeReleaseEvent extends Model
{
    #[Override]
    protected $fillable = [
        'student_id',
        'school_year',
        'semester',
        'event',
        'actor_id',
        'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array', 'semester' => 'integer'];
    }
}
