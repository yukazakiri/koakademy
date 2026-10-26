import {
    ColumnDef,
    OnChangeFn,
    RowSelectionState,
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
import { DataTableViewOptions } from "./data-table-view-options";

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
    onRowClick?: (row: TData) => void;
    getRowId: (row: TData) => string;
    rowSelection: RowSelectionState;
    onRowSelectionChange: OnChangeFn<RowSelectionState>;
    emptyState?: ReactNode;
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
    onRowClick,
    getRowId,
    rowSelection,
    onRowSelectionChange,
    emptyState,
}: DataTableProps<TData, TValue>) {
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({ period: false });

    // Sorting and pagination belong to the server: the table renders the page
    // it was given and asks for a different one when a header is clicked.
    const sorting = React.useMemo<SortingState>(() => (sort ? [{ id: sort, desc: direction === "desc" }] : []), [sort, direction]);

    const table = useReactTable({
        data,
        columns,
        getRowId,
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
        onRowSelectionChange,
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
        <div aria-busy={isLoading}>
            <div className="relative overflow-hidden">
                <Table aria-label="Class sections">
                    <TableHeader className="bg-muted/40">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead
                                        key={header.id}
                                        className={cn("text-muted-foreground h-12 px-4 text-sm", header.column.id === "select" && "w-12 px-3")}
                                    >
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
                                            target.closest("label") ||
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
                                        <TableCell key={cell.id} className={cn("px-4 py-4", cell.column.id === "select" && "w-12 px-3")}>
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={table.getVisibleLeafColumns().length} className="whitespace-normal">
                                    {emptyState ?? <p className="text-muted-foreground p-8 text-center">No classes found.</p>}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <DataTablePagination
                viewOptions={<DataTableViewOptions table={table} />}
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
