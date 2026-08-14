<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TuitionAdjustmentBatch extends Model
{
    protected $fillable = ['public_id', 'actor_user_id', 'source', 'status', 'recorded_count', 'duplicate_count', 'rejected_count'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(TuitionAdjustment::class, 'batch_id');
    }
}
