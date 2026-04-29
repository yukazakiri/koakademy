<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InventoryItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property InventoryItemType|string $item_type
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property float $price
 * @property float $cost
 * @property int $stock_quantity
 * @property int $defective_quantity
 * @property int $min_stock_level
 * @property int|null $max_stock_level
 * @property string $unit
 * @property string|null $barcode
 * @property bool $track_stock
 * @property bool $is_borrowable
 * @property bool $is_active
 * @property array|null $images
 * @property string|null $notes
 * @property string|null $location_building
 * @property string|null $location_floor
 * @property string|null $location_area
 * @property string|null $ip_address
 * @property string|null $wifi_ssid
 * @property string|null $wifi_password
 * @property string|null $login_username
 * @property string|null $login_password
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCategory|null $category
 * @property-read InventorySupplier|null $supplier
 * @property-read Collection<int, InventoryStockMovement> $stockMovements
 * @property-read int|null $stock_movements_count
 * @property-read Collection<int, InventoryBorrowing> $borrowings
 * @property-read int|null $borrowings_count
 * @property-read Collection<int, InventoryAmendment> $amendments
 * @property-read int|null $amendments_count
 * @property-read Collection<int, InventoryProductHistory> $histories
 * @property-read int|null $histories_count
 *
 * @method static Builder<static>|InventoryProduct newModelQuery()
 * @method static Builder<static>|InventoryProduct newQuery()
 * @method static Builder<static>|InventoryProduct query()
 * @method static Builder<static>|InventoryProduct whereId($value)
 * @method static Builder<static>|InventoryProduct whereName($value)
 * @method static Builder<static>|InventoryProduct whereSku($value)
 * @method static Builder<static>|InventoryProduct whereItemType($value)
 * @method static Builder<static>|InventoryProduct whereDescription($value)
 * @method static Builder<static>|InventoryProduct whereCategoryId($value)
 * @method static Builder<static>|InventoryProduct whereSupplierId($value)
 * @method static Builder<static>|InventoryProduct wherePrice($value)
 * @method static Builder<static>|InventoryProduct whereCost($value)
 * @method static Builder<static>|InventoryProduct whereStockQuantity($value)
 * @method static Builder<static>|InventoryProduct whereMinStockLevel($value)
 * @method static Builder<static>|InventoryProduct whereMaxStockLevel($value)
 * @method static Builder<static>|InventoryProduct whereUnit($value)
 * @method static Builder<static>|InventoryProduct whereBarcode($value)
 * @method static Builder<static>|InventoryProduct whereTrackStock($value)
 * @method static Builder<static>|InventoryProduct whereIsBorrowable($value)
 * @method static Builder<static>|InventoryProduct whereIsActive($value)
 * @method static Builder<static>|InventoryProduct whereImages($value)
 * @method static Builder<static>|InventoryProduct whereNotes($value)
 * @method static Builder<static>|InventoryProduct whereLocationBuilding($value)
 * @method static Builder<static>|InventoryProduct whereLocationFloor($value)
 * @method static Builder<static>|InventoryProduct whereLocationArea($value)
 * @method static Builder<static>|InventoryProduct whereIpAddress($value)
 * @method static Builder<static>|InventoryProduct whereWifiSsid($value)
 * @method static Builder<static>|InventoryProduct whereWifiPassword($value)
 * @method static Builder<static>|InventoryProduct whereLoginUsername($value)
 * @method static Builder<static>|InventoryProduct whereLoginPassword($value)
 * @method static Builder<static>|InventoryProduct whereCreatedAt($value)
 * @method static Builder<static>|InventoryProduct whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class InventoryProduct extends Model
{
    use HasFactory;

    protected $table = 'inventory_products';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'item_type' => InventoryItemType::Tool->value,
        'is_borrowable' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sku',
        'item_type',
        'description',
        'category_id',
        'supplier_id',
        'price',
        'cost',
        'stock_quantity',
        'defective_quantity',
        'min_stock_level',
        'max_stock_level',
        'unit',
        'barcode',
        'track_stock',
        'is_borrowable',
        'is_active',
        'images',
        'notes',
        'location_building',
        'location_floor',
        'location_area',
        'ip_address',
        'wifi_ssid',
        'wifi_password',
        'login_username',
        'login_password',
    ];

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    /**
     * Get the supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    /**
     * Get stock movements
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'product_id');
    }

    /**
     * Get borrowings
     */
    public function borrowings(): HasMany
    {
        return $this->hasMany(InventoryBorrowing::class, 'product_id');
    }

    /**
     * Get amendments
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(InventoryAmendment::class, 'product_id');
    }

    /**
     * Get change histories.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(InventoryProductHistory::class, 'product_id');
    }

    /**
     * Scope for low stock products
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_level');
    }

    /**
     * Scope for borrowable products
     */
    public function scopeBorrowable(Builder $query): Builder
    {
        return $query->where('is_borrowable', true);
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    /**
     * Check if product is a network device
     */
    public function isNetworkDevice(): bool
    {
        $itemType = $this->item_type instanceof InventoryItemType
            ? $this->item_type->value
            : $this->item_type;

        return in_array($itemType, InventoryItemType::networkValues(), true);
    }

    /**
     * Get a formatted location label
     */
    public function locationLabel(): ?string
    {
        $segments = array_filter([
            $this->location_building,
            $this->location_floor,
            $this->location_area,
        ], fn (?string $segment): bool => $segment !== null && $segment !== '');

        if ($segments === []) {
            return null;
        }

        return implode(' - ', $segments);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => InventoryItemType::class,
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock_quantity' => 'integer',
            'defective_quantity' => 'integer',
            'min_stock_level' => 'integer',
            'max_stock_level' => 'integer',
            'track_stock' => 'boolean',
            'is_borrowable' => 'boolean',
            'is_active' => 'boolean',
            'images' => 'array',
            'wifi_password' => 'encrypted',
            'login_password' => 'encrypted',
        ];
    }
}
