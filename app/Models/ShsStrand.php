<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ShsStrand
 *
 * @property-read Collection<int, ShsStudent> $students
 * @property-read int|null $students_count
 * @property-read ShsTrack|null $track
 *
 * @method static Builder<static>|ShsStrand newModelQuery()
 * @method static Builder<static>|ShsStrand newQuery()
 * @method static Builder<static>|ShsStrand query()
 *
 * @mixin \Eloquent
 */
final class ShsStrand extends Model
{
    use HasFactory;

    protected $table = 'shs_strands';

    protected $fillable = [
        'strand_name',
        'description',
        'track_id',
    ];

    /**
     * Get the track that this strand belongs to.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(ShsTrack::class, 'track_id');
    }

    /**
     * Get the students enrolled in this strand.
     */
    public function students(): HasMany
    {
        return $this->hasMany(ShsStudent::class, 'strand_id');
    }

    /**
     * Get the subjects for this strand.
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(StrandSubject::class, 'strand_id');
    }

    /**
     * The "booting" method of the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::deleting(function (ShsStrand $strand): void {
            // Delete all related subjects when strand is deleted
            $strand->subjects()->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'track_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
