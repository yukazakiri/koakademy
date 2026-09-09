<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $school_id
 * @property int $code_authority_id
 * @property int $uploaded_by_user_id
 * @property int|null $confirmed_by_user_id
 * @property string $original_filename
 * @property string $checksum
 * @property array<int, mixed>|null $field_proposals
 * @property string $status
 * @property int $ready_count
 * @property int $invalid_count
 * @property int $applied_count
 * @property int $skipped_count
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
final class CodeAuthorityImport extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'public_id',
        'school_id',
        'code_authority_id',
        'uploaded_by_user_id',
        'confirmed_by_user_id',
        'original_filename',
        'checksum',
        'field_proposals',
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

    public function authority(): BelongsTo
    {
        return $this->belongsTo(CodeAuthority::class, 'code_authority_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CodeAuthorityImportRow::class, 'code_authority_import_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'field_proposals' => 'array',
        ];
    }
}
