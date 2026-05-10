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

import { router } from "@inertiajs/react";
import { DataTablePagination } from "./data-table-pagination";
import { DataTableViewOptions } from "./data-table-view-options";

declare let route: any;

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
    filters?: Record<string, any>;
    routeName?: string;
}

export function DataTable<TData, TValue>({
    columns,
    data,
    pagination,
    filters = {},
    routeName = "administrators.classes.index",
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});

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
        onPaginationChange: (updater) => {
            const currentPaginationState = {
                pageIndex: pagination ? pagination.current_page - 1 : 0,
                pageSize: pagination ? pagination.per_page : 10,
            };

            const nextState = typeof updater === "function" ? updater(currentPaginationState) : updater;

            if (nextState.pageIndex !== currentPaginationState.pageIndex || nextState.pageSize !== currentPaginationState.pageSize) {
                router.get(
                    route(routeName),
                    {
                        ...filters,
                        page: nextState.pageIndex + 1,
                        per_page: nextState.pageSize,
                    },
                    { preserveState: true, replace: true },
                );
            }
        },
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

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <DataTableViewOptions table={table} />
            </div>
            <div className="overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && "selected"}
                                    className="hover:bg-muted/50 cursor-pointer transition-colors"
                                    onClick={(e) => {
                                        const target = e.target as HTMLElement;
                                        if (
                                            target.closest("button") ||
                                            target.closest('[role="checkbox"]') ||
                                            target.closest("a") ||
                                            target.closest('[role="menu"]')
                                        ) {
                                            return;
                                        }

                                        const classRow = row.original as any;
                                        if (classRow?.id) {
                                            router.visit(route("administrators.classes.show", { class: classRow.id }));
                                        }
                                    }}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-32 text-center">
                                    <p className="font-medium">No classes found</p>
                                    <p className="text-muted-foreground text-sm">Try adjusting your search or filters.</p>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <DataTablePagination table={table} />
        </div>
    );
}
