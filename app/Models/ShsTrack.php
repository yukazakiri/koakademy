<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; // Added
use Illuminate\Database\Eloquent\Relations\HasMany;

// Added for clarity
/**
 * Class ShsTrack
 *
 * @property-read Collection<int, ShsStrand> $strands
 * @property-read int|null $strands_count
 * @property-read Collection<int, ShsStudent> $students
 * @property-read int|null $students_count
 *
 * @method static Builder<static>|ShsTrack newModelQuery()
 * @method static Builder<static>|ShsTrack newQuery()
 * @method static Builder<static>|ShsTrack query()
 *
 * @mixin \Eloquent
 */
final class ShsTrack extends Model
{
    use HasFactory; // Added

    protected $table = 'shs_tracks';

    protected $fillable = [
        'track_name',
        'description',
    ];

    /**
     * Get the strands associated with the SHS track.
     */
    public function strands(): HasMany
    {
        return $this->hasMany(ShsStrand::class, 'track_id');
    }

    /**
     * Get all students enrolled in this track.
     * This relationship assumes that students are directly linked to a track,
     * and also can be linked via a strand.
     */
    public function students(): HasMany
    {
        return $this->hasMany(ShsStudent::class, 'track_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
