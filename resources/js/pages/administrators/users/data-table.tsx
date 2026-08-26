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
    options?: {
        roles?: string[];
        schools?: { id: number; name: string }[];
        departments?: { id: number; name: string }[];
    };
}

export function DataTable<TData, TValue>({ columns, data, options }: DataTableProps<TData, TValue>) {
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
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <label htmlFor="users-search" className="sr-only">
                        Search users
                    </label>
                    <Input
                        id="users-search"
                        placeholder="Search by name or email..."
                        aria-label="Search users by name or email"
                        value={(table.getColumn("name")?.getFilterValue() as string) ?? ""}
                        onChange={(event) => table.getColumn("name")?.setFilterValue(event.target.value)}
                        className="h-10 w-full sm:w-[280px]"
                    />
                    <div className="flex flex-wrap gap-2">
                        {table.getColumn("role") && options?.roles && (
                            <Select
                                value={(table.getColumn("role")?.getFilterValue() as string) ?? "all"}
                                onValueChange={(value) => {
                                    if (value === "all") {
                                        table.getColumn("role")?.setFilterValue(undefined);
                                    } else {
                                        table.getColumn("role")?.setFilterValue(value);
                                    }
                                }}
                            >
                                <SelectTrigger className="h-10 w-[150px]">
                                    <SelectValue placeholder="Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Roles</SelectItem>
                                    {options.roles.map((role) => (
                                        <SelectItem key={role} value={role}>
                                            {role}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        {table.getColumn("email_verified_at") && (
                            <Select
                                value={(table.getColumn("email_verified_at")?.getFilterValue() as string) ?? "all"}
                                onValueChange={(value) => {
                                    if (value === "all") {
                                        table.getColumn("email_verified_at")?.setFilterValue(undefined);
                                    } else {
                                        table.getColumn("email_verified_at")?.setFilterValue(value);
                                    }
                                }}
                            >
                                <SelectTrigger className="h-10 w-[150px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="verified">Verified</SelectItem>
                                    <SelectItem value="unverified">Unverified</SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                        {table.getColumn("security_two_factor_enabled") && (
                            <Select
                                value={(table.getColumn("security_two_factor_enabled")?.getFilterValue() as string) ?? "all"}
                                onValueChange={(value) => {
                                    if (value === "all") {
                                        table.getColumn("security_two_factor_enabled")?.setFilterValue(undefined);
                                    } else {
                                        table.getColumn("security_two_factor_enabled")?.setFilterValue(value);
                                    }
                                }}
                            >
                                <SelectTrigger className="h-10 w-[150px]">
                                    <SelectValue placeholder="2FA" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All 2FA states</SelectItem>
                                    <SelectItem value="enabled">Protected</SelectItem>
                                    <SelectItem value="disabled">Off</SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                        {isFiltered && (
                            <Button variant="ghost" onClick={() => table.resetColumnFilters()} className="h-10 px-3">
                                Reset
                                <X className="ml-2 h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
                <DataTableViewOptions table={table} />
            </div>
            <div className="overflow-x-auto rounded-lg border">
                <Table className="min-w-[760px]">
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id} className="bg-muted/30 hover:bg-muted/30">
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead
                                            key={header.id}
                                            colSpan={header.colSpan}
                                            className="h-10 text-xs font-semibold tracking-wide uppercase"
                                        >
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
                                <TableRow key={row.id} data-state={row.getIsSelected() && "selected"} className="group">
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className="py-3">
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-28 text-center">
                                    <p className="font-medium">No users found</p>
                                    <p className="text-muted-foreground mt-1 text-sm">Try a different search or clear the active filters.</p>
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
