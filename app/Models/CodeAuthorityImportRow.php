<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $school_id
 * @property int $row_number
 * @property string|null $code
 * @property string|null $title
 * @property string|null $category_code
 * @property string|null $category_name
 * @property string|null $action
 * @property array<string, mixed>|null $payload
 * @property array<int, string>|null $errors
 * @property array<int, string>|null $warnings
 * @property array<string, mixed>|null $result
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
final class CodeAuthorityImportRow extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id',
        'row_number',
        'code',
        'title',
        'category_code',
        'category_name',
        'action',
        'payload',
        'errors',
        'warnings',
        'result',
        'status',
    ];

    protected $hidden = ['payload'];

    public function import(): BelongsTo
    {
        return $this->belongsTo(CodeAuthorityImport::class, 'code_authority_import_id');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'errors' => 'array',
            'warnings' => 'array',
            'result' => 'array',
        ];
    }
}
