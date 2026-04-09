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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Settings2 } from "lucide-react";

declare let route: any;

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    // Server-side pagination props
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
    filters?: Record<string, any>;
    routeName?: string; // Route to visit for server-side updates
    dataKey?: string; // Key for partial reloads
    isLoading?: boolean;
    onRowClick?: (row: TData) => void;
    selectionActions?: (selectedRows: TData[]) => React.ReactNode;
}

export function DataTable<TData, TValue>({
    columns,
    data,
    pagination,
    filters = {},
    routeName = "administrators.enrollments.index",
    dataKey,
    isLoading = false,
    onRowClick,
    selectionActions,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [internalLoading, setInternalLoading] = React.useState(false);

    const showLoading = isLoading || internalLoading;

    // Initialize sorting from URL if present
    React.useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const sort = urlParams.get("sort");
        const direction = urlParams.get("direction");
        if (sort) {
            setSorting([{ id: sort, desc: direction === "desc" }]);
        }
    }, []);

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        // We use manual pagination if pagination prop is provided
        manualPagination: !!pagination,
        pageCount: pagination?.last_page ?? -1,
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: (updater) => {
            const newSorting = typeof updater === "function" ? updater(sorting) : updater;
            setSorting(newSorting);

            // Trigger server-side sort
            if (newSorting.length > 0) {
                const { id, desc } = newSorting[0];
                router.get(
                    route(routeName),
                    { ...filters, sort: id, direction: desc ? "desc" : "asc" },
                    {
                        preserveState: true,
                        replace: true,
                        preserveScroll: true,
                        only: dataKey ? [dataKey, "filters"] : undefined,
                        onStart: () => setInternalLoading(true),
                        onFinish: () => setInternalLoading(false),
                    },
                );
            } else {
                // Reset sort
                router.get(
                    route(routeName),
                    { ...filters, sort: null, direction: null },
                    {
                        preserveState: true,
                        replace: true,
                        preserveScroll: true,
                        only: dataKey ? [dataKey, "filters"] : undefined,
                        onStart: () => setInternalLoading(true),
                        onFinish: () => setInternalLoading(false),
                    },
                );
            }
        },
        getSortedRowModel: getSortedRowModel(),
        onColumnFiltersChange: setColumnFilters,
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            pagination: {
                pageIndex: pagination ? pagination.current_page - 1 : 0,
                pageSize: pagination ? pagination.per_page : 10,
            },
        },
    });

    const selectedRows = table.getSelectedRowModel().rows.map((row) => row.original);
    const selectionActionsContent = selectionActions ? selectionActions(selectedRows) : null;

    // Function to handle page navigation via Inertia
    const navigateToPage = (url: string | null) => {
        if (url) {
            router.get(
                url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: dataKey ? [dataKey, "filters"] : undefined,
                    onStart: () => setInternalLoading(true),
                    onFinish: () => setInternalLoading(false),
                },
            );
        }
    };

    // Handle page size change
    const onPageSizeChange = (value: string) => {
        router.get(
            route(routeName),
            { ...filters, per_page: value, page: 1 },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: dataKey ? [dataKey, "filters"] : undefined,
                onStart: () => setInternalLoading(true),
                onFinish: () => setInternalLoading(false),
            },
        );
    };

    return (
        <div>
            <div className="flex items-center justify-between py-4">
                <div className="flex items-center space-x-2">
                    {pagination && (
                        <>
                            <p className="text-muted-foreground text-sm font-medium">Rows per page</p>
                            <Select
                                value={pagination.per_page > 200 || pagination.per_page === 100000 ? "all" : pagination.per_page.toString()}
                                onValueChange={onPageSizeChange}
                            >
                                <SelectTrigger className="h-8 w-[70px]">
                                    <SelectValue placeholder={pagination.per_page.toString()} />
                                </SelectTrigger>
                                <SelectContent side="top">
                                    {[10, 50, 150].map((pageSize) => (
                                        <SelectItem key={pageSize} value={`${pageSize}`}>
                                            {pageSize}
                                        </SelectItem>
                                    ))}
                                    <SelectItem value="all">All</SelectItem>
                                </SelectContent>
                            </Select>
                        </>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    {selectionActionsContent}
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
                                            {column.id.replace("_", " ")}
                                        </DropdownMenuCheckboxItem>
                                    );
                                })}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
            <div className="bg-card rounded-md border">
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
                        {showLoading ? (
                            // Skeleton Loading State
                            Array.from({ length: 10 }).map((_, rowIndex) => (
                                <TableRow key={`skeleton-row-${rowIndex}`}>
                                    {columns.map((_, colIndex) => (
                                        <TableCell key={`skeleton-cell-${rowIndex}-${colIndex}`}>
                                            <Skeleton className="bg-muted/50 h-6 w-full rounded" />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && "selected"}
                                    className={cn("animate-in fade-in-0 duration-300", onRowClick && "hover:bg-muted/50 cursor-pointer")}
                                    onClick={() => onRowClick?.(row.original)}
                                >
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

            {/* Pagination Controls */}
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
                                disabled={pagination.current_page === 1 || showLoading}
                            >
                                <span className="sr-only">Go to first page</span>
                                <ChevronsLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="h-8 w-8 p-0"
                                onClick={() => navigateToPage(pagination.prev_page_url)}
                                disabled={!pagination.prev_page_url || showLoading}
                            >
                                <span className="sr-only">Go to previous page</span>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="h-8 w-8 p-0"
                                onClick={() => navigateToPage(pagination.next_page_url)}
                                disabled={!pagination.next_page_url || showLoading}
                            >
                                <span className="sr-only">Go to next page</span>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="hidden h-8 w-8 p-0 lg:flex"
                                onClick={() => navigateToPage(route(routeName, { ...filters, page: pagination.last_page }))}
                                disabled={pagination.current_page === pagination.last_page || showLoading}
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
