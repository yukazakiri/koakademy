<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FacultyBulkImportRow extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'row_number', 'faculty_id', 'faculty_id_number', 'name', 'action', 'payload',
        'errors', 'warnings', 'result', 'status',
    ];

    protected $hidden = ['payload'];

    public function import(): BelongsTo
    {
        return $this->belongsTo(FacultyBulkImport::class, 'faculty_bulk_import_id');
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
