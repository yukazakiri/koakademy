<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClassPostSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_post_id',
        'student_id',
        'content',
        'attachments',
        'points',
        'feedback',
        'status',
        'submitted_at',
        'graded_at',
    ];

    public function classPost(): BelongsTo
    {
        return $this->belongsTo(ClassPost::class, 'class_post_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'points' => 'int',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }
}
