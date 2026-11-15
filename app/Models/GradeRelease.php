<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

final class GradeRelease extends Model
{
    #[Override]
    protected $table = 'grade_releases';

    #[Override]
    protected $fillable = [
        'student_id',
        'school_year',
        'semester',
        'snapshot',
        'revision',
        'published_by',
        'published_at',
        'notification_status',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'revision' => 'integer',
            'snapshot' => 'array',
            'notification_status' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
