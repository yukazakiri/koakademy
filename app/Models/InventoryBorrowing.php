<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $quantity_borrowed
 * @property string $borrower_name
 * @property string|null $borrower_email
 * @property string|null $borrower_phone
 * @property string|null $department
 * @property string|null $purpose
 * @property string $status
 * @property Carbon $borrowed_date
 * @property Carbon|null $expected_return_date
 * @property Carbon|null $actual_return_date
 * @property int $quantity_returned
 * @property string|null $return_notes
 * @property int $issued_by
 * @property int|null $returned_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryProduct $product
 * @property-read User $issuedBy
 * @property-read User|null $returnedTo
 *
 * @method static Builder<static>|InventoryBorrowing newModelQuery()
 * @method static Builder<static>|InventoryBorrowing newQuery()
 * @method static Builder<static>|InventoryBorrowing query()
 * @method static Builder<static>|InventoryBorrowing whereId($value)
 * @method static Builder<static>|InventoryBorrowing whereProductId($value)
 * @method static Builder<static>|InventoryBorrowing whereQuantityBorrowed($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowerName($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowerEmail($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowerPhone($value)
 * @method static Builder<static>|InventoryBorrowing whereDepartment($value)
 * @method static Builder<static>|InventoryBorrowing wherePurpose($value)
 * @method static Builder<static>|InventoryBorrowing whereStatus($value)
 * @method static Builder<static>|InventoryBorrowing whereBorrowedDate($value)
 * @method static Builder<static>|InventoryBorrowing whereExpectedReturnDate($value)
 * @method static Builder<static>|InventoryBorrowing whereActualReturnDate($value)
 * @method static Builder<static>|InventoryBorrowing whereQuantityReturned($value)
 * @method static Builder<static>|InventoryBorrowing whereReturnNotes($value)
 * @method static Builder<static>|InventoryBorrowing whereIssuedBy($value)
 * @method static Builder<static>|InventoryBorrowing whereReturnedTo($value)
 * @method static Builder<static>|InventoryBorrowing whereCreatedAt($value)
 * @method static Builder<static>|InventoryBorrowing whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class InventoryBorrowing extends Model
{
    protected $table = 'inventory_borrowings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'quantity_borrowed',
        'borrower_name',
        'borrower_email',
        'borrower_phone',
        'department',
        'purpose',
        'status',
        'borrowed_date',
        'expected_return_date',
        'actual_return_date',
        'quantity_returned',
        'return_notes',
        'issued_by',
        'returned_to',
    ];

    /**
     * Get the product being borrowed
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    /**
     * Get the user who issued the borrowing
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the user to whom the item was returned
     */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /**
     * Scope for active borrowings
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['borrowed', 'overdue']);
    }

    /**
     * Scope for overdue borrowings
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'overdue')
            ->orWhere(function ($q): void {
                $q->where('status', 'borrowed')
                    ->where('expected_return_date', '<', now());
            });
    }

    /**
     * Check if borrowing is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'returned') {
            return false;
        }

        return $this->expected_return_date && $this->expected_return_date->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_borrowed' => 'integer',
            'quantity_returned' => 'integer',
            'borrowed_date' => 'datetime',
            'expected_return_date' => 'datetime',
            'actual_return_date' => 'datetime',
        ];
    }
}
