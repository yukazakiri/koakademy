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
 * @property string $type
 * @property int $quantity
 * @property int $previous_stock
 * @property int $new_stock
 * @property string|null $reference
 * @property string|null $reason
 * @property int|null $user_id
 * @property Carbon $movement_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryProduct $product
 * @property-read User|null $user
 *
 * @method static Builder<static>|InventoryStockMovement newModelQuery()
 * @method static Builder<static>|InventoryStockMovement newQuery()
 * @method static Builder<static>|InventoryStockMovement query()
 * @method static Builder<static>|InventoryStockMovement whereId($value)
 * @method static Builder<static>|InventoryStockMovement whereProductId($value)
 * @method static Builder<static>|InventoryStockMovement whereType($value)
 * @method static Builder<static>|InventoryStockMovement whereQuantity($value)
 * @method static Builder<static>|InventoryStockMovement wherePreviousStock($value)
 * @method static Builder<static>|InventoryStockMovement whereNewStock($value)
 * @method static Builder<static>|InventoryStockMovement whereReference($value)
 * @method static Builder<static>|InventoryStockMovement whereReason($value)
 * @method static Builder<static>|InventoryStockMovement whereUserId($value)
 * @method static Builder<static>|InventoryStockMovement whereMovementDate($value)
 * @method static Builder<static>|InventoryStockMovement whereCreatedAt($value)
 * @method static Builder<static>|InventoryStockMovement whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class InventoryStockMovement extends Model
{
    protected $table = 'inventory_stock_movements';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reference',
        'reason',
        'user_id',
        'movement_date',
    ];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    /**
     * Get the user who made the movement
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for specific movement type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_stock' => 'integer',
            'new_stock' => 'integer',
            'movement_date' => 'datetime',
        ];
    }
}
