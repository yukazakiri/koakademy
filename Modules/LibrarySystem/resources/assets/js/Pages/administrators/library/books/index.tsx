import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { BookOpen, Plus, Search } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";
import { columns, type Book } from "./columns";
import { DataTable } from "./data-table";

declare const route: any; // eslint-disable-line @typescript-eslint/no-explicit-any

interface PaginationInfo {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    from: number;
    to: number;
}

interface Props {
    user: User;
    books: {
        data: Book[];
    } & PaginationInfo;
    stats: {
        total_books: number;
        available_copies: number;
        borrowed_books: number;
    };
    filters: {
        search?: string | null;
        status?: string | null;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    options: {
        statuses: { value: string; label: string }[];
        per_page: { value: number; label: string }[];
    };
    flash?: {
        type: string;
        message: string;
    };
}

export default function LibraryBooksIndex({ user, books, stats, filters, options, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");

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
            { ...filters, status: value === "all" ? null : value, page: 1 },
            { preserveState: true, replace: true },
        );
    };

    const handleSortFieldChange = (value: string) => {
        router.get(
            route("administrators.library.books.index"),
            { ...filters, sort: value, page: 1 },
            { preserveState: true, replace: true },
        );
    };

    const handleSortDirectionChange = (value: string) => {
        router.get(
            route("administrators.library.books.index"),
            { ...filters, direction: value, page: 1 },
            { preserveState: true, replace: true },
        );
    };

    const sortFieldOptions = [
        { value: "created_at", label: "Recently Added" },
        { value: "publication_year", label: "Publication Year" },
        { value: "title", label: "Title" },
    ];

    const sortDirectionOptions = [
        { value: "desc", label: "Newest / Z → A" },
        { value: "asc", label: "Oldest / A → Z" },
    ];

    const pagination = {
        current_page: books.current_page,
        last_page: books.last_page,
        per_page: books.per_page,
        total: books.total,
        next_page_url: books.next_page_url,
        prev_page_url: books.prev_page_url,
        from: books.from,
        to: books.to,
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
                            <CardDescription>Search by title, author, ISBN, call number, or accession number.</CardDescription>
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
                                <SelectTrigger className="w-full sm:w-44">
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
                            <Select value={filters.sort ?? "created_at"} onValueChange={handleSortFieldChange}>
                                <SelectTrigger className="w-full sm:w-44">
                                    <SelectValue placeholder="Sort by" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sortFieldOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.direction ?? "desc"} onValueChange={handleSortDirectionChange}>
                                <SelectTrigger className="w-full sm:w-44">
                                    <SelectValue placeholder="Order" />
                                </SelectTrigger>
                                <SelectContent>
                                    {sortDirectionOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <DataTable columns={columns} data={books.data} pagination={pagination} filters={filters} />
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
