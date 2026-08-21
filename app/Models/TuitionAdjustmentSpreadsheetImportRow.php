<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TuitionAdjustmentSpreadsheetImportRow extends Model
{
    protected $fillable = [
        'tuition_adjustment_spreadsheet_import_id', 'row_number', 'student_number', 'student_id', 'student_enrollment_id',
        'student_tuition_id', 'tuition_adjustment_id', 'input', 'canonical_snapshot', 'proposal', 'errors', 'result', 'status',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(TuitionAdjustmentSpreadsheetImport::class, 'tuition_adjustment_spreadsheet_import_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(TuitionAdjustment::class, 'tuition_adjustment_id');
    }

    protected function casts(): array
    {
        return [
            'input' => 'array', 'canonical_snapshot' => 'array', 'proposal' => 'array', 'errors' => 'array', 'result' => 'array',
        ];
    }
}
