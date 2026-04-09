<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class Announcement
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string|null $slug
 * @property string $status
 * @property string $type
 * @property string $priority
 * @property bool $is_global
 * @property int|null $user_id
 * @property int|null $class_id
 * @property array|null $attachments
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Classes|null $class
 *
 * @method static Builder<static>|Announcement newModelQuery()
 * @method static Builder<static>|Announcement newQuery()
 * @method static Builder<static>|Announcement query()
 * @method static Builder<static>|Announcement global()
 * @method static Builder<static>|Announcement published()
 * @method static Builder<static>|Announcement active()
 *
 * @mixin \Eloquent
 */
final class Announcement extends Model
{
    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'content',
        'slug',
        'status',
        'type',
        'priority',
        'is_global',
        'user_id',
        'class_id',
        'attachments',
        'published_at',
        'expires_at',
    ];

    /**
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Classes, self>
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * Scope for global announcements (not class-specific).
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true)->whereNull('class_id');
    }

    /**
     * Scope for published announcements.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope for active (not expired) announcements.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if announcement is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->published_at && $this->published_at->isFuture()) {
            return false;
        }

        return ! ($this->expires_at && $this->expires_at->isPast());
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'class_id' => 'int',
            'is_global' => 'bool',
            'attachments' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
