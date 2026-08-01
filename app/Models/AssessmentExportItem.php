<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

final class AssessmentExportItem extends Model
{
    use BelongsToSchool;

    #[Override]
    protected $fillable = [
        'assessment_export_id', 'school_id', 'enrollment_id', 'sequence', 'status', 'attempts',
        'artifact_disk', 'artifact_path', 'page_count', 'byte_size', 'checksum',
        'error_code', 'error_message', 'error_context', 'started_at',
        'completed_at', 'failed_at',
    ];

    public function export(): BelongsTo
    {
        return $this->belongsTo(AssessmentExport::class, 'assessment_export_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'error_context' => 'array',
            'sequence' => 'integer',
            'attempts' => 'integer',
            'page_count' => 'integer',
            'byte_size' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
