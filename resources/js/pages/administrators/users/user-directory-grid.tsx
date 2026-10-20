"use client";

import { Badge } from "@/components/reui/badge";
import { DataGrid, DataGridContainer, dataGridFeatures, type DataGridFeatures } from "@/components/reui/data-grid/data-grid";
import { DataGridColumnFilter } from "@/components/reui/data-grid/data-grid-column-filter";
import { DataGridColumnVisibility } from "@/components/reui/data-grid/data-grid-column-visibility";
import { DataGridPagination } from "@/components/reui/data-grid/data-grid-pagination";
import { DataGridScrollArea } from "@/components/reui/data-grid/data-grid-scroll-area";
import { DataGridTable } from "@/components/reui/data-grid/data-grid-table";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { edit, show } from "@/routes/administrators/users";
import { Link } from "@inertiajs/react";
import { ColumnDef, ColumnFiltersState, PaginationState, Row, SortingState, useTable } from "@tanstack/react-table-v9";
import { CircleSlash, FileEdit, KeyRound, MoreHorizontal, Search, Settings2, ShieldCheck, UserCog, X } from "lucide-react";
import { useMemo, useState } from "react";
import type { ActionType } from "./index";

export interface DirectoryUser {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    role: string;
    school?: { id: number; name: string } | null;
    department?: { id: number; name: string } | null;
    email_verified_at: string | null;
    security_two_factor_enabled?: boolean | null;
    mfa_configured?: boolean;
    suspended_at?: string | null;
    suspension_reason?: string | null;
    last_active_at?: string | null;
    last_login_at?: string | null;
    created_at: string | null;
    capabilities: { view: boolean; edit: boolean; update: boolean; delete: boolean; manage_access: boolean };
}

interface DirectoryOptions {
    roles: string[];
    schools: { id: number; name: string }[];
    departments: { id: number; name: string }[];
}

interface DirectoryGridProps {
    users: DirectoryUser[];
    options: DirectoryOptions;
    onlineUserIds: number[];
    onAction: (type: ActionType, userId: number, userName: string) => void;
}

