<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FacultyBulkImport extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'public_id', 'school_id', 'uploaded_by_user_id', 'confirmed_by_user_id', 'original_filename',
        'source_type', 'checksum', 'status', 'ready_count', 'invalid_count', 'applied_count',
        'skipped_count', 'confirmed_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function rows(): HasMany
    {
        return $this->hasMany(FacultyBulkImportRow::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }
}
