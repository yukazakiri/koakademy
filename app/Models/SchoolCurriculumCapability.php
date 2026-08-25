<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use Database\Factories\SchoolCurriculumCapabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

final class SchoolCurriculumCapability extends Model
{
    /** @use HasFactory<SchoolCurriculumCapabilityFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'school_id',
        'school_level',
        'curriculum_framework',
        'curriculum_reference',
        'is_enabled',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    protected function casts(): array
    {
        return [
            'school_level' => SchoolLevel::class,
            'curriculum_framework' => CurriculumFramework::class,
            'is_enabled' => 'boolean',
        ];
    }
}
