<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read User|null $user
 *
 * @method static Builder<static>|UserSetting newModelQuery()
 * @method static Builder<static>|UserSetting newQuery()
 * @method static Builder<static>|UserSetting query()
 *
 * @mixin \Eloquent
 */
final class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'semester', 'school_year_start', 'active_school_id'];

    /**
     * Get the user that owns the settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the active school the user is viewing/managing.
     */
    public function activeSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'active_school_id');
    }

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'school_year_start' => 'integer',
            'active_school_id' => 'integer',
        ];
    }
}
