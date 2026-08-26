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

import { FilterInput } from "@/components/reui/filters";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Search, Settings2 } from "lucide-react";

declare let route: (...args: unknown[]) => string;

type InertiaGetPayload = NonNullable<Parameters<typeof router.get>[1]>;

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
    filters?: Record<string, string | number | boolean | null | undefined>;
    routeName?: string; // Route to visit for server-side updates
    dataKey?: string; // Key for partial reloads
    isLoading?: boolean;
    onRowClick?: (row: TData) => void;
    selectionActions?: (selectedRows: TData[], helpers: { clearSelection: () => void }) => React.ReactNode;
    getRowId?: (row: TData) => string;
    tableVariant?: "default" | "spreadsheet";
}

export function DataTable<TData, TValue>({
    columns,
    data,
    pagination,
    filters = {},
    routeName,
    dataKey,
    isLoading = false,
    onRowClick,
    selectionActions,
    getRowId,
    tableVariant = "default",
}: DataTableProps<TData, TValue>) {
    const isServerSide = !!routeName && !!pagination;
    const isSpreadsheet = tableVariant === "spreadsheet";

    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [internalLoading, setInternalLoading] = React.useState(false);

    const showLoading = isLoading || internalLoading;
    const showSkeleton = showLoading && data.length === 0;

    const visitTable = React.useCallback(
        (url: string, query: InertiaGetPayload = {}) => {
            if (!routeName) return;

            router.cancelAll();

            router.get(url, query, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: dataKey ? [dataKey, "filters"] : undefined,
                onStart: () => setInternalLoading(true),
                onFinish: () => setInternalLoading(false),
            });
        },
        [dataKey, routeName],
    );

    // Initialize sorting from URL if present
    React.useEffect(() => {
        if (!isServerSide) return;

        const urlParams = new URLSearchParams(window.location.search);
        const sort = urlParams.get("sort");
        const direction = urlParams.get("direction");
        if (sort) {
            setSorting([{ id: sort, desc: direction === "desc" }]);
        }
    }, [isServerSide]);

    const table = useReactTable({
        data,
        columns,
        getRowId,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: isServerSide,
        manualSorting: isServerSide,
        pageCount: pagination?.last_page ?? -1,
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: isServerSide
            ? (updater) => {
                  const newSorting = typeof updater === "function" ? updater(sorting) : updater;
                  setSorting(newSorting);

                  if (newSorting.length > 0) {
                      const { id, desc } = newSorting[0];
                      visitTable(route(routeName), { ...filters, sort: id, direction: desc ? "desc" : "asc", page: 1 });
                  } else {
                      visitTable(route(routeName), { ...filters, sort: null, direction: null, page: 1 });
                  }
              }
            : setSorting,
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
            ...(isServerSide && {
                pagination: {
                    pageIndex: pagination ? pagination.current_page - 1 : 0,
                    pageSize: pagination ? pagination.per_page : 10,
                },
            }),
        },
    });

    const selectedRows = table.getSelectedRowModel().rows.map((row) => row.original);
    const selectionActionsContent = selectionActions
        ? selectionActions(selectedRows, { clearSelection: () => setRowSelection({}) })
        : null;
    const clientPageCount = Math.max(table.getPageCount(), 1);
    const clientCurrentPage = Math.min(table.getState().pagination.pageIndex + 1, clientPageCount);
    const pageSizeOptions = isServerSide ? [10, 50, 150] : [10, 25, 50, 100];
    const selectedPageSize = isServerSide
        ? pagination && (pagination.per_page > 200 || pagination.per_page === 100000)
            ? "all"
            : pagination?.per_page.toString()
        : table.getState().pagination.pageSize >= 100000
          ? "all"
          : table.getState().pagination.pageSize.toString();

    // Function to handle page navigation via Inertia
    const navigateToPage = (url: string | null) => {
        if (url) {
            visitTable(url);
        }
    };

    // Handle page size change
    const onPageSizeChange = (value: string) => {
        if (isServerSide) {
            if (!routeName) return;

            visitTable(route(routeName), { ...filters, per_page: value, page: 1 });

            return;
        }

        table.setPageIndex(0);
        table.setPageSize(value === "all" ? 100000 : Number(value));
    };

    const firstVisibleRow =
        table.getFilteredRowModel().rows.length === 0 ? 0 : table.getState().pagination.pageIndex * table.getState().pagination.pageSize + 1;
    const lastVisibleRow = Math.min(
        (table.getState().pagination.pageIndex + 1) * table.getState().pagination.pageSize,
        table.getFilteredRowModel().rows.length,
    );

    return (
        <div>
            <div className="flex items-center justify-between gap-3 py-3">
                <div className="flex items-center space-x-2">
                    {(pagination || !isServerSide) && (
                        <>
                            <p className="text-muted-foreground hidden text-xs font-medium sm:block">Rows per page</p>
                            <Select value={selectedPageSize} onValueChange={onPageSizeChange}>
                                <SelectTrigger className="h-8 w-[70px]">
                                    <SelectValue placeholder="Rows" />
                                </SelectTrigger>
                                <SelectContent side="top">
                                    {pageSizeOptions.map((pageSize) => (
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
                            <Button variant="outline" size="sm" className="border-border/70 bg-background/60 ml-auto h-8 gap-1.5">
                                <Settings2 className="mr-2 h-4 w-4" />
                                <span className="hidden sm:inline">View</span>
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
            <div
                className={cn(
                    "border-border/70 relative border",
                    isSpreadsheet ? "bg-background max-h-[680px] overflow-auto rounded-md" : "bg-background/20 overflow-hidden rounded-lg",
                )}
            >
                <Table className={cn("min-w-[760px]", isSpreadsheet && "min-w-[1280px] border-separate border-spacing-0")}>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow
                                key={headerGroup.id}
                                className={cn(
                                    isSpreadsheet ? "bg-muted/90 hover:bg-muted/90 sticky top-0 z-10 backdrop-blur" : "bg-muted/25 hover:bg-muted/25",
                                )}
                            >
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead
                                            key={header.id}
                                            className={cn(
                                                "text-muted-foreground h-10 px-3 text-[10px] font-semibold tracking-[0.1em] uppercase",
                                                isSpreadsheet && "border-border/60 bg-muted/90 border-r last:border-r-0",
                                            )}
                                        >
                                            {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                        {!isServerSide && (
                            <TableRow className={cn(isSpreadsheet && "bg-muted/90 hover:bg-muted/90 sticky top-10 z-[9] backdrop-blur")}>
                                {table.getHeaderGroups()[0].headers.map((header) => (
                                    <TableHead
                                        key={`filter-${header.id}`}
                                        className={cn("px-3 py-2", isSpreadsheet && "border-border/60 bg-muted/90 border-r last:border-r-0")}
                                    >
                                        {header.column.getCanFilter() && header.column.id !== "select" && header.column.id !== "actions"
                                            ? (() => {
                                                  const filterLabel = header.column.id.replace(/_/g, " ");
                                                  const filterValue = (header.column.getFilterValue() as string) ?? "";

                                                  if (isSpreadsheet) {
                                                      return (
                                                          <FilterInput
                                                              field={{
                                                                  key: header.column.id,
                                                                  label: filterLabel,
                                                                  prefix: <Search className="size-3" aria-hidden="true" />,
                                                              }}
                                                              aria-label={`Filter ${filterLabel}`}
                                                              placeholder={`Filter ${filterLabel}...`}
                                                              value={filterValue}
                                                              onChange={(e) => header.column.setFilterValue(e.target.value)}
                                                              className={cn(
                                                                  "border-input bg-background/95 focus-within:border-primary focus-within:ring-primary/20 w-full min-w-0 shadow-sm focus-within:ring-2 dark:border-zinc-700/90 dark:bg-zinc-950/70",
                                                                  "[&_[data-slot=input-group-control]]:h-7 [&_[data-slot=input-group-control]]:text-xs",
                                                                  "[&_[data-slot=input-group-control]]:text-foreground [&_[data-slot=input-group-control]]:placeholder:text-muted-foreground/80",
                                                                  filterValue && "border-primary/60 bg-primary/5",
                                                              )}
                                                          />
                                                      );
                                                  }

                                                  return (
                                                      <Input
                                                          placeholder={`Filter ${filterLabel}...`}
                                                          value={filterValue}
                                                          onChange={(e) => header.column.setFilterValue(e.target.value)}
                                                          className="h-8 text-xs"
                                                      />
                                                  );
                                              })()
                                            : null}
                                    </TableHead>
                                ))}
                            </TableRow>
                        )}
                    </TableHeader>
                    <TableBody className={cn(showLoading && !showSkeleton && "opacity-60 transition-opacity")}>
                        {showSkeleton ? (
                            Array.from({ length: 10 }).map((_, rowIndex) => (
                                <TableRow key={`skeleton-row-${rowIndex}`}>
                                    {columns.map((_, colIndex) => (
                                        <TableCell
                                            key={`skeleton-cell-${rowIndex}-${colIndex}`}
                                            className={cn("px-3 py-3", isSpreadsheet && "border-border/50 border-r last:border-r-0")}
                                        >
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
                                    className={cn(
                                        "group animate-in fade-in-0 duration-300",
                                        isSpreadsheet
                                            ? "even:bg-muted/10 hover:bg-blue-500/[0.045] data-[state=selected]:bg-blue-500/10"
                                            : onRowClick && "hover:bg-primary/[0.035] cursor-pointer",
                                        onRowClick && "cursor-pointer",
                                    )}
                                    onClick={(event) => {
                                        const target = event.target as HTMLElement;
                                        if (
                                            target.closest("button") ||
                                            target.closest('[role="checkbox"]') ||
                                            target.closest("a") ||
                                            target.closest('[role="menu"]')
                                        ) {
                                            return;
                                        }

                                        onRowClick?.(row.original);
                                    }}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cn("px-3 py-3", isSpreadsheet && "border-border/50 border-r last:border-r-0")}
                                        >
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-28 px-3 text-center">
                                    <div className="text-muted-foreground flex flex-col items-center gap-1 text-sm">
                                        <span className="text-foreground font-medium">No enrollment records found</span>
                                        <span>Try adjusting your search or filters.</span>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                {showLoading && !showSkeleton && (
                    <div className="pointer-events-none absolute inset-x-0 top-0 flex justify-center pt-3">
                        <span className="bg-background/90 text-muted-foreground rounded-full border px-3 py-1 text-xs shadow-sm">Updating…</span>
                    </div>
                )}
            </div>

            {/* Pagination Controls */}
            <div className="flex items-center justify-between gap-3 py-3">
                <div className="text-muted-foreground flex-1 text-sm">
                    {pagination ? (
                        <>
                            Showing {pagination.from} to {pagination.to} of {pagination.total} entries
                        </>
                    ) : (
                        <>
                            Showing {firstVisibleRow} to {lastVisibleRow} of {table.getFilteredRowModel().rows.length} row(s)
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
                    <div className="flex items-center gap-2">
                        <p className="text-sm font-medium">
                            Page {clientCurrentPage} of {clientPageCount}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                className="hidden h-8 w-8 p-0 lg:flex"
                                onClick={() => table.setPageIndex(0)}
                                disabled={!table.getCanPreviousPage() || showLoading}
                            >
                                <span className="sr-only">Go to first page</span>
                                <ChevronsLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="h-8 w-8 p-0"
                                onClick={() => table.previousPage()}
                                disabled={!table.getCanPreviousPage() || showLoading}
                            >
                                <span className="sr-only">Go to previous page</span>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="h-8 w-8 p-0"
                                onClick={() => table.nextPage()}
                                disabled={!table.getCanNextPage() || showLoading}
                            >
                                <span className="sr-only">Go to next page</span>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                className="hidden h-8 w-8 p-0 lg:flex"
                                onClick={() => table.setPageIndex(clientPageCount - 1)}
                                disabled={!table.getCanNextPage() || showLoading}
                            >
                                <span className="sr-only">Go to last page</span>
                                <ChevronsRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
