<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CurriculumImport extends Model
{
    protected $fillable = [
        'public_id', 'school_id', 'uploaded_by_user_id', 'confirmed_by_user_id',
        'course_id', 'filename', 'checksum', 'status', 'draft', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['draft' => 'array', 'confirmed_at' => 'datetime'];
    }
}
