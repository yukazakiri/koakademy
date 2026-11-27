<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Per-student document verification & audit metadata synced alongside DocumentLocation.
 *
 * @property int $id
 * @property int|null $school_id
 * @property int $student_id
 * @property string $document_key
 * @property string $status
 * @property string|null $file_path
 * @property string|null $disk
 * @property string|null $file_name
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property int|null $uploaded_by
 * @property Carbon|null $uploaded_at
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property string|null $waiver_reason
 * @property string|null $notes
 * @property Carbon|null $expires_at
 * @property array<int, array<string, mixed>>|null $history
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student|null $student
 * @property-read User|null $uploader
 * @property-read User|null $verifier
 */
final class StudentDocumentRecord extends Model
{
    use BelongsToSchool;
    use LogsActivity;

    public const string StatusMissing = 'missing';

    public const string StatusPendingReview = 'pending_review';

    public const string StatusVerified = 'verified';

    public const string StatusRejected = 'rejected';

    public const string StatusWaived = 'waived';

    public const string StatusPhysicalOnFile = 'physical_on_file';

    /** @var list<string> */
    public const array COMPLIANT_STATUSES = [
        self::StatusVerified,
        self::StatusWaived,
        self::StatusPhysicalOnFile,
    ];

    /** @var list<string> */
    public const array ALL_STATUSES = [
        self::StatusMissing,
        self::StatusPendingReview,
        self::StatusVerified,
        self::StatusRejected,
        self::StatusWaived,
        self::StatusPhysicalOnFile,
    ];

    #[Override]
    protected $table = 'student_document_records';

    #[Override]
    protected $fillable = [
        'school_id',
        'student_id',
        'document_key',
        'status',
        'file_path',
        'disk',
        'file_name',
        'mime_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'waiver_reason',
        'notes',
        'expires_at',
        'history',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'document_key',
                'status',
                'file_name',
                'rejection_reason',
                'waiver_reason',
                'notes',
                'expires_at',
            ])
            ->logOnlyDirty()
            ->useLogName('student_documents');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isCompliant(): bool
    {
        return in_array($this->status, self::COMPLIANT_STATUSES, true);
    }

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'student_id' => 'integer',
            'file_size' => 'integer',
            'uploaded_by' => 'integer',
            'verified_by' => 'integer',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'date',
            'history' => 'array',
        ];
    }
}
