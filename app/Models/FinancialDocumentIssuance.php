<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialDocumentStatus;
use App\Enums\FinancialDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

final class FinancialDocumentIssuance extends Model
{
    #[Override]
    protected $fillable = [
        'uuid',
        'type',
        'student_id',
        'transaction_id',
        'enrollment_id',
        'tuition_id',
        'issued_by',
        'document_number',
        'paper_reference',
        'recipient',
        'status',
        'snapshot',
        'integrity_signature',
        'verification_token',
        'verification_token_hash',
        'disk',
        'pdf_path',
        'pdf_checksum',
        'issued_at',
        'queued_at',
        'sent_at',
        'failed_at',
        'failure_message',
        'revoked_at',
        'revocation_reason',
    ];

    #[Override]
    protected $hidden = [
        'verification_token',
        'verification_token_hash',
        'integrity_signature',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    public function tuition(): BelongsTo
    {
        return $this->belongsTo(StudentTuition::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(FinancialDocumentDelivery::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function attachmentFilename(): string
    {
        return sprintf(
            'official-e%s-%s.pdf',
            $this->type === FinancialDocumentType::Receipt ? 'receipt' : 'invoice',
            $this->document_number,
        );
    }

    protected function casts(): array
    {
        return [
            'type' => FinancialDocumentType::class,
            'status' => FinancialDocumentStatus::class,
            'snapshot' => 'array',
            'verification_token' => 'encrypted',
            'issued_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
