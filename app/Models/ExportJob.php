<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $filters_display
 * @property-read User|null $user
 *
 * @method static Builder<static>|ExportJob newModelQuery()
 * @method static Builder<static>|ExportJob newQuery()
 * @method static Builder<static>|ExportJob query()
 *
 * @mixin \Eloquent
 */
final class ExportJob extends Model
{
    protected $fillable = [
        'job_id',
        'user_id',
        'export_type',
        'filters',
        'format',
        'status',
        'file_content',
        'file_name',
        'error_message',
        'started_at',
        'completed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $fileContent, string $fileName): void
    {
        $this->update([
            'status' => 'completed',
            'file_content' => $fileContent,
            'file_name' => $fileName,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    protected function filtersDisplay(): Attribute
    {
        return Attribute::make(get: function (): string {
            $filters = $this->filters;
            $display = [];
            if (isset($filters['course_filter']) && $filters['course_filter'] !== 'all') {
                $display[] = 'Course: '.$filters['course_filter'];
            }

            if (isset($filters['year_level_filter']) && $filters['year_level_filter'] !== 'all') {
                $display[] = 'Year: '.$filters['year_level_filter'];
            }

            return $display === [] ? 'All Students' : implode(', ', $display);
        });
    }

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
