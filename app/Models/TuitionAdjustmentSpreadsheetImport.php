<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TuitionAdjustmentSpreadsheetImport extends Model
{
    protected $fillable = [
        'public_id', 'uploaded_by_user_id', 'confirmed_by_user_id', 'original_filename', 'stored_path', 'checksum',
        'school_year', 'semester', 'status', 'ready_count', 'invalid_count', 'applied_count', 'rejected_count', 'confirmed_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TuitionAdjustmentSpreadsheetImportRow::class);
    }

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }
}
