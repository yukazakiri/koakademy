<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read StudentEnrollment|null $enrollment
 * @property-read string $formatted_amount
 *
 * @method static Builder<static>|AdditionalFee newModelQuery()
 * @method static Builder<static>|AdditionalFee newQuery()
 * @method static Builder<static>|AdditionalFee query()
 *
 * @mixin \Eloquent
 */
final class AdditionalFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'fee_name',
        'amount',
        'is_separate_transaction',
        'transaction_number',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    /**
     * Get the formatted amount
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(get: fn (): string => '₱ '.number_format((float) $this->amount, 2));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_separate_transaction' => 'boolean',
        ];
    }
}
