import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Combobox } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { Camera, Save, Server, Wrench } from "lucide-react";
import type { FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";

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
    category_name: string;
    supplier_id: string;
    supplier_name: string;
    price: string;
    cost: string;
    stock_quantity: string;
    defective_quantity: string;
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
    images: File[];
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
    defective_quantity?: number;
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
    image_urls?: string[];
    history?: {
        id: number;
        event_type: string;
        before?: Record<string, unknown> | null;
        after?: Record<string, unknown> | null;
        notes?: string | null;
        recorded_at?: string | null;
    }[];
    recent_borrowings?: {
        id: number;
        borrower_name: string;
        status: string;
        quantity_borrowed: number;
        quantity_returned: number;
        borrowed_date?: string | null;
    }[];
    location_history?: {
        id: number;
        from_location: string;
        to_location: string;
        notes?: string | null;
        recorded_at?: string | null;
    }[];
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
        locations: {
            buildings: string[];
            floors: string[];
            areas: string[];
        };
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
        category_name: "",
        supplier_id: product?.supplier_id ? String(product.supplier_id) : "",
        supplier_name: "",
        price: product?.price ? String(product.price) : "0",
        cost: product?.cost ? String(product.cost) : "0",
        stock_quantity: product?.stock_quantity ? String(product.stock_quantity) : "0",
        defective_quantity: product?.defective_quantity ? String(product.defective_quantity) : "0",
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
        images: [],
    });

    const [imagePreviews, setImagePreviews] = useState<string[]>([]);
    const locationForm = useForm({
        location_building: product?.location_building ?? "",
        location_floor: product?.location_floor ?? "",
        location_area: product?.location_area ?? "",
        notes: "",
    });
    const existingImages = product?.image_urls ?? [];

    const generateSku = (itemType: string, locationBuilding: string) => {
        const prefix = (itemType || "Tool").toUpperCase().slice(0, 3);
        const buildingCode = (locationBuilding || "GEN")
            .replace(/[^a-zA-Z0-9]/g, "")
            .toUpperCase()
            .slice(0, 4);
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, "0");
        const d = String(now.getDate()).padStart(2, "0");
        const stamp = `${y}${m}${d}`;

        return `${prefix}-${buildingCode || "GEN"}-${stamp}-001`;
    };

    const isNetworkType = ["Router", "NVR", "CCTV"].includes(form.data.item_type);
    const isTool = form.data.item_type === "Tool";

    const handleItemTypeChange = (value: string) => {
        form.setData("item_type", value);
        if (value !== "Tool") {
            form.setData("is_borrowable", false);
        } else if (!form.data.is_borrowable) {
            form.setData("is_borrowable", true);
        }

        if (!product) {
            form.setData("sku", generateSku(value, form.data.location_building));
        }
    };

    useEffect(() => {
        if (product) {
            return;
        }

        form.setData("sku", generateSku(form.data.item_type, form.data.location_building));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [product, form.data.item_type, form.data.location_building]);

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (product) {
            form.put(route("administrators.inventory.items.update", product.id), { forceFormData: true });
            return;
        }

        form.post(route("administrators.inventory.items.store"), { forceFormData: true });
    };

    const totalUnits = useMemo(() => {
        const good = Number(form.data.stock_quantity || 0);
        const defective = Number(form.data.defective_quantity || 0);

        return Math.max(good, 0) + Math.max(defective, 0);
    }, [form.data.stock_quantity, form.data.defective_quantity]);

    const locationOptions = useMemo(
        () => ({
            buildings: options.locations.buildings.map((value) => ({ label: value, value })),
            floors: options.locations.floors.map((value) => ({ label: value, value })),
            areas: options.locations.areas.map((value) => ({ label: value, value })),
        }),
        [options.locations.areas, options.locations.buildings, options.locations.floors],
    );

    const submitLocationUpdate = (event: FormEvent) => {
        event.preventDefault();

        if (!product) {
            return;
        }

        locationForm.post(route("administrators.inventory.items.update-location", product.id));
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
                                <CardDescription>
                                    Step-by-step workflow: identify item, record condition stock, place location, then add photo evidence.
                                </CardDescription>
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
                        <CardTitle>Step 1: Item Identity</CardTitle>
                        <CardDescription>Start with the item name and type. SKU is auto-generated for consistency.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Item Name</Label>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData("name", event.target.value)} />
                            {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="sku">SKU</Label>
                            <div className="flex gap-2">
                                <Input id="sku" value={form.data.sku} readOnly className="font-mono" />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => form.setData("sku", generateSku(form.data.item_type, form.data.location_building))}
                                >
                                    Regenerate
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs">Auto-generated as TYPE-BUILDING-YYYYMMDD-SEQ.</p>
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
                                onValueChange={(value) => {
                                    if (value === "create") {
                                        form.setData("category_id", "");
                                        return;
                                    }
                                    form.setData("category_id", value === "none" ? "" : value);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    <SelectItem value="create">+ Create new category</SelectItem>
                                    {options.categories.map((category) => (
                                        <SelectItem key={category.value} value={String(category.value)}>
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.category_id && <p className="text-destructive text-xs">{form.errors.category_id}</p>}
                            <Input
                                placeholder="New category (optional)"
                                value={form.data.category_name}
                                onChange={(event) => form.setData("category_name", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Supplier</Label>
                            <Select
                                value={form.data.supplier_id || "none"}
                                onValueChange={(value) => {
                                    if (value === "create") {
                                        form.setData("supplier_id", "");
                                        return;
                                    }
                                    form.setData("supplier_id", value === "none" ? "" : value);
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select supplier" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    <SelectItem value="create">+ Create new supplier</SelectItem>
                                    {options.suppliers.map((supplier) => (
                                        <SelectItem key={supplier.value} value={String(supplier.value)}>
                                            {supplier.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.supplier_id && <p className="text-destructive text-xs">{form.errors.supplier_id}</p>}
                            <Input
                                placeholder="New supplier (optional)"
                                value={form.data.supplier_name}
                                onChange={(event) => form.setData("supplier_name", event.target.value)}
                            />
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
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="images" className="flex items-center gap-2">
                                <Camera className="h-4 w-4" />
                                Item Photos
                            </Label>
                            <Input
                                id="images"
                                type="file"
                                accept="image/*"
                                capture="environment"
                                multiple
                                onChange={(event) => {
                                    const files = Array.from(event.target.files ?? []);
                                    form.setData("images", files);
                                    setImagePreviews(files.map((file) => URL.createObjectURL(file)));
                                }}
                            />
                            <p className="text-muted-foreground text-xs">Upload up to 6 photos. Mobile devices can capture directly using camera.</p>
                            {form.errors.images && <p className="text-destructive text-xs">{form.errors.images}</p>}
                            <div className="grid grid-cols-3 gap-3 md:grid-cols-6">
                                {existingImages.map((url) => (
                                    <img key={url} src={url} alt="Existing item" className="h-20 w-full rounded-md border object-cover" />
                                ))}
                                {imagePreviews.map((url) => (
                                    <img key={url} src={url} alt="Preview" className="h-20 w-full rounded-md border object-cover" />
                                ))}
                            </div>
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

                {product && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Set New Current Location</CardTitle>
                            <CardDescription>Use searchable combobox fields. If not found, create directly from the same input.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitLocationUpdate} className="grid gap-4 md:grid-cols-2">
                                <Combobox
                                    label="Building"
                                    required
                                    options={locationOptions.buildings}
                                    value={locationForm.data.location_building}
                                    onValueChange={(value) => locationForm.setData("location_building", value)}
                                    placeholder="Select or create building"
                                    searchPlaceholder="Search building"
                                    allowCreate
                                    createLabel="Create building"
                                />
                                {locationForm.errors.location_building ? <p className="text-destructive text-xs md:col-span-2">{locationForm.errors.location_building}</p> : null}
                                <Combobox
                                    label="Floor"
                                    options={locationOptions.floors}
                                    value={locationForm.data.location_floor}
                                    onValueChange={(value) => locationForm.setData("location_floor", value)}
                                    placeholder="Select or create floor"
                                    searchPlaceholder="Search floor"
                                    allowCreate
                                    createLabel="Create floor"
                                />
                                <div className="md:col-span-2">
                                    <Combobox
                                        label="Area / Landmark"
                                        options={locationOptions.areas}
                                        value={locationForm.data.location_area}
                                        onValueChange={(value) => locationForm.setData("location_area", value)}
                                        placeholder="Select or create area"
                                        searchPlaceholder="Search area"
                                        allowCreate
                                        createLabel="Create area"
                                    />
                                </div>
                                <div className="md:col-span-2">
                                    <Label htmlFor="location_notes">Move Notes</Label>
                                    <Textarea
                                        id="location_notes"
                                        rows={2}
                                        value={locationForm.data.notes}
                                        onChange={(event) => locationForm.setData("notes", event.target.value)}
                                        placeholder="Example: moved from Annex to Main Building for class deployment"
                                    />
                                </div>
                                <div className="flex justify-end md:col-span-2">
                                    <Button type="submit" disabled={locationForm.processing}>
                                        Update current location
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

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
                        <CardTitle>Step 2: Condition & Stock</CardTitle>
                        <CardDescription>Record current good and defective units so availability stays accurate.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="stock_quantity">Good Quantity</Label>
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
                            <Label htmlFor="defective_quantity">Defective Quantity</Label>
                            <Input
                                id="defective_quantity"
                                type="number"
                                min={0}
                                value={form.data.defective_quantity}
                                onChange={(event) => form.setData("defective_quantity", event.target.value)}
                            />
                            {form.errors.defective_quantity && <p className="text-destructive text-xs">{form.errors.defective_quantity}</p>}
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
                            <div className="bg-muted/50 rounded-lg border px-3 py-2 text-sm">
                                <p className="font-medium">Total physical units: {totalUnits}</p>
                                <p className="text-muted-foreground text-xs">Total = Good + Defective. Borrowing uses Good Quantity.</p>
                            </div>
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

                {product && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Location Movement Timeline</CardTitle>
                            <CardDescription>Track where the item was before and where it is now for every location change.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {(product.location_history ?? []).length === 0 ? (
                                <p className="text-muted-foreground text-sm">No location movement records yet.</p>
                            ) : (
                                (product.location_history ?? []).map((entry) => (
                                    <div key={entry.id} className="rounded-lg border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-sm font-semibold">{entry.from_location} → {entry.to_location}</p>
                                            <p className="text-muted-foreground text-xs">{entry.recorded_at ?? "—"}</p>
                                        </div>
                                        {entry.notes ? <p className="text-muted-foreground mt-1 text-xs">{entry.notes}</p> : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                )}

                {product && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Item History</CardTitle>
                            <CardDescription>Version-like timeline for location and stock condition changes.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {(product.history ?? []).length === 0 ? (
                                <p className="text-muted-foreground text-sm">No history records yet.</p>
                            ) : (
                                (product.history ?? []).map((entry) => (
                                    <div key={entry.id} className="rounded-lg border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-sm font-medium">{entry.event_type.replaceAll("_", " ")}</p>
                                            <p className="text-muted-foreground text-xs">{entry.recorded_at ?? "—"}</p>
                                        </div>
                                        {entry.notes ? <p className="text-muted-foreground mt-1 text-xs">{entry.notes}</p> : null}
                                        {entry.before || entry.after ? (
                                            <div className="mt-2 grid gap-2 text-xs md:grid-cols-2">
                                                <div className="rounded border bg-slate-50 p-2 dark:bg-slate-900/30">
                                                    <p className="mb-1 font-semibold">Before</p>
                                                    <pre className="overflow-x-auto whitespace-pre-wrap">{JSON.stringify(entry.before ?? {}, null, 2)}</pre>
                                                </div>
                                                <div className="rounded border bg-emerald-50 p-2 dark:bg-emerald-950/20">
                                                    <p className="mb-1 font-semibold">After</p>
                                                    <pre className="overflow-x-auto whitespace-pre-wrap">{JSON.stringify(entry.after ?? {}, null, 2)}</pre>
                                                </div>
                                            </div>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                )}

                {product && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Borrowing Timeline</CardTitle>
                            <CardDescription>See who borrowed this item and if quantity decreased due to active borrows.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {(product.recent_borrowings ?? []).length === 0 ? (
                                <p className="text-muted-foreground text-sm">No borrowing activity yet.</p>
                            ) : (
                                (product.recent_borrowings ?? []).map((entry) => (
                                    <div key={entry.id} className="flex items-center justify-between rounded border p-2 text-sm">
                                        <div>
                                            <p className="font-medium">{entry.borrower_name}</p>
                                            <p className="text-muted-foreground text-xs">{entry.borrowed_date ?? "—"}</p>
                                        </div>
                                        <div className="text-right">
                                            <p>{entry.quantity_borrowed - entry.quantity_returned} out</p>
                                            <p className="text-muted-foreground text-xs">{entry.status}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                )}
            </form>
        </AdminLayout>
    );
}
