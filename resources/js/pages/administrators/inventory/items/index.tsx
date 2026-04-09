import AdminLayout from "@/components/administrators/admin-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { Plus, Search, Trash2, Wrench } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare const route: any;

interface InventoryItem {
    id: number;
    name: string;
    sku: string;
    item_type: string;
    category?: string | null;
    supplier?: string | null;
    stock_quantity: number;
    unit: string;
    track_stock: boolean;
    is_borrowable: boolean;
    is_active: boolean;
    location?: string | null;
    ip_address?: string | null;
    wifi_ssid?: string | null;
    updated_at?: string | null;
}

interface Props {
    user: User;
    items: InventoryItem[];
    stats: {
        total_items: number;
        tools: number;
        network_devices: number;
        borrowable_items: number;
        low_stock: number;
    };
    filters: {
        search?: string | null;
        item_type?: string | null;
        borrowable?: string | null;
        status?: string | null;
    };
    options: {
        item_types: { value: string; label: string }[];
        borrowable: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
    };
    flash?: {
        type: string;
        message: string;
    };
}

const typeStyles: Record<string, string> = {
    Tool: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    Router: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    NVR: "bg-indigo-500/10 text-indigo-700 dark:text-indigo-300",
    CCTV: "bg-sky-500/10 text-sky-700 dark:text-sky-300",
};

const statusStyles: Record<string, string> = {
    active: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    inactive: "bg-slate-500/10 text-slate-700 dark:text-slate-300",
};

export default function InventoryItemsIndex({ user, items, stats, filters, options, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [deleteTarget, setDeleteTarget] = useState<InventoryItem | null>(null);

    useEffect(() => {
        if (!flash?.message) return;
        if (flash.type === "success") {
            toast.success(flash.message);
        } else if (flash.type === "error") {
            toast.error(flash.message);
        } else {
            toast.message(flash.message);
        }
    }, [flash]);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.inventory.items.index"), { ...filters, search: term || null }, { preserveState: true, replace: true });
    }, 300);

    const handleFilterChange = (key: keyof Props["filters"], value: string) => {
        router.get(
            route("administrators.inventory.items.index"),
            { ...filters, [key]: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route("administrators.inventory.items.destroy", deleteTarget.id), {
            preserveState: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout user={user} title="Inventory Items">
            <Head title="Administrators • Inventory Items" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-emerald-500/10 to-slate-900/5">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                    <Wrench className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Inventory Items</CardTitle>
                                    <CardDescription>Manage tools and installed network assets.</CardDescription>
                                </div>
                            </div>
                            <div className="text-muted-foreground flex flex-wrap gap-3 text-sm">
                                <span>Total items: {stats.total_items}</span>
                                <span>Tools: {stats.tools}</span>
                                <span>Network devices: {stats.network_devices}</span>
                                <span>Low stock: {stats.low_stock}</span>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild className="gap-2">
                                <Link href={route("administrators.inventory.items.create", { item_type: "tool" })}>
                                    <Plus className="h-4 w-4" />
                                    Add Tool
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.inventory.items.create", { item_type: "router" })}>Add Network Device</Link>
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Inventory Overview</CardTitle>
                            <CardDescription>Search by item name, SKU, or IP address.</CardDescription>
                        </div>
                        <div className="flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:items-center">
                            <div className="relative w-full lg:w-64">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search items..."
                                    value={search}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setSearch(value);
                                        handleSearch(value);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select value={filters.item_type ?? "all"} onValueChange={(value) => handleFilterChange("item_type", value)}>
                                <SelectTrigger className="w-full lg:w-48">
                                    <SelectValue placeholder="Filter type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.item_types.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.borrowable ?? "all"} onValueChange={(value) => handleFilterChange("borrowable", value)}>
                                <SelectTrigger className="w-full lg:w-44">
                                    <SelectValue placeholder="Borrowable" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.borrowable.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.status ?? "all"} onValueChange={(value) => handleFilterChange("status", value)}>
                                <SelectTrigger className="w-full lg:w-40">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.statuses.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Location / Network</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead>Borrowable</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-muted-foreground h-24 text-center text-sm">
                                            No inventory items match the current filters.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <p className="text-foreground font-medium">{item.name}</p>
                                                    <div className="text-muted-foreground text-xs">
                                                        <span>{item.sku}</span>
                                                        {item.category && <span> • {item.category}</span>}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={typeStyles[item.item_type] ?? "bg-muted text-muted-foreground"}>
                                                    {item.item_type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                <div className="space-y-1">
                                                    <p>{item.location ?? "No location"}</p>
                                                    {(item.ip_address || item.wifi_ssid) && (
                                                        <p className="text-muted-foreground text-xs">
                                                            {item.ip_address ? `IP ${item.ip_address}` : ""}
                                                            {item.ip_address && item.wifi_ssid ? " • " : ""}
                                                            {item.wifi_ssid ? item.wifi_ssid : ""}
                                                        </p>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {item.track_stock ? `${item.stock_quantity} ${item.unit}` : "Not tracked"}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={
                                                        item.is_borrowable
                                                            ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                            : "bg-slate-500/10 text-slate-700 dark:text-slate-300"
                                                    }
                                                >
                                                    {item.is_borrowable ? "Yes" : "No"}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={statusStyles[item.is_active ? "active" : "inactive"]}>
                                                    {item.is_active ? "Active" : "Inactive"}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route("administrators.inventory.items.edit", item.id)}>Edit</Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => setDeleteTarget(item)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete inventory item?</AlertDialogTitle>
                        <AlertDialogDescription>This will remove "{deleteTarget?.name}" from the inventory list.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmDelete}>Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
