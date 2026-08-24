<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FacultyCustomFieldDefinition extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'key', 'label', 'field_type', 'help_text', 'options', 'source_header_aliases',
        'is_required', 'is_sensitive', 'is_active', 'display_order',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(FacultyCustomFieldValue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('display_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'source_header_aliases' => 'array',
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
