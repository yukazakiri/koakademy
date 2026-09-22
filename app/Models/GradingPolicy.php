<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

final class GradingPolicy extends Model
{
    use HasFactory;

    #[Override]
    protected $fillable = [
        'school_id',
        'name',
        'active_version_id',
        'created_by',
    ];

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<GradingPolicyVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(GradingPolicyVersion::class, 'active_version_id');
    }

    /** @return HasMany<GradingPolicyVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(GradingPolicyVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
