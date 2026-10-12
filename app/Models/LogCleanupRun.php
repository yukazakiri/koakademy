<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LogCleanupRun extends Model
{
    protected $fillable = [
        'source', 'mode', 'status', 'requested_by', 'scheduled', 'cutoff_at',
        'upper_ids', 'deleted_records', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled' => 'boolean',
            'cutoff_at' => 'immutable_datetime',
            'upper_ids' => 'array',
            'deleted_records' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
