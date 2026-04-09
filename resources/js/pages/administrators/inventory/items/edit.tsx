import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { Save, Server, Wrench } from "lucide-react";
import type { FormEvent } from "react";

declare const route: any;

interface SelectOption {
    value: string | number;
    label: string;
}

interface InventoryProductFormData {
    name: string;
    sku: string;
    item_type: string;
    description: string;
    category_id: string;
    supplier_id: string;
    price: string;
    cost: string;
    stock_quantity: string;
    min_stock_level: string;
    max_stock_level: string;
    unit: string;
    barcode: string;
    track_stock: boolean;
    is_borrowable: boolean;
    is_active: boolean;
    notes: string;
    location_building: string;
    location_floor: string;
    location_area: string;
    ip_address: string;
    wifi_ssid: string;
    wifi_password: string;
    login_username: string;
    login_password: string;
}

interface InventoryProductRecord {
    id: number;
    name: string;
    sku: string;
    item_type: string;
    description?: string | null;
    category_id?: number | null;
    supplier_id?: number | null;
    price: number;
    cost: number;
    stock_quantity: number;
    min_stock_level: number;
    max_stock_level?: number | null;
    unit: string;
    barcode?: string | null;
    track_stock: boolean;
    is_borrowable: boolean;
    is_active: boolean;
    notes?: string | null;
    location_building?: string | null;
    location_floor?: string | null;
    location_area?: string | null;
    ip_address?: string | null;
    wifi_ssid?: string | null;
    wifi_password?: string | null;
    login_username?: string | null;
    login_password?: string | null;
}

interface Props {
    user: User;
    product: InventoryProductRecord | null;
    defaults?: {
        item_type?: string;
    } | null;
    options: {
        item_types: SelectOption[];
        categories: SelectOption[];
        suppliers: SelectOption[];
    };
}

