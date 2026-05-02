<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $recorded_quantity
 * @property int $actual_quantity
 * @property int $variance
 * @property string $status
 * @property string|null $notes
 * @property int $amended_by
 * @property int|null $approved_by
 * @property Carbon $amendment_date
 * @property Carbon|null $approved_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryProduct $product
 * @property-read User $amendedBy
 * @property-read User|null $approvedBy
 *
 * @method static Builder<static>|InventoryAmendment newModelQuery()
 * @method static Builder<static>|InventoryAmendment newQuery()
 * @method static Builder<static>|InventoryAmendment query()
 * @method static Builder<static>|InventoryAmendment whereId($value)
 * @method static Builder<static>|InventoryAmendment whereProductId($value)
 * @method static Builder<static>|InventoryAmendment whereRecordedQuantity($value)
 * @method static Builder<static>|InventoryAmendment whereActualQuantity($value)
 * @method static Builder<static>|InventoryAmendment whereVariance($value)
 * @method static Builder<static>|InventoryAmendment whereStatus($value)
 * @method static Builder<static>|InventoryAmendment whereNotes($value)
 * @method static Builder<static>|InventoryAmendment whereAmendedBy($value)
 * @method static Builder<static>|InventoryAmendment whereApprovedBy($value)
 * @method static Builder<static>|InventoryAmendment whereAmendmentDate($value)
 * @method static Builder<static>|InventoryAmendment whereApprovedDate($value)
 * @method static Builder<static>|InventoryAmendment whereCreatedAt($value)
 * @method static Builder<static>|InventoryAmendment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class InventoryAmendment extends Model
{
    protected $table = 'inventory_amendments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'recorded_quantity',
        'actual_quantity',
        'variance',
        'status',
        'notes',
        'amended_by',
        'approved_by',
        'amendment_date',
        'approved_date',
    ];

    /**
     * Get the product being amended
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    /**
     * Get the user who created the amendment
     */
    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }

    /**
     * Get the user who approved the amendment
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for pending amendments
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved amendments
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Check if amendment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_quantity' => 'integer',
            'actual_quantity' => 'integer',
            'variance' => 'integer',
            'amendment_date' => 'datetime',
            'approved_date' => 'datetime',
        ];
    }
}
