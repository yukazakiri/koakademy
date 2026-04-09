<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Room
 *
 * @property-read Collection<int, Classes> $classes
 * @property-read int|null $classes_count
 * @property-read Collection<int, Schedule> $schedules
 * @property-read int|null $schedules_count
 *
 * @method static Builder<static>|Room newModelQuery()
 * @method static Builder<static>|Room newQuery()
 * @method static Builder<static>|Room query()
 *
 * @mixin \Eloquent
 */
final class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $fillable = [
        'name',
        'class_code',
        'is_active',
    ];

    public function classes()
    {
        return $this->hasMany(Classes::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'room_id', 'id');
    }

    protected function getSchedulesAttribute()
    {
        return $this->schedules()->get();
    }

    /**
     * Scope a query to only include active rooms
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
