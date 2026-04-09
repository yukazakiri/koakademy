<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TracksStrand
 *
 * @method static Builder<static>|TracksStrand newModelQuery()
 * @method static Builder<static>|TracksStrand newQuery()
 * @method static Builder<static>|TracksStrand query()
 *
 * @mixin \Eloquent
 */
final class TracksStrand extends Model
{
    protected $table = 'tracks_strands';

    protected $fillable = [
        'code',
        'title',
        'description',
        'track_id',
    ];

    protected function casts(): array
    {
        return [
            'track_id' => 'int',
        ];
    }
}
