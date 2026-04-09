import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ClipboardList, Package, Server, Wrench } from "lucide-react";

declare const route: any;

interface InventoryStats {
    total_items: number;
    tools: number;
    network_devices: number;
    borrowable_items: number;
    active_borrowings: number;
    overdue_borrowings: number;
}

interface RecentItem {
    id: number;
    name: string;
    sku: string;
    item_type: string;
    stock_quantity: number;
    unit: string;
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
    };
}

const statusStyles: Record<string, string> = {
    borrowed: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    returned: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    overdue: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
    lost: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
};

const typeStyles: Record<string, string> = {
    Tool: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    Router: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    NVR: "bg-indigo-500/10 text-indigo-700 dark:text-indigo-300",
    CCTV: "bg-sky-500/10 text-sky-700 dark:text-sky-300",
};

const formatDate = (value?: string | null) => {
    if (!value) return "—";
    return new Date(value).toLocaleDateString();
};

export default function InventoryIndex({ user, stats, recent }: Props) {
    return (
        <AdminLayout user={user} title="Inventory System">
            <Head title="Administrators • Inventory System" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-r from-slate-900/5 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-900/10 text-slate-700 dark:text-slate-200">
                                <Package className="h-6 w-6" />
                            </div>
                            <div>
                                <CardTitle className="text-2xl">Inventory Control Center</CardTitle>
                                <CardDescription>Track tools, network devices, and borrowing activity in one place.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={route("administrators.inventory.items.create", { item_type: "tool" })}>Add Tool</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.inventory.items.create", { item_type: "router" })}>Add Network Device</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.inventory.borrowings.create")}>Log Borrowing</Link>
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
                                <p className="text-muted-foreground text-xs">{stats.borrowable_items} borrowable</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900/10 text-slate-700 dark:text-slate-200">
                                <Package className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Tool Inventory</p>
                                <p className="text-2xl font-semibold">{stats.tools}</p>
                                <p className="text-muted-foreground text-xs">Tools tracked for lending</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                <Wrench className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Network Devices</p>
                                <p className="text-2xl font-semibold">{stats.network_devices}</p>
                                <p className="text-muted-foreground text-xs">Routers, NVRs, CCTV</p>
                            </div>
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600">
                                <Server className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background/60 border-0">
                        <CardContent className="flex items-center justify-between gap-4 p-5">
                            <div>
                                <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Active Borrowings</p>
                                <p className="text-2xl font-semibold">{stats.active_borrowings}</p>
                                <p className="text-muted-foreground text-xs">{stats.overdue_borrowings} overdue</p>
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
                                <CardDescription>Latest changes across tools and devices.</CardDescription>
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
                                                    <div className="space-y-1">
                                                        <p className="text-foreground font-medium">{item.name}</p>
                                                        <p className="text-muted-foreground text-xs">{item.sku}</p>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={typeStyles[item.item_type] ?? "bg-muted text-muted-foreground"}>
                                                        {item.item_type}
                                                    </Badge>
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
            </div>
        </AdminLayout>
    );
}
