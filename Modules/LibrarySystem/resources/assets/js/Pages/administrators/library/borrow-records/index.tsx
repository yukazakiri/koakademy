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
import { ClipboardCheck, Plus, Search, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare const route: any;

interface BorrowRecordItem {
    id: number;
    book: { id?: number | null; title?: string | null };
    borrower: { name?: string | null; email?: string | null };
    borrowed_at?: string | null;
    due_date?: string | null;
    returned_at?: string | null;
    status: string;
    fine_amount: string | number;
    notes?: string | null;
    is_overdue: boolean;
    days_overdue: number;
}

interface Props {
    user: User;
    records: BorrowRecordItem[];
    stats: {
        total: number;
        borrowed: number;
        returned: number;
        lost: number;
        overdue: number;
    };
    filters: {
        search?: string | null;
        status?: string | null;
    };
    options: {
        statuses: { value: string; label: string }[];
    };
    flash?: {
        type: string;
        message: string;
    };
}

const statusStyles: Record<string, string> = {
    borrowed: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    returned: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    lost: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
};

const formatDate = (value?: string | null) => {
    if (!value) return "—";
    return new Date(value).toLocaleDateString();
};

export default function LibraryBorrowRecordsIndex({ user, records, stats, filters, options, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [deleteTarget, setDeleteTarget] = useState<BorrowRecordItem | null>(null);

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
        router.get(
            route("administrators.library.borrow-records.index"),
            { ...filters, search: term || null },
            { preserveState: true, replace: true },
        );
    }, 300);

    const handleStatusChange = (value: string) => {
        router.get(
            route("administrators.library.borrow-records.index"),
            { ...filters, status: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route("administrators.library.borrow-records.destroy", deleteTarget.id), {
            preserveState: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout user={user} title="Borrow Records">
            <Head title="Administrators • Borrow Records" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-amber-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                                    <ClipboardCheck className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Borrow Records</CardTitle>
                                    <CardDescription>Track circulation and overdue activity.</CardDescription>
                                </div>
                            </div>
                            <div className="text-muted-foreground flex flex-wrap gap-3 text-sm">
                                <span>Total records: {stats.total}</span>
                                <span>Borrowed: {stats.borrowed}</span>
                                <span>Overdue: {stats.overdue}</span>
                            </div>
                        </div>
                        <Button asChild className="gap-2">
                            <Link href={route("administrators.library.borrow-records.create")}>
                                <Plus className="h-4 w-4" />
                                Log Borrow
                            </Link>
                        </Button>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Circulation Entries</CardTitle>
                            <CardDescription>Search by book title or borrower name.</CardDescription>
                        </div>
                        <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <div className="relative w-full sm:w-64">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search records..."
                                    value={search}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setSearch(value);
                                        handleSearch(value);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select value={filters.status ?? "all"} onValueChange={handleStatusChange}>
                                <SelectTrigger className="w-full sm:w-48">
                                    <SelectValue placeholder="Filter status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.statuses.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
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
                                    <TableHead>Book</TableHead>
                                    <TableHead>Borrower</TableHead>
                                    <TableHead>Borrowed</TableHead>
                                    <TableHead>Due</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Fine</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-muted-foreground h-24 text-center text-sm">
                                            No borrow records found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    records.map((record) => (
                                        <TableRow key={record.id}>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <p className="text-foreground font-medium">{record.book?.title ?? "Unknown"}</p>
                                                    <p className="text-muted-foreground text-xs">#{record.id}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{record.borrower?.name ?? "Unknown"}</TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{formatDate(record.borrowed_at)}</TableCell>
                                            <TableCell className={`text-sm ${record.is_overdue ? "text-rose-500" : "text-muted-foreground"}`}>
                                                {formatDate(record.due_date)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={statusStyles[record.status] ?? "bg-muted text-muted-foreground"}>
                                                    {record.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{Number(record.fine_amount).toFixed(2)}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route("administrators.library.borrow-records.edit", record.id)}>Edit</Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => setDeleteTarget(record)}
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
                        <AlertDialogTitle>Delete borrow record?</AlertDialogTitle>
                        <AlertDialogDescription>This will remove the record for "{deleteTarget?.book?.title}".</AlertDialogDescription>
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
