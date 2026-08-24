<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RegistrarStudentProfileImport extends Model
{
    use BelongsToSchool;

    protected $attributes = [
        'status' => 'review',
        'ready_count' => 0,
        'invalid_count' => 0,
        'applied_count' => 0,
        'skipped_count' => 0,
    ];

    protected $fillable = [
        'public_id',
        'school_id',
        'uploaded_by_user_id',
        'confirmed_by_user_id',
        'original_filename',
        'checksum',
        'schema_version',
        'status',
        'ready_count',
        'invalid_count',
        'applied_count',
        'skipped_count',
        'confirmed_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /** @return HasMany<RegistrarStudentProfileImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(RegistrarStudentProfileImportRow::class);
    }

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'ready_count' => 'integer',
            'invalid_count' => 'integer',
            'applied_count' => 'integer',
            'skipped_count' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }
}
