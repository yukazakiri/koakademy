<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClassPostType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class ClassPost extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'class_id',
        'title',
        'content',
        'type',
        'status',
        'priority',
        'start_date',
        'due_date',
        'progress_percent',
        'total_points',
        'assigned_faculty_id',
        'attachments',
    ];

    /**
     * Configure activity logging options for this model
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'type', 'class_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => "Post '{$this->title}' was {$eventName}")
            ->useLogName('class_posts');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function assignedFaculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'assigned_faculty_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'type' => ClassPostType::class,
            'status' => 'string',
            'priority' => 'string',
            'start_date' => 'date',
            'due_date' => 'date',
            'progress_percent' => 'int',
            'total_points' => 'int',
            'assigned_faculty_id' => 'string',
            'attachments' => 'array',
        ];
    }
}
