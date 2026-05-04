import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ScrollText, Search } from "lucide-react";
import { useState } from "react";
import { useDebouncedCallback } from "use-debounce";

declare const route: any;

interface LedgerEntry {
    id: number;
    event_type: string;
    reference_type?: string | null;
    reference_id?: number | null;
    notes?: string | null;
    recorded_at?: string | null;
    recorded_by?: string | null;
    product: {
        id?: number | null;
        name?: string | null;
        sku?: string | null;
        unit?: string | null;
        is_consumable?: boolean;
    };
    movement: {
        good_before?: number | null;
        good_after?: number | null;
        good_delta?: number | null;
        defective_before?: number | null;
        defective_after?: number | null;
        defective_delta?: number | null;
        location_from?: string | null;
        location_to?: string | null;
        consumable_before?: boolean | null;
        consumable_after?: boolean | null;
    };
}

interface Props {
    user: User;
    entries: LedgerEntry[];
    stats: {
        total_entries: number;
        today_entries: number;
        products_with_logs: number;
        consumable_products: number;
    };
    filters: {
        search?: string | null;
        event_type?: string | null;
        reference_type?: string | null;
    };
    options: {
        event_types: { value: string; label: string }[];
        reference_types: { value: string; label: string }[];
    };
}

export default function InventoryLedgerIndex({ user, entries, stats, filters, options }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.inventory.ledger.index"), { ...filters, search: term || null }, { preserveState: true, replace: true });
    }, 300);

    const handleFilterChange = (key: "event_type" | "reference_type", value: string) => {
        router.get(
            route("administrators.inventory.ledger.index"),
            { ...filters, [key]: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout user={user} title="Inventory Ledger">
            <Head title="Administrators • Inventory Ledger" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-indigo-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600">
                                    <ScrollText className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Transactional Inventory Ledger</CardTitle>
                                    <CardDescription>One source of truth for quantity, location, borrower, and consumable changes.</CardDescription>
                                </div>
                            </div>
                            <div className="text-muted-foreground flex flex-wrap gap-3 text-sm">
                                <span>Total entries: {stats.total_entries}</span>
                                <span>Today: {stats.today_entries}</span>
                                <span>Logged products: {stats.products_with_logs}</span>
                                <span>Consumables: {stats.consumable_products}</span>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.inventory.items.index")}>Items</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.inventory.borrowings.index")}>Borrowings</Link>
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Ledger Entries</CardTitle>
                            <CardDescription>Search and filter all recorded inventory movements.</CardDescription>
                        </div>
                        <div className="flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:items-center">
                            <div className="relative w-full lg:w-72">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search by item, SKU, event, or notes"
                                    value={search}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setSearch(value);
                                        handleSearch(value);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select value={filters.event_type ?? "all"} onValueChange={(value) => handleFilterChange("event_type", value)}>
                                <SelectTrigger className="w-full lg:w-52">
                                    <SelectValue placeholder="Event" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.event_types.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.reference_type ?? "all"} onValueChange={(value) => handleFilterChange("reference_type", value)}>
                                <SelectTrigger className="w-full lg:w-48">
                                    <SelectValue placeholder="Reference" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.reference_types.map((option) => (
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
                                    <TableHead>Recorded</TableHead>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Event</TableHead>
                                    <TableHead>Movement</TableHead>
                                    <TableHead>Location</TableHead>
                                    <TableHead>Reference</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {entries.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-muted-foreground h-24 text-center text-sm">
                                            No ledger entries found for the selected filters.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    entries.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell className="text-muted-foreground text-xs">{entry.recorded_at ?? "—"}</TableCell>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <p className="text-foreground font-medium">{entry.product.name ?? "Unknown item"}</p>
                                                    <div className="text-muted-foreground text-xs">
                                                        {entry.product.sku ?? "—"}
                                                        {entry.product.is_consumable ? " • Consumable" : ""}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge className="bg-muted text-muted-foreground">{entry.event_type.replaceAll("_", " ")}</Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                <div>Good Δ: {entry.movement.good_delta ?? 0}</div>
                                                <div>Def Δ: {entry.movement.defective_delta ?? 0}</div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                {(entry.movement.location_from || "—") + " → " + (entry.movement.location_to || "—")}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                {entry.reference_type ? `${entry.reference_type} #${entry.reference_id ?? "—"}` : "—"}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
