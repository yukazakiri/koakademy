<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

final class AssessmentExport extends Model
{
    use BelongsToSchool;
    use HasUuids;

    public const array ACTIVE_STATUSES = ['pending', 'processing', 'cancelling'];

    public const array TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

    #[Override]
    protected $fillable = [
        'id', 'user_id', 'school_id', 'status', 'stage', 'filters', 'batch_id',
        'total_count', 'processed_count', 'completed_count', 'skipped_count',
        'failed_count', 'merged_parts', 'total_parts', 'percentage', 'message',
        'output_disk', 'output_path', 'output_name', 'report_path', 'error_code',
        'error_message', 'error_context', 'started_at', 'merge_dispatched_at',
        'cancel_requested_at', 'completed_at', 'failed_at', 'dismissed_at',
        'terminal_notified_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentExportItem::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'error_context' => 'array',
            'total_count' => 'integer',
            'processed_count' => 'integer',
            'completed_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'merged_parts' => 'integer',
            'total_parts' => 'integer',
            'percentage' => 'integer',
            'started_at' => 'immutable_datetime',
            'merge_dispatched_at' => 'immutable_datetime',
            'cancel_requested_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'terminal_notified_at' => 'immutable_datetime',
        ];
    }
}
