<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryProductHistory extends Model
{
    protected $table = 'inventory_product_histories';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'event_type',
        'before',
        'after',
        'reference_type',
        'reference_id',
        'notes',
        'recorded_by',
        'recorded_at',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
