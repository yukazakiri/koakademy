"use client";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFacetedRowModel,
    getFacetedUniqueValues,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import { X } from "lucide-react";
import * as React from "react";
import { DataTablePagination } from "./data-table-pagination";
import { DataTableViewOptions } from "./data-table-view-options";

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
}

export function DataTable<TData, TValue>({ columns, data }: DataTableProps<TData, TValue>) {
    const [rowSelection, setRowSelection] = React.useState({});
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([]);
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [pagination, setPagination] = React.useState({
        pageIndex: 0,
        pageSize: 10,
    });

    const table = useReactTable({
        data,
        columns,
        state: {
            sorting,
            columnVisibility,
            rowSelection,
            columnFilters,
            pagination,
        },
        enableRowSelection: true,
        onRowSelectionChange: setRowSelection,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onColumnVisibilityChange: setColumnVisibility,
        onPaginationChange: setPagination,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFacetedRowModel: getFacetedRowModel(),
        getFacetedUniqueValues: getFacetedUniqueValues(),
    });

    const isFiltered = table.getState().columnFilters.length > 0;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex flex-1 flex-col gap-4 sm:flex-row sm:items-center">
                    <Input
                        placeholder="Search subjects..."
                        value={(table.getColumn("subject")?.getFilterValue() as string) ?? ""}
                        onChange={(event) => table.getColumn("subject")?.setFilterValue(event.target.value)}
                        className="h-8 w-full sm:w-[250px] lg:w-[300px]"
                    />
                    <div className="flex flex-wrap gap-2">
                        {table.getColumn("status") && (
                            <Select
                                value={(table.getColumn("status")?.getFilterValue() as string) ?? "all"}
                                onValueChange={(value) => {
                                    table.getColumn("status")?.setFilterValue(value === "all" ? undefined : value);
                                }}
                            >
                                <SelectTrigger className="h-8 w-[130px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="open">Open</SelectItem>
                                    <SelectItem value="in_progress">In Progress</SelectItem>
                                    <SelectItem value="resolved">Resolved</SelectItem>
                                    <SelectItem value="closed">Closed</SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                        {table.getColumn("priority") && (
                            <Select
                                value={(table.getColumn("priority")?.getFilterValue() as string) ?? "all"}
                                onValueChange={(value) => {
                                    table.getColumn("priority")?.setFilterValue(value === "all" ? undefined : value);
                                }}
                            >
                                <SelectTrigger className="h-8 w-[130px]">
                                    <SelectValue placeholder="Priority" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Priority</SelectItem>
                                    <SelectItem value="low">Low</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="critical">Critical</SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                        {table.getColumn("type") && (
                            <Select
                                value={(table.getColumn("type")?.getFilterValue() as string) ?? "all"}
                                onValueChange={(value) => {
                                    table.getColumn("type")?.setFilterValue(value === "all" ? undefined : value);
                                }}
                            >
                                <SelectTrigger className="h-8 w-[130px]">
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="support">Support</SelectItem>
                                    <SelectItem value="issue">Issue</SelectItem>
                                    <SelectItem value="recommendation">Recommendation</SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                        {isFiltered && (
                            <Button variant="ghost" onClick={() => table.resetColumnFilters()} className="h-8 px-2 lg:px-3">
                                Reset
                                <X className="ml-2 h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
                <DataTableViewOptions table={table} />
            </div>
            <div className="overflow-hidden rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id} colSpan={header.colSpan}>
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
            <DataTablePagination table={table} />
        </div>
    );
}