export default function InventoryItemEdit({ user, product, defaults, options }: Props) {
    const defaultType = product?.item_type ?? defaults?.item_type ?? "Tool";
    const form = useForm<InventoryProductFormData>({
        name: product?.name ?? "",
        sku: product?.sku ?? "",
        item_type: defaultType,
        description: product?.description ?? "",
        category_id: product?.category_id ? String(product.category_id) : "",
        supplier_id: product?.supplier_id ? String(product.supplier_id) : "",
        price: product?.price ? String(product.price) : "0",
        cost: product?.cost ? String(product.cost) : "0",
        stock_quantity: product?.stock_quantity ? String(product.stock_quantity) : "0",
        min_stock_level: product?.min_stock_level ? String(product.min_stock_level) : "0",
        max_stock_level: product?.max_stock_level ? String(product.max_stock_level) : "",
        unit: product?.unit ?? "pcs",
        barcode: product?.barcode ?? "",
        track_stock: product?.track_stock ?? true,
        is_borrowable: product?.is_borrowable ?? defaultType === "Tool",
        is_active: product?.is_active ?? true,
        notes: product?.notes ?? "",
        location_building: product?.location_building ?? "",
        location_floor: product?.location_floor ?? "",
        location_area: product?.location_area ?? "",
        ip_address: product?.ip_address ?? "",
        wifi_ssid: product?.wifi_ssid ?? "",
        wifi_password: product?.wifi_password ?? "",
        login_username: product?.login_username ?? "",
        login_password: product?.login_password ?? "",
    });

    const isNetworkType = ["Router", "NVR", "CCTV"].includes(form.data.item_type);
    const isTool = form.data.item_type === "Tool";

    const handleItemTypeChange = (value: string) => {
        form.setData("item_type", value);
        if (value !== "Tool") {
            form.setData("is_borrowable", false);
        } else if (!form.data.is_borrowable) {
            form.setData("is_borrowable", true);
        }
    };

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (product) {
            form.put(route("administrators.inventory.items.update", product.id));
            return;
        }

        form.post(route("administrators.inventory.items.store"));
    };

    return (
        <AdminLayout user={user} title={product ? "Edit Item" : "Add Inventory Item"}>
            <Head title={`Administrators • ${product ? "Edit" : "Add"} Inventory Item`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-emerald-500/10 to-slate-900/5">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                {isNetworkType ? <Server className="h-5 w-5" /> : <Wrench className="h-5 w-5" />}
                            </div>
                            <div>
                                <CardTitle>{product ? "Update Inventory Item" : "New Inventory Item"}</CardTitle>
                                <CardDescription>Capture location, credentials, and stock details accurately.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.inventory.items.index")}>Back to items</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {product ? "Save changes" : "Create item"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Item Details</CardTitle>
                        <CardDescription>Define how this item should be tracked.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Item Name</Label>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData("name", event.target.value)} />
                            {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="sku">SKU</Label>
                            <Input id="sku" value={form.data.sku} onChange={(event) => form.setData("sku", event.target.value)} />
                            {form.errors.sku && <p className="text-destructive text-xs">{form.errors.sku}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label>Item Type</Label>
                            <Select value={form.data.item_type} onValueChange={handleItemTypeChange}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select item type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.item_types.map((type) => (
                                        <SelectItem key={type.value} value={String(type.value)}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.item_type && <p className="text-destructive text-xs">{form.errors.item_type}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label>Category</Label>
                            <Select
                                value={form.data.category_id || "none"}
                                onValueChange={(value) => form.setData("category_id", value === "none" ? "" : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    {options.categories.map((category) => (
                                        <SelectItem key={category.value} value={String(category.value)}>
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.category_id && <p className="text-destructive text-xs">{form.errors.category_id}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label>Supplier</Label>
                            <Select
                                value={form.data.supplier_id || "none"}
                                onValueChange={(value) => form.setData("supplier_id", value === "none" ? "" : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select supplier" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    {options.suppliers.map((supplier) => (
                                        <SelectItem key={supplier.value} value={String(supplier.value)}>
                                            {supplier.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.supplier_id && <p className="text-destructive text-xs">{form.errors.supplier_id}</p>}
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                rows={3}
                                value={form.data.description}
                                onChange={(event) => form.setData("description", event.target.value)}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Location Details</CardTitle>
                        <CardDescription>Specify where this asset is stored or installed.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="location_building">Building</Label>
                            <Input
                                id="location_building"
                                value={form.data.location_building}
                                onChange={(event) => form.setData("location_building", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="location_floor">Floor</Label>
                            <Input
                                id="location_floor"
                                value={form.data.location_floor}
                                onChange={(event) => form.setData("location_floor", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="location_area">Area / Landmark</Label>
                            <Input
                                id="location_area"
                                value={form.data.location_area}
                                onChange={(event) => form.setData("location_area", event.target.value)}
                            />
                        </div>
                    </CardContent>
                </Card>

                {isNetworkType && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Network Configuration</CardTitle>
                            <CardDescription>Record IP, WiFi, and access credentials.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="ip_address">IP Address</Label>
                                <Input
                                    id="ip_address"
                                    value={form.data.ip_address}
                                    onChange={(event) => form.setData("ip_address", event.target.value)}
                                />
                                {form.errors.ip_address && <p className="text-destructive text-xs">{form.errors.ip_address}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="wifi_ssid">WiFi SSID</Label>
                                <Input
                                    id="wifi_ssid"
                                    value={form.data.wifi_ssid}
                                    onChange={(event) => form.setData("wifi_ssid", event.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="wifi_password">WiFi Password</Label>
                                <Input
                                    id="wifi_password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.wifi_password}
                                    onChange={(event) => form.setData("wifi_password", event.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="login_username">Login Username</Label>
                                <Input
                                    id="login_username"
                                    value={form.data.login_username}
                                    onChange={(event) => form.setData("login_username", event.target.value)}
                                />
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="login_password">Login Password</Label>
                                <Input
                                    id="login_password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.login_password}
                                    onChange={(event) => form.setData("login_password", event.target.value)}
                                />
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Stock & Pricing</CardTitle>
                        <CardDescription>Set quantity, thresholds, and pricing.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="stock_quantity">Stock Quantity</Label>
                            <Input
                                id="stock_quantity"
                                type="number"
                                min={0}
                                value={form.data.stock_quantity}
                                onChange={(event) => form.setData("stock_quantity", event.target.value)}
                            />
                            {form.errors.stock_quantity && <p className="text-destructive text-xs">{form.errors.stock_quantity}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="min_stock_level">Minimum Stock</Label>
                            <Input
                                id="min_stock_level"
                                type="number"
                                min={0}
                                value={form.data.min_stock_level}
                                onChange={(event) => form.setData("min_stock_level", event.target.value)}
                            />
                            {form.errors.min_stock_level && <p className="text-destructive text-xs">{form.errors.min_stock_level}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="max_stock_level">Maximum Stock</Label>
                            <Input
                                id="max_stock_level"
                                type="number"
                                min={0}
                                value={form.data.max_stock_level}
                                onChange={(event) => form.setData("max_stock_level", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="unit">Unit</Label>
                            <Input id="unit" value={form.data.unit} onChange={(event) => form.setData("unit", event.target.value)} />
                            {form.errors.unit && <p className="text-destructive text-xs">{form.errors.unit}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="cost">Cost</Label>
                            <Input
                                id="cost"
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.cost}
                                onChange={(event) => form.setData("cost", event.target.value)}
                            />
                            {form.errors.cost && <p className="text-destructive text-xs">{form.errors.cost}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="price">Price</Label>
                            <Input
                                id="price"
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.price}
                                onChange={(event) => form.setData("price", event.target.value)}
                            />
                            {form.errors.price && <p className="text-destructive text-xs">{form.errors.price}</p>}
                        </div>
                        <div className="space-y-2 lg:col-span-3">
                            <Label htmlFor="barcode">Barcode</Label>
                            <Input id="barcode" value={form.data.barcode} onChange={(event) => form.setData("barcode", event.target.value)} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Notes & Settings</CardTitle>
                        <CardDescription>Manage availability and borrowing controls.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea id="notes" rows={3} value={form.data.notes} onChange={(event) => form.setData("notes", event.target.value)} />
                        </div>
                        <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                            <div>
                                <p className="text-sm font-medium">Track Stock</p>
                                <p className="text-muted-foreground text-xs">Enable stock monitoring for this item.</p>
                            </div>
                            <Switch checked={form.data.track_stock} onCheckedChange={(value) => form.setData("track_stock", value)} />
                        </div>
                        {isTool && (
                            <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">Borrowable</p>
                                    <p className="text-muted-foreground text-xs">Allow staff to borrow this tool.</p>
                                </div>
                                <Switch checked={form.data.is_borrowable} onCheckedChange={(value) => form.setData("is_borrowable", value)} />
                            </div>
                        )}
                        <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                            <div>
                                <p className="text-sm font-medium">Active Item</p>
                                <p className="text-muted-foreground text-xs">Inactive items are hidden from lists.</p>
                            </div>
                            <Switch checked={form.data.is_active} onCheckedChange={(value) => form.setData("is_active", value)} />
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AdminLayout>
    );
}
