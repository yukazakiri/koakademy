<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OnboardingDismissal extends Model
{
    /** @use HasFactory<\Database\Factories\OnboardingDismissalFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'feature_key',
        'dismissed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }
}
