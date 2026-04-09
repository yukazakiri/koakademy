<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $tax_number
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryProduct> $products
 * @property-read int|null $products_count
 *
 * @method static Builder<static>|InventorySupplier newModelQuery()
 * @method static Builder<static>|InventorySupplier newQuery()
 * @method static Builder<static>|InventorySupplier query()
 * @method static Builder<static>|InventorySupplier whereId($value)
 * @method static Builder<static>|InventorySupplier whereName($value)
 * @method static Builder<static>|InventorySupplier whereContactPerson($value)
 * @method static Builder<static>|InventorySupplier whereEmail($value)
 * @method static Builder<static>|InventorySupplier wherePhone($value)
 * @method static Builder<static>|InventorySupplier whereAddress($value)
 * @method static Builder<static>|InventorySupplier whereCity($value)
 * @method static Builder<static>|InventorySupplier whereState($value)
 * @method static Builder<static>|InventorySupplier wherePostalCode($value)
 * @method static Builder<static>|InventorySupplier whereCountry($value)
 * @method static Builder<static>|InventorySupplier whereTaxNumber($value)
 * @method static Builder<static>|InventorySupplier whereNotes($value)
 * @method static Builder<static>|InventorySupplier whereIsActive($value)
 * @method static Builder<static>|InventorySupplier whereCreatedAt($value)
 * @method static Builder<static>|InventorySupplier whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class InventorySupplier extends Model
{
    protected $table = 'inventory_suppliers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_number',
        'notes',
        'is_active',
    ];

    /**
     * Get products from this supplier
     */
    public function products(): HasMany
    {
        return $this->hasMany(InventoryProduct::class, 'supplier_id');
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
        ];
    }
}