const dateFormatter = new Intl.DateTimeFormat("en-PH", { day: "numeric", month: "short", year: "numeric" });
const dateTimeFormatter = new Intl.DateTimeFormat("en-PH", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" });

function formatDate(value: string | null | undefined): string {
    return value ? dateFormatter.format(new Date(value)) : "Not recorded";
}

function formatDateTime(value: string | null | undefined): string {
    return value ? dateTimeFormatter.format(new Date(value)) : "Not recorded";
}

/**
 * Column filters in this v9 release take a predicate, not a built-in name:
 * the feature bundle registers no filter function names, so the grid cannot
 * resolve "equalsString" and the option type rejects it.
 */
function selectedIncludes(row: Row<DataGridFeatures, DirectoryUser>, columnId: string, filterValue: unknown): boolean {
    const selected = Array.isArray(filterValue) ? (filterValue as string[]) : [];
    if (selected.length === 0) {
        return true;
    }
    return selected.includes(String(row.getValue(columnId) ?? ""));
}

function initials(name: string): string {
    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
}

export function UserDirectoryGrid({ users, options, onlineUserIds, onAction }: DirectoryGridProps) {
    const [search, setSearch] = useState("");
    const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 10 });
    const [sorting, setSorting] = useState<SortingState>([{ id: "name", desc: false }]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<Record<string, boolean>>({ created_at: false, last_login_at: false });
    const [globalFilter, setGlobalFilter] = useState("");

    const columns = useMemo<ColumnDef<DataGridFeatures, DirectoryUser>[]>(
        () => [
            {
                id: "name",
                accessorFn: (user) => user.name,
                header: "Account",
                cell: ({ row }) => {
                    const user = row.original;
                    const online = onlineUserIds.includes(user.id) && !user.suspended_at;

                    return (
                        <div className="flex items-center gap-2.5">
                            <div className="relative shrink-0">
                                <Avatar className="size-8 border">
                                    <AvatarImage src={user.avatar_url || undefined} alt={user.name} />
                                    <AvatarFallback className="text-xs">{initials(user.name)}</AvatarFallback>
                                </Avatar>
                                {online ? (
                                    <span
                                        aria-hidden="true"
                                        className="border-background absolute -right-0.5 -bottom-0.5 size-2 rounded-full border-2 bg-emerald-500"
                                    />
                                ) : null}
                            </div>
                            <div className="flex min-w-0 flex-col">
                                {user.capabilities.view ? (
                                    <Link href={show.url(user.id)} className="truncate font-medium hover:underline focus-visible:outline-2">
                                        {user.name}
                                    </Link>
                                ) : (
                                    <span className="truncate font-medium">{user.name}</span>
                                )}
                                <span className="text-muted-foreground truncate text-xs">{user.email}</span>
                            </div>
                        </div>
                    );
                },
                size: 220,
                meta: { headerTitle: "Account" },
            },
            {
                id: "status",
                accessorFn: (user) => (user.suspended_at ? "suspended" : "active"),
                header: "Status",
                cell: ({ row }) =>
                    row.original.suspended_at ? (
                        <Badge variant="destructive-light" size="sm" radius="full">
                            Suspended
                        </Badge>
                    ) : (
                        <Badge variant="success-light" size="sm" radius="full">
                            Active
                        </Badge>
                    ),
                filterFn: (row, columnId, filterValue) => selectedIncludes(row, columnId, filterValue),
                size: 96,
                meta: { headerTitle: "Status" },
            },
            {
                id: "role",
                accessorFn: (user) => user.role,
                header: "System role",
                cell: ({ row }) => <Badge variant="outline">{row.original.role}</Badge>,
                filterFn: (row, columnId, filterValue) => selectedIncludes(row, columnId, filterValue),
                size: 130,
                meta: { headerTitle: "System role" },
            },
            {
                id: "organization",
                accessorFn: (user) => user.school?.name ?? "Unassigned",
                header: "Organization",
                cell: ({ row }) => (
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate">{row.original.school?.name || "Not assigned"}</span>
                        <span className="text-muted-foreground truncate text-xs">{row.original.department?.name || "No department"}</span>
                    </div>
                ),
                filterFn: (row, columnId, filterValue) => selectedIncludes(row, columnId, filterValue),
                size: 160,
                meta: { headerTitle: "Organization" },
            },
            {
                id: "email",
                accessorFn: (user) => (user.email_verified_at ? "verified" : "unverified"),
                header: "Email",
                cell: ({ row }) =>
                    row.original.email_verified_at ? (
                        <Badge variant="info-light" size="sm" radius="full">
                            Verified
                        </Badge>
                    ) : (
                        <Badge variant="warning-light" size="sm" radius="full">
                            Unverified
                        </Badge>
                    ),
                filterFn: (row, columnId, filterValue) => selectedIncludes(row, columnId, filterValue),
                size: 100,
                meta: { headerTitle: "Email" },
            },
            {
                id: "mfa",
                accessorFn: (user) => (user.mfa_configured && user.security_two_factor_enabled ? "configured" : "attention"),
                header: "MFA",
                cell: ({ row }) =>
                    row.original.mfa_configured && row.original.security_two_factor_enabled ? (
                        <Badge variant="success-light" size="sm" radius="full">
                            <ShieldCheck aria-hidden="true" />
                            Configured
                        </Badge>
                    ) : (
                        <Badge variant="warning-light" size="sm" radius="full">
                            Review
                        </Badge>
                    ),
                filterFn: (row, columnId, filterValue) => selectedIncludes(row, columnId, filterValue),
                size: 110,
                meta: { headerTitle: "MFA" },
            },
            {
                id: "last_login_at",
                accessorFn: (user) => user.last_login_at,
                header: "Last sign-in",
                cell: ({ row }) => <span className="text-muted-foreground text-xs">{formatDateTime(row.original.last_login_at)}</span>,
                sortFn: "datetime",
                size: 130,
                meta: { headerTitle: "Last sign-in" },
            },
            {
                id: "last_active_at",
                accessorFn: (user) => user.last_active_at,
                header: "Last active",
                cell: ({ row }) => <span className="text-muted-foreground text-xs">{formatDateTime(row.original.last_active_at)}</span>,
                sortFn: "datetime",
                size: 130,
                meta: { headerTitle: "Last active" },
            },
            {
                id: "created_at",
                accessorFn: (user) => user.created_at,
                header: "Joined",
                cell: ({ row }) => <span className="text-muted-foreground text-xs">{formatDate(row.original.created_at)}</span>,
                sortFn: "datetime",
                size: 100,
                meta: { headerTitle: "Joined" },
            },
            {
                id: "actions",
                header: "",
                cell: ({ row }) => {
                    const user = row.original;

                    return (
                        <div className="flex items-center justify-end gap-1">
                            {user.capabilities.view ? (
                                <Button render={<Link href={show.url(user.id)} />} nativeButton={false} size="xs" variant="ghost">
                                    View
                                </Button>
                            ) : null}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon-xs" aria-label={`Actions for ${user.name}`}>
                                        <MoreHorizontal aria-hidden="true" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                    <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">Manage {user.name}</DropdownMenuLabel>
                                    {user.capabilities.edit ? (
                                        <DropdownMenuItem asChild>
                                            <Link href={edit.url(user.id)} className="flex items-center">
                                                <FileEdit aria-hidden="true" className="text-muted-foreground mr-2 size-4" />
                                                Edit details
                                            </Link>
                                        </DropdownMenuItem>
                                    ) : null}
                                    <DropdownMenuItem
                                        disabled={!user.capabilities.update || !!user.suspended_at}
                                        onClick={() => onAction("impersonate", user.id, user.name)}
                                    >
                                        <UserCog aria-hidden="true" className="mr-2 size-4 text-amber-600" />
                                        Impersonate user
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        disabled={!user.capabilities.manage_access}
                                        onClick={() => onAction(user.suspended_at ? "reactivate" : "suspend", user.id, user.name)}
                                    >
                                        <CircleSlash aria-hidden="true" className="mr-2 size-4 text-rose-600" />
                                        {user.suspended_at ? "Reactivate account" : "Suspend account"}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        disabled={!user.capabilities.manage_access}
                                        onClick={() => onAction("revoke", user.id, user.name)}
                                    >
                                        <KeyRound aria-hidden="true" className="text-muted-foreground mr-2 size-4" />
                                        Revoke sessions and tokens
                                    </DropdownMenuItem>
                                    {!user.email_verified_at ? (
                                        <DropdownMenuItem disabled={!user.capabilities.update} onClick={() => onAction("verify", user.id, user.name)}>
                                            <ShieldCheck aria-hidden="true" className="mr-2 size-4 text-emerald-600" />
                                            Mark email verified
                                        </DropdownMenuItem>
                                    ) : null}
                                    <DropdownMenuItem
                                        disabled={!user.capabilities.update}
                                        onClick={() => onAction("reset_password", user.id, user.name)}
                                    >
                                        <KeyRound aria-hidden="true" className="text-muted-foreground mr-2 size-4 text-blue-600" />
                                        Send password reset
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        disabled={!user.capabilities.delete}
                                        className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                        onClick={() => onAction("delete", user.id, user.name)}
                                    >
                                        Delete user
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    );
                },
                enableSorting: false,
                enableHiding: false,
                size: 110,
                meta: { headerTitle: "" },
            },
        ],
        [onlineUserIds, onAction],
    );

    const table = useTable({
        features: dataGridFeatures,
        columns,
        data: users,
        getRowId: (user) => String(user.id),
        pageCount: Math.max(1, Math.ceil(users.length / pagination.pageSize)),
        state: { pagination, sorting, columnFilters, globalFilter, columnVisibility },
        onColumnVisibilityChange: (updater) => setColumnVisibility((current) => (typeof updater === "function" ? updater(current) : updater)),
        onPaginationChange: setPagination,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: (updater) => setGlobalFilter((current) => (typeof updater === "function" ? updater(current) : updater)),
    });

    const filterCount = columnFilters.length + (search ? 1 : 0);

    return (
        <DataGrid
            table={table}
            recordCount={users.length}
            emptyMessage="No accounts match these filters"
            tableLayout={{ dense: true, headerBackground: true, headerSticky: true, rowBorder: true }}
        >
            <div className="flex min-w-0 flex-col gap-3">
                <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <div className="relative min-w-56 flex-1 sm:max-w-xs">
                            <Search
                                aria-hidden="true"
                                className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                            />
                            <Input
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                    setGlobalFilter(event.target.value);
                                    setPagination((current) => ({ ...current, pageIndex: 0 }));
                                }}
                                placeholder="Search name or email"
                                aria-label="Search accounts by name or email"
                                className="h-9 pl-8"
                            />
                        </div>
                        <DataGridColumnFilter
                            column={table.getColumn("role")}
                            title="System role"
                            options={options.roles.map((role) => ({ label: role, value: role }))}
                        />
                        <DataGridColumnFilter
                            column={table.getColumn("status")}
                            title="Status"
                            options={[
                                { label: "Active", value: "active" },
                                { label: "Suspended", value: "suspended" },
                            ]}
                        />
                        <DataGridColumnFilter
                            column={table.getColumn("email")}
                            title="Email"
                            options={[
                                { label: "Verified", value: "verified" },
                                { label: "Unverified", value: "unverified" },
                            ]}
                        />
                        <DataGridColumnFilter
                            column={table.getColumn("mfa")}
                            title="MFA"
                            options={[
                                { label: "Configured", value: "configured" },
                                { label: "Needs review", value: "attention" },
                            ]}
                        />
                        {filterCount > 0 ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch("");
                                    setGlobalFilter("");
                                    setColumnFilters([]);
                                    setPagination((current) => ({ ...current, pageIndex: 0 }));
                                }}
                            >
                                Clear
                                <X className="ml-1.5 size-3.5" />
                            </Button>
                        ) : null}
                    </div>
                    <div className="flex items-center gap-2">
                        <DataGridColumnVisibility
                            table={table}
                            trigger={
                                <Button variant="outline" size="sm">
                                    <Settings2 className="mr-1.5 size-3.5" />
                                    Columns
                                </Button>
                            }
                        />
                    </div>
                </div>
                <DataGridContainer className="rounded-xl border">
                    <DataGridScrollArea>
                        <DataGridTable />
                    </DataGridScrollArea>
                </DataGridContainer>
                <DataGridPagination />
            </div>
        </DataGrid>
    );
}
