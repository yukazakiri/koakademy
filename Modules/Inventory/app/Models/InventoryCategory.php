<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $slug
 * @property bool $is_active
 * @property int|null $parent_id
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCategory|null $parent
 * @property-read Collection<int, InventoryCategory> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, InventoryProduct> $products
 * @property-read int|null $products_count
 *
 * @method static Builder<static>|InventoryCategory newModelQuery()
 * @method static Builder<static>|InventoryCategory newQuery()
 * @method static Builder<static>|InventoryCategory query()
 * @method static Builder<static>|InventoryCategory whereId($value)
 * @method static Builder<static>|InventoryCategory whereName($value)
 * @method static Builder<static>|InventoryCategory whereDescription($value)
 * @method static Builder<static>|InventoryCategory whereSlug($value)
 * @method static Builder<static>|InventoryCategory whereIsActive($value)
 * @method static Builder<static>|InventoryCategory whereParentId($value)
 * @method static Builder<static>|InventoryCategory whereSortOrder($value)
 * @method static Builder<static>|InventoryCategory whereCreatedAt($value)
 * @method static Builder<static>|InventoryCategory whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class InventoryCategory extends Model
{
    protected $table = 'inventory_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'slug',
        'is_active',
        'parent_id',
        'sort_order',
    ];

    /**
     * Get the parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get products in this category
     */
    public function products(): HasMany
    {
        return $this->hasMany(InventoryProduct::class, 'category_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
