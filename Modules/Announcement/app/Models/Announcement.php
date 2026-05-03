<?php

declare(strict_types=1);

namespace Modules\Announcement\Models;

use App\Models\Classes;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'content',
        'slug',
        'status',
        'type',
        'priority',
        'is_global',
        'display_mode',
        'requires_acknowledgment',
        'link',
        'is_active',
        'starts_at',
        'ends_at',
        'published_at',
        'expires_at',
        'created_by',
        'class_id',
        'school_id',
        'attachments',
    ];

    /**
     * Default attribute values that mirror database defaults.
     * Ensures new model instances have sensible values before saving.
     */
    protected $attributes = [
        'status' => 'draft',
        'is_global' => true,
        'is_active' => true,
    ];

    /**
     * Per-request cache for Schema::hasColumn checks (SQLite issues many pragma queries per call).
     *
     * @var array<string, bool>|null
     */
    private static ?array $schemaColumnCache = null;

    public static function schemaHasCachedColumn(string $column): bool
    {
        self::$schemaColumnCache ??= [];

        if (! array_key_exists($column, self::$schemaColumnCache)) {
            self::$schemaColumnCache[$column] = Schema::hasColumn('announcements', $column);
        }

        return self::$schemaColumnCache[$column];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        if (self::schemaHasCachedColumn('is_global')) {
            $query->where(function (Builder $builder): void {
                $builder->where('is_global', true)
                    ->orWhereNull('is_global');
            });
        }

        if (self::schemaHasCachedColumn('class_id')) {
            $query->whereNull('class_id');
        }

        return $query;
    }

    public function scopePublished(Builder $query): Builder
    {
        if (self::schemaHasCachedColumn('status')) {
            $query->where(function (Builder $builder): void {
                $builder->where('status', 'published')
                    ->orWhere(function (Builder $legacyBuilder): void {
                        $legacyBuilder->whereNull('status');

                        if (self::schemaHasCachedColumn('is_active')) {
                            $legacyBuilder->where(function (Builder $activeBuilder): void {
                                $activeBuilder->where('is_active', true)
                                    ->orWhereNull('is_active');
                            });
                        }
                    });
            });
        }

        if (self::schemaHasCachedColumn('published_at')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
        }

        if (self::schemaHasCachedColumn('starts_at')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            });
        }

        return $query;
    }

    public function scopeActive(Builder $query): Builder
    {
        if (self::schemaHasCachedColumn('is_active')) {
            $query->where(function (Builder $builder): void {
                $builder->where('is_active', true)
                    ->orWhereNull('is_active');
            });
        }

        if (self::schemaHasCachedColumn('expires_at')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
        }

        if (self::schemaHasCachedColumn('ends_at')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
        }

        return $query;
    }

    public function isActive(): bool
    {
        if ($this->status !== null && $this->status !== 'published') {
            return false;
        }

        if ($this->published_at instanceof Carbon && $this->published_at->isFuture()) {
            return false;
        }

        if ($this->starts_at instanceof Carbon && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at instanceof Carbon && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->ends_at instanceof Carbon && $this->ends_at->isPast()) {
            return false;
        }

        return $this->is_active ?? true;
    }

    protected static function newFactory(): \Modules\Announcement\Database\Factories\AnnouncementFactory
    {
        return \Modules\Announcement\Database\Factories\AnnouncementFactory::new();
    }

    protected function casts(): array
    {
        return [
            'created_by' => 'int',
            'class_id' => 'int',
            'school_id' => 'int',
            'is_global' => 'bool',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'attachments' => 'array',
        ];
    }
}
