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
import { BookOpen, Plus, Search, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare const route: any;

interface BookItem {
    id: number;
    title: string;
    isbn?: string | null;
    author: { id?: number | null; name?: string | null } | null;
    category: { id?: number | null; name?: string | null; color?: string | null } | null;
    status: string;
    available_copies: number;
    total_copies: number;
    publication_year?: number | null;
    location?: string | null;
    updated_at?: string | null;
    cover_image_url?: string | null;
}

interface Props {
    user: User;
    books: BookItem[];
    stats: {
        total_books: number;
        available_copies: number;
        borrowed_books: number;
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
    available: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    borrowed: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    maintenance: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
};

export default function LibraryBooksIndex({ user, books, stats, filters, options, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [deleteTarget, setDeleteTarget] = useState<BookItem | null>(null);

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
        router.get(route("administrators.library.books.index"), { ...filters, search: term || null }, { preserveState: true, replace: true });
    }, 300);

    const handleStatusChange = (value: string) => {
        router.get(
            route("administrators.library.books.index"),
            { ...filters, status: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route("administrators.library.books.destroy", deleteTarget.id), {
            preserveState: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout user={user} title="Library Catalog">
            <Head title="Administrators • Library Books" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-emerald-500/10 to-sky-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                    <BookOpen className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Library Books</CardTitle>
                                    <CardDescription>Track catalog inventory and availability.</CardDescription>
                                </div>
                            </div>
                            <div className="text-muted-foreground flex flex-wrap gap-3 text-sm">
                                <span>Total titles: {stats.total_books}</span>
                                <span>Copies available: {stats.available_copies}</span>
                                <span>Borrowed: {stats.borrowed_books}</span>
                            </div>
                        </div>
                        <Button asChild className="gap-2">
                            <Link href={route("administrators.library.books.create")}>
                                <Plus className="h-4 w-4" />
                                Add Book
                            </Link>
                        </Button>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Catalog Overview</CardTitle>
                            <CardDescription>Search by title, author, or ISBN.</CardDescription>
                        </div>
                        <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <div className="relative w-full sm:w-64">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search catalog..."
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
                                    <TableHead>Title</TableHead>
                                    <TableHead>Author</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Copies</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {books.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-muted-foreground h-24 text-center text-sm">
                                            No books match the current filters.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    books.map((book) => (
                                        <TableRow key={book.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    {book.cover_image_url ? (
                                                        <img
                                                            src={book.cover_image_url}
                                                            alt=""
                                                            className="h-12 w-9 rounded-md border object-cover"
                                                            loading="lazy"
                                                        />
                                                    ) : (
                                                        <div className="bg-muted text-muted-foreground flex h-12 w-9 items-center justify-center rounded-md border text-[10px] uppercase">
                                                            Cover
                                                        </div>
                                                    )}
                                                    <div className="space-y-1">
                                                        <p className="text-foreground font-medium">{book.title}</p>
                                                        <p className="text-muted-foreground text-xs">{book.isbn ? `ISBN ${book.isbn}` : "No ISBN"}</p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{book.author?.name ?? "Unknown"}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className="h-2 w-2 rounded-full"
                                                        style={{ backgroundColor: book.category?.color ?? "#64748b" }}
                                                    />
                                                    <span className="text-muted-foreground text-sm">{book.category?.name ?? "Uncategorized"}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {book.available_copies}/{book.total_copies}
                                            </TableCell>
                                            <TableCell>
                                                <Badge className={statusStyles[book.status] ?? "bg-muted text-muted-foreground"}>{book.status}</Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route("administrators.library.books.edit", book.id)}>Edit</Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => setDeleteTarget(book)}
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
                        <AlertDialogTitle>Delete book?</AlertDialogTitle>
                        <AlertDialogDescription>This will remove "{deleteTarget?.title}" from the catalog.</AlertDialogDescription>
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
