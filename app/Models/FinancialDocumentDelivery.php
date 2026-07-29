<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

final class FinancialDocumentDelivery extends Model
{
    #[Override]
    protected $fillable = [
        'uuid',
        'financial_document_issuance_id',
        'recipient',
        'status',
        'attempts',
        'queued_at',
        'sent_at',
        'failed_at',
        'error',
    ];

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(FinancialDocumentIssuance::class, 'financial_document_issuance_id');
    }

    protected function casts(): array
    {
        return [
            'status' => FinancialDeliveryStatus::class,
            'attempts' => 'integer',
            'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
