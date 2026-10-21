import {
    ColumnDef,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from "@tanstack/react-table";
import * as React from "react";

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";

import type { ReactNode } from "react";
import { DataTablePagination } from "./data-table-pagination";

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
};

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination: PaginationMeta;
    /** The sort key the server applied. Must match the column ids. */
    sort?: string;
    direction?: "asc" | "desc";
    onSortChange?: (sort: string, direction: "asc" | "desc") => void;
    onPageChange?: (page: number, perPage: number) => void;
    isLoading?: boolean;
    toolbar?: ReactNode;
    onRowClick?: (row: TData) => void;
}

export function DataTable<TData, TValue>({
    columns,
    data,
    pagination,
    sort,
    direction = "desc",
    onSortChange,
    onPageChange,
    isLoading = false,
    toolbar,
    onRowClick,
}: DataTableProps<TData, TValue>) {
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});

    // Sorting and pagination belong to the server: the table renders the page
    // it was given and asks for a different one when a header is clicked.
    const sorting = React.useMemo<SortingState>(
        () => (sort ? [{ id: sort, desc: direction === "desc" }] : []),
        [sort, direction],
    );

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        pageCount: pagination.last_page,
        onSortingChange: (updater) => {
            const nextSorting = typeof updater === "function" ? updater(sorting) : updater;
            const next = nextSorting[0];

            if (!next) {
                onSortChange?.("", "desc");

                return;
            }

            onSortChange?.(next.id, next.desc ? "desc" : "asc");
        },
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        state: {
            sorting,
            columnVisibility,
            rowSelection,
            pagination: {
                pageIndex: pagination.current_page - 1,
                pageSize: pagination.per_page,
            },
        },
    });

    return (
        <div className="space-y-4">
            <div className="relative overflow-hidden rounded-lg border">
                {toolbar ? (
                    <div className="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between">{toolbar}</div>
                ) : null}
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
                    <TableBody className={cn(isLoading && "opacity-60 transition-opacity")}>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() && "selected"}
                                    className={cn("transition-colors", onRowClick && "hover:bg-muted/50 cursor-pointer")}
                                    onClick={(event) => {
                                        if (!onRowClick) {
                                            return;
                                        }

                                        const target = event.target as HTMLElement;
                                        if (
                                            target.closest("button") ||
                                            target.closest('[role="checkbox"]') ||
                                            target.closest("a") ||
                                            target.closest('[role="menu"]')
                                        ) {
                                            return;
                                        }

                                        onRowClick(row.original);
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
                {isLoading && (
                    <div className="pointer-events-none absolute inset-x-0 top-0 flex justify-center pt-3">
                        <span className="bg-background/90 text-muted-foreground rounded-full border px-3 py-1 text-xs shadow-sm">Updating…</span>
                    </div>
                )}
            </div>
            <DataTablePagination
                page={pagination.current_page}
                lastPage={pagination.last_page}
                perPage={pagination.per_page}
                total={pagination.total}
                from={pagination.from}
                to={pagination.to}
                isLoading={isLoading}
                onPageChange={onPageChange}
            />
        </div>
    );
}
