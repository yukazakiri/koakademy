<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OnboardingProgress extends Model
{
    use HasFactory;

    protected $table = 'onboarding_progress';

    protected $fillable = [
        'user_id',
        'variant',
        'completed_steps',
        'checklist_state',
        'started_at',
        'completed_at',
        'last_seen_at',
        'current_step_index',
        'is_dismissed',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'checklist_state' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_dismissed' => 'boolean',
        ];
    }
}
