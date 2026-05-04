<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Inventory\Enums\InventoryItemType;
use Modules\Inventory\Models\InventoryProduct;

final class InventoryProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('inventoryProduct');
        $productId = $product instanceof InventoryProduct ? $product->id : null;
        $itemTypes = array_map(
            fn (InventoryItemType $type): string => $type->value,
            InventoryItemType::cases()
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('inventory_products', 'sku')->ignore($productId)],
            'item_type' => ['required', Rule::in($itemTypes)],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:inventory_categories,id'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'exists:inventory_suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'defective_quantity' => ['required', 'integer', 'min:0'],
            'min_stock_level' => ['required', 'integer', 'min:0'],
            'max_stock_level' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'track_stock' => ['required', 'boolean'],
            'is_borrowable' => ['required', 'boolean'],
            'is_consumable' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
            'location_building' => ['nullable', 'string', 'max:255'],
            'location_floor' => ['nullable', 'string', 'max:255'],
            'location_area' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip', 'max:255'],
            'wifi_ssid' => ['nullable', 'string', 'max:255'],
            'wifi_password' => ['nullable', 'string', 'max:255'],
            'login_username' => ['nullable', 'string', 'max:255'],
            'login_password' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['nullable', File::image()->max(5 * 1024)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Item name is required.',
            'sku.unique' => 'This SKU is already in use.',
            'item_type.in' => 'Select a valid item type.',
            'price.required' => 'Price is required.',
            'cost.required' => 'Cost is required.',
            'stock_quantity.required' => 'Stock quantity is required.',
            'min_stock_level.required' => 'Minimum stock level is required.',
            'defective_quantity.required' => 'Defective quantity is required.',
            'unit.required' => 'Unit of measurement is required.',
            'ip_address.ip' => 'IP address must be a valid IPv4 or IPv6 address.',
        ];
    }
}
