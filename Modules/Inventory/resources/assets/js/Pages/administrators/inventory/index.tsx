import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ClipboardList, Package, ScrollText, Wrench } from "lucide-react";

declare const route: any;

interface InventoryStats {
    total_items: number;
    good_units: number;
    defective_units: number;
    total_units: number;
    general_equipment: number;
    specialized_assets: number;
    consumables: number;
    borrowable_items: number;
    active_borrowings: number;
    overdue_borrowings: number;
    ledger_entries: number;
    ledger_today: number;
}

interface RecentItem {
    id: number;
    name: string;
    sku: string;
    item_type: string;
    stock_quantity: number;
    defective_quantity?: number;
    total_quantity?: number;
    unit: string;
    image_url?: string | null;
    location?: string | null;
    updated_at?: string | null;
}

interface RecentBorrowing {
    id: number;
    product: { id?: number | null; name?: string | null };
    borrower: { name?: string | null; department?: string | null };
    status: string;
    quantity_borrowed: number;
    quantity_returned: number;
    borrowed_date?: string | null;
    expected_return_date?: string | null;
    is_overdue: boolean;
}

interface Props {
    user: User;
    stats: InventoryStats;
    recent: {
        items: RecentItem[];
        borrowings: RecentBorrowing[];
        transactions: {
            id: number;
            event_type: string;
            notes?: string | null;
            recorded_at?: string | null;
            reference_type?: string | null;
            product?: { id?: number | null; name?: string | null; sku?: string | null; unit?: string | null; is_consumable?: boolean };
            movement?: { good_delta?: number | null; defective_delta?: number | null };
        }[];
    };
}

const statusStyles: Record<string, string> = {
    borrowed: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    returned: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    overdue: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
    lost: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
};

const typeLabels: Record<string, string> = {
    Tool: "General Equipment",
    Router: "Distribution Unit",
    NVR: "Recording Unit",
    CCTV: "Monitoring Unit",
};

const formatDate = (value?: string | null) => {
    if (!value) return "—";
    return new Date(value).toLocaleDateString();
};

export default function InventoryIndex({ user, stats, recent }: Props) {
    return (
        <AdminLayout user={user} title="Inventory Transactions">
            <Head title="Administrators • Inventory Transactions" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-r from-slate-900/5 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-900/10 text-slate-700 dark:text-slate-200">
                                <Package className="h-6 w-6" />
                            </div>
                            <div>
                                <CardTitle className="text-2xl">Inventory Ledger Center</CardTitle>
                                <CardDescription>Track transactional entries for stock, location, borrowings, and consumables in one place.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={route("administrators.inventory.items.create")}>Add Item</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.inventory.borrowings.create")}>Log Borrowing</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.inventory.ledger.index")}>Open Ledger</Link>
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Total Items</p>
                                <p className="text-2xl font-semibold">{stats.total_items}</p>
                                <p className="text-muted-foreground text-xs">{stats.consumables} consumable</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900/10 text-slate-700 dark:text-slate-200">
                                <Package className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Condition Status</p>
                                <p className="text-2xl font-semibold">{stats.total_units}</p>
                                <p className="text-muted-foreground text-xs">Good {stats.good_units} • Defective {stats.defective_units}</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                <Wrench className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Asset Mix</p>
                                <p className="text-2xl font-semibold">{stats.specialized_assets}</p>
                                <p className="text-muted-foreground text-xs">General {stats.general_equipment} • Specialized {stats.specialized_assets}</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600">
                                <ScrollText className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Active Borrowings</p>
                                <p className="text-2xl font-semibold">{stats.active_borrowings}</p>
                                <p className="text-muted-foreground text-xs">{stats.ledger_today} logged today</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                                <ClipboardList className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                    <Card className="border">
                        <CardHeader className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <CardTitle>Recently Updated Items</CardTitle>
                                <CardDescription>Latest changes across inventory items and stock records.</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route("administrators.inventory.items.index")}>View inventory</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Location</TableHead>
                                        <TableHead className="text-right">Updated</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent.items.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground h-24 text-center text-sm">
                                                No inventory items updated yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        recent.items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        {item.image_url ? (
                                                            <img src={item.image_url} alt={item.name} className="h-10 w-10 rounded-md border object-cover" />
                                                        ) : (
                                                            <div className="bg-muted text-muted-foreground flex h-10 w-10 items-center justify-center rounded-md border text-xs">
                                                                N/A
                                                            </div>
                                                        )}
                                                        <div className="space-y-1">
                                                            <p className="text-foreground font-medium">{item.name}</p>
                                                            <p className="text-muted-foreground text-xs">{item.sku}</p>
                                                            <p className="text-muted-foreground text-xs">
                                                                Good {item.stock_quantity} • Defective {item.defective_quantity ?? 0} • Total {item.total_quantity ?? item.stock_quantity}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className="bg-muted text-muted-foreground">{typeLabels[item.item_type] ?? item.item_type}</Badge>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground text-sm">{item.location ?? "Not set"}</TableCell>
                                                <TableCell className="text-muted-foreground text-right text-xs">
                                                    {formatDate(item.updated_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="border">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Borrowing Watchlist</CardTitle>
                                <CardDescription>Latest check-outs and return expectations.</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route("administrators.inventory.borrowings.index")}>Borrow logs</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {recent.borrowings.length === 0 ? (
                                <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm">
                                    No borrowings logged yet.
                                </div>
                            ) : (
                                recent.borrowings.map((record) => (
                                    <div key={record.id} className="flex flex-col gap-2 rounded-lg border p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="text-foreground text-sm font-semibold">{record.product?.name ?? "Unknown item"}</p>
                                                <p className="text-muted-foreground text-xs">{record.borrower?.name ?? "Unknown borrower"}</p>
                                            </div>
                                            <Badge className={statusStyles[record.status] ?? "bg-muted text-muted-foreground"}>{record.status}</Badge>
                                        </div>
                                        <div className="text-muted-foreground flex items-center justify-between text-xs">
                                            <span>Qty {record.quantity_borrowed - record.quantity_returned} outstanding</span>
                                            <span className={record.is_overdue ? "text-rose-500" : ""}>
                                                Due {formatDate(record.expected_return_date)}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="border">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Recent Ledger Entries</CardTitle>
                            <CardDescription>Unified transaction timeline across all inventory changes.</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route("administrators.inventory.ledger.index")}>View full ledger</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Event</TableHead>
                                    <TableHead>Movement</TableHead>
                                    <TableHead>Notes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recent.transactions.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-muted-foreground h-24 text-center text-sm">
                                            No ledger entries yet.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    recent.transactions.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell className="text-muted-foreground text-xs">{formatDate(entry.recorded_at)}</TableCell>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <p className="text-foreground font-medium">{entry.product?.name ?? "Unknown item"}</p>
                                                    <p className="text-muted-foreground text-xs">{entry.product?.sku ?? "—"}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge className="bg-muted text-muted-foreground">{entry.event_type.replaceAll("_", " ")}</Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                {(entry.movement?.good_delta ?? 0) !== 0 || (entry.movement?.defective_delta ?? 0) !== 0
                                                    ? `Good ${entry.movement?.good_delta ?? 0}, Def ${entry.movement?.defective_delta ?? 0}`
                                                    : "—"}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">{entry.notes ?? "—"}</TableCell>
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
