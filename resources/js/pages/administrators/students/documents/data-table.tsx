import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import * as React from "react";

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Search, Settings2, X } from "lucide-react";

declare let route: (name: string, params?: number | Record<string, unknown>) => string;

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        from: number;
        to: number;
    };
    filters?: Record<string, unknown>;
    routeName?: string;
    searchPlaceholder?: string;
}

export function DataTable<TData, TValue>({
    columns,
    data,
    pagination,
    filters = {},
    routeName = "administrators.students.documents.list",
    searchPlaceholder = "Search...",
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [globalFilter, setGlobalFilter] = React.useState("");

    React.useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const sort = urlParams.get("sort");
        const direction = urlParams.get("direction");
        const search = urlParams.get("search");
        if (sort) {
            setSorting([{ id: sort, desc: direction === "desc" }]);
        }
        if (search) {
            setGlobalFilter(search);
        }
    }, []);

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: !!pagination,
        pageCount: pagination?.last_page ?? -1,
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: (updater) => {
            const newSorting = typeof updater === "function" ? updater(sorting) : updater;
            setSorting(newSorting);

            if (newSorting.length > 0) {
                const { id, desc } = newSorting[0];
                router.get(route(routeName), { ...filters, sort: id, direction: desc ? "desc" : "asc" }, { preserveState: true, replace: true });
            } else {
                router.get(route(routeName), { ...filters, sort: null, direction: null }, { preserveState: true, replace: true });
            }
        },
        getSortedRowModel: getSortedRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        onGlobalFilterChange: setGlobalFilter,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            globalFilter,
            pagination: {
                pageIndex: pagination ? pagination.current_page - 1 : 0,
                pageSize: pagination ? pagination.per_page : 10,
            },
        },
    });

    const navigateToPage = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true, replace: true });
        }
    };

    const handlePerPageChange = (value: string) => {
        router.get(route(routeName), { ...filters, per_page: value, page: 1 }, { preserveState: true, replace: true });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route(routeName), { ...filters, search: globalFilter, page: 1 }, { preserveState: true, replace: true });
    };

    const clearSearch = () => {
        setGlobalFilter("");
        router.get(route(routeName), { ...filters, search: null, page: 1 }, { preserveState: true, replace: true });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-1 items-center gap-2">
                    <form onSubmit={handleSearch} className="flex w-full max-w-sm items-center space-x-2">
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input
                                type="text"
                                placeholder={searchPlaceholder}
                                value={globalFilter}
                                onChange={(e) => setGlobalFilter(e.target.value)}
                                className="pl-8"
                            />
                            {globalFilter && (
                                <button
                                    type="button"
                                    onClick={clearSearch}
                                    className="text-muted-foreground hover:text-foreground absolute top-2.5 right-2"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </form>
                </div>

                <div className="flex items-center gap-2">
                    {pagination && (
                        <div className="flex items-center space-x-2">
                            <p className="text-sm font-medium">Rows per page</p>
                            <Select value={`${pagination.per_page}`} onValueChange={handlePerPageChange}>
                                <SelectTrigger className="h-8 w-[70px]">
                                    <SelectValue placeholder={pagination.per_page} />
                                </SelectTrigger>
                                <SelectContent side="top">
                                    {[10, 20, 50, 100].map((pageSize) => (
                                        <SelectItem key={pageSize} value={`${pageSize}`}>
                                            {pageSize}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="ml-auto">
                                <Settings2 className="mr-2 h-4 w-4" />
                                View
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {table
                                .getAllColumns()
                                .filter((column) => column.getCanHide())
                                .map((column) => {
                                    return (
                                        <DropdownMenuCheckboxItem
                                            key={column.id}
                                            className="capitalize"
                                            checked={column.getIsVisible()}
                                            onCheckedChange={(value) => column.toggleVisibility(!!value)}
                                        >
                                            {column.id.replace(/_/g, " ")}
                                        </DropdownMenuCheckboxItem>
                                    );
                                })}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} data-state={row.getIsSelected() && "selected"}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                    No results.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex items-center justify-between space-x-2 py-4">
                <div className="text-muted-foreground flex-1 text-sm">
                    {pagination ? (
                        <>
                            Showing {pagination.from} to {pagination.to} of {pagination.total} entries
                        </>
                    ) : (
                        <>
                            {table.getFilteredSelectedRowModel().rows.length} of {table.getFilteredRowModel().rows.length} row(s) selected.
                        </>
                    )}
                </div>

                {pagination ? (
                    <div className="flex items-center space-x-2">
                        <div className="flex items-center space-x-2">
                            <p className="text-sm font-medium">
                                Page {pagination.current_page} of {pagination.last_page}
                            </p>
                        </div>
                        <div className="flex items-center space-x-2">
                            <Button
                                variant="outline"
                                className="hidden h-8 w-8 p-0 lg:flex"
                                onClick={() => navigateToPage(route(routeName, { ...filters, page: 1 }))}
                                disabled={pagination.current_page === 1}
                            >
                                <span className="sr-only">Go to first page</span>
                                <ChevronsLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="h-8 w-8 p-0"
                                onClick={() => navigateToPage(pagination.prev_page_url)}
                                disabled={!pagination.prev_page_url}
                            >
                                <span className="sr-only">Go to previous page</span>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="h-8 w-8 p-0"
                                onClick={() => navigateToPage(pagination.next_page_url)}
                                disabled={!pagination.next_page_url}
                            >
                                <span className="sr-only">Go to next page</span>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="hidden h-8 w-8 p-0 lg:flex"
                                onClick={() => navigateToPage(route(routeName, { ...filters, page: pagination.last_page }))}
                                disabled={pagination.current_page === pagination.last_page}
                            >
                                <span className="sr-only">Go to last page</span>
                                <ChevronsRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="space-x-2">
                        <Button variant="outline" size="sm" onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()}>
                            Previous
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}>
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
