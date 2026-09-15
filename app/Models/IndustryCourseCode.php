<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

/**
 * One official course/program code imported from a regulator spreadsheet
 * (or registered manually) for a specific school + authority.
 *
 * Only code/title are fixed columns; every authority-specific field lives
 * in the schemaless attributes bag so different countries can import
 * different spreadsheets without schema changes.
 *
 * @property int $id
 * @property int $school_id
 * @property int $code_authority_id
 * @property string $code
 * @property string $title
 * @property array<string, mixed>|null $attributes
 * @property string $source
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
final class IndustryCourseCode extends Model
{
    use BelongsToSchool;
    use Searchable;

    protected $fillable = [
        'school_id',
        'code_authority_id',
        'code',
        'title',
        'attributes',
        'source',
        'is_active',
    ];

    public function authority(): BelongsTo
    {
        return $this->belongsTo(CodeAuthority::class, 'code_authority_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'industry_course_code_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForAuthority(Builder $query, CodeAuthority|int $authority): Builder
    {
        return $query->where('code_authority_id', $authority instanceof CodeAuthority ? $authority->id : $authority);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'code' => $this->code,
            'title' => $this->title,
        ];
    }

    public function displayLabel(): string
    {
        return sprintf('%s — %s', (string) $this->code, (string) $this->title);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::saving(function (self $code): void {
            $code->code = mb_trim((string) $code->code);
            $code->title = mb_trim((string) $code->title);
        });
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
