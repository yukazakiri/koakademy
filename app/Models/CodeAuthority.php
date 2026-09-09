<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A regulatory authority whose official course/program list a school tracks.
 *
 * Definitions are created at runtime per school (e.g. CHED for a
 * CHED-accredited Philippine school) so no country dataset ships with the
 * repository. An authority optionally gates itself to schools via
 * country_code and/or curriculum_framework, mirroring the
 * regulatory-report registry semantics.
 *
 * @property int $id
 * @property int $school_id
 * @property string $key
 * @property string $name
 * @property string|null $country_code
 * @property string|null $curriculum_framework
 * @property string|null $description
 * @property array|null $schema
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
final class CodeAuthority extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'key',
        'name',
        'country_code',
        'curriculum_framework',
        'description',
        'schema',
        'is_active',
    ];

    /**
     * @return list<array{key: string, label: string, required: bool}>
     */
    public static function defaultSchema(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'required' => true],
            ['key' => 'title', 'label' => 'Title', 'required' => true],
            ['key' => 'category_code', 'label' => 'Category / Discipline Code', 'required' => false],
            ['key' => 'category_name', 'label' => 'Category / Discipline Name', 'required' => false],
        ];
    }

    public function codes(): HasMany
    {
        return $this->hasMany(IndustryCourseCode::class, 'code_authority_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(CodeAuthorityImport::class, 'code_authority_id');
    }

    /**
     * Whether this authority is relevant for the given school.
     * Null dimensions are unrestricted; a school without a country set
     * matches any country so fresh OSS installs are never locked out.
     */
    public function matchesSchool(School $school): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->country_code !== null && $school->country_code !== null
            && mb_strtoupper($this->country_code) !== mb_strtoupper((string) $school->country_code)) {
            return false;
        }

        if ($this->curriculum_framework !== null) {
            $frameworks = $school->curriculumCapabilities()
                ->where('is_enabled', true)
                ->pluck('curriculum_framework');

            $values = $frameworks
                ->map(fn (mixed $framework): mixed => $framework instanceof BackedEnum ? $framework->value : $framework)
                ->filter(fn (mixed $value): bool => is_string($value) && $value !== '');

            if (! $values->contains($this->curriculum_framework)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{key: string, label: string, required: bool}>
     */
    public function columnDefinitions(): array
    {
        $schema = $this->schema;

        if (! is_array($schema) || $schema === []) {
            return self::defaultSchema();
        }

        $definitions = [];

        foreach ($schema as $column) {
            if (! is_array($column)) {
                continue;
            }

            $key = $column['key'] ?? null;
            $label = $column['label'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $definitions[] = [
                'key' => $key,
                'label' => is_string($label) && $label !== '' ? $label : $key,
                'required' => (bool) ($column['required'] ?? false),
            ];
        }

        return $definitions === [] ? self::defaultSchema() : array_values($definitions);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (self $authority): void {
            $authority->key = self::slugify((string) $authority->key);
            if ($authority->country_code !== null) {
                $authority->country_code = mb_strtoupper(mb_trim($authority->country_code));
            }
            $authority->schema ??= self::defaultSchema();
        });

        self::updating(function (self $authority): void {
            $authority->key = self::slugify((string) $authority->key);
            if ($authority->country_code !== null) {
                $authority->country_code = mb_strtoupper(mb_trim($authority->country_code));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    private static function slugify(string $value): string
    {
        $slug = mb_strtolower(mb_trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = mb_trim($slug, '_');

        return $slug !== '' ? mb_substr($slug, 0, 100) : 'authority';
    }
}
