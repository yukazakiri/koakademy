"use client";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";
import type { ColumnDef } from "@tanstack/react-table";
import { CalendarDays, FileEdit, KeyRound, MoreHorizontal, ShieldCheck, Trash2, UserCog } from "lucide-react";
import { DataTableColumnHeader } from "./data-table-column-header";

declare const route: (name: string, ...parameters: unknown[]) => string;

const dateFormatter = new Intl.DateTimeFormat("en-PH", {
    day: "numeric",
    month: "short",
    year: "numeric",
});

const dateTimeFormatter = new Intl.DateTimeFormat("en-PH", {
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    month: "short",
    year: "numeric",
});

function isEnabled(value: unknown): boolean {
    return value === true || value === 1 || value === "1" || value === "true";
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return "Not available";
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? "Not available" : dateFormatter.format(date);
}

function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return "Never";
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? "Not available" : dateTimeFormatter.format(date);
}

export interface ExtendedUser {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    role: string;
    school?: { id: number; name: string };
    department?: { id: number; name: string };
    roles?: { id: number; name: string }[];
    email_verified_at: string | null;
    security_two_factor_enabled?: boolean | null;
    last_login_at?: string | null;
    created_at: string | null;
    deleted_at: string | null;
}

type ActionType = "delete" | "impersonate" | "verify" | "reset_password";

interface ColumnsProps {
    onAction: (type: ActionType, userId: number, userName: string) => void;
    onlineUserIds: number[];
}

export const createColumns = ({ onAction, onlineUserIds }: ColumnsProps): ColumnDef<ExtendedUser>[] => [
    {
        id: "select",
        header: ({ table }) => (
            <Checkbox
                checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                aria-label="Select all users on this page"
            />
        ),
        cell: ({ row }) => (
            <Checkbox
                checked={row.getIsSelected()}
                onCheckedChange={(value) => row.toggleSelected(!!value)}
                aria-label={`Select ${row.original.name}`}
            />
        ),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: "id",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Account ID" />,
        cell: ({ row }) => <span className="text-muted-foreground font-mono text-xs">#{row.original.id}</span>,
        size: 96,
    },
    {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="User" />,
        cell: ({ row }) => {
            const user = row.original;
            const isOnline = onlineUserIds.includes(user.id);

            return (
                <div className="flex items-center gap-3">
                    <div className="relative">
                        <Avatar className="size-9 border">
                            <AvatarImage src={user.avatar_url || undefined} alt={user.name} />
                            <AvatarFallback>{user.name.charAt(0).toUpperCase()}</AvatarFallback>
                        </Avatar>
                        {isOnline ? (
                            <span
                                aria-hidden="true"
                                className="border-background absolute right-0 bottom-0 size-2.5 rounded-full border-2 bg-emerald-500"
                            />
                        ) : null}
                    </div>
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate font-medium">{user.name}</span>
                        <span className="text-muted-foreground max-w-[18rem] truncate text-xs">{user.email}</span>
                        <span className="sr-only">{isOnline ? "Online" : "Offline"}</span>
                    </div>
                </div>
            );
        },
        filterFn: (row, id, value) => {
            const name = row.getValue(id) as string;
            const email = row.original.email;
            const searchValue = value.toLowerCase();

            return name.toLowerCase().includes(searchValue) || email.toLowerCase().includes(searchValue);
        },
    },
    {
        accessorKey: "role",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Role" />,
        cell: ({ row }) => <Badge variant="outline">{row.getValue("role")}</Badge>,
        filterFn: (row, id, value) => value.includes(row.getValue(id)),
    },
    {
        accessorKey: "school",
        header: "Organization",
        cell: ({ row }) => {
            const user = row.original;

            return (
                <div className="flex min-w-36 flex-col text-sm">
                    <span className="truncate">{user.school?.name || "Not assigned"}</span>
                    <span className="text-muted-foreground truncate text-xs">{user.department?.name || "No department"}</span>
                </div>
            );
        },
        enableSorting: false,
    },
    {
        accessorKey: "email_verified_at",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => {
            const isVerified = row.getValue("email_verified_at");

            return isVerified ? (
                <Badge variant="secondary" className="text-emerald-700 dark:text-emerald-400">
                    Verified
                </Badge>
            ) : (
                <Badge variant="secondary">Unverified</Badge>
            );
        },
        filterFn: (row, id, value) => {
            const isVerified = !!row.getValue(id);

            if (value === "verified") {
                return isVerified;
            }

            if (value === "unverified") {
                return !isVerified;
            }

            return true;
        },
    },
    {
        accessorKey: "security_two_factor_enabled",
        header: ({ column }) => <DataTableColumnHeader column={column} title="2FA" />,
        cell: ({ row }) => {
            const protectedAccount = isEnabled(row.original.security_two_factor_enabled);

            return (
                <Badge
                    variant={protectedAccount ? "secondary" : "outline"}
                    className={protectedAccount ? "text-emerald-700 dark:text-emerald-400" : "text-muted-foreground"}
                >
                    <ShieldCheck aria-hidden="true" />
                    {protectedAccount ? "Protected" : "Off"}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            const protectedAccount = isEnabled(row.getValue(id));

            if (value === "enabled") {
                return protectedAccount;
            }

            if (value === "disabled") {
                return !protectedAccount;
            }

            return true;
        },
    },
    {
        accessorKey: "last_login_at",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Last sign-in" />,
        cell: ({ row }) => {
            const user = row.original;
            const isOnline = onlineUserIds.includes(user.id);

            return (
                <div className="flex min-w-32 flex-col gap-0.5 text-sm">
                    <span className={isOnline ? "font-medium text-emerald-700 dark:text-emerald-400" : "text-foreground"}>
                        {isOnline ? "Online now" : formatDateTime(user.last_login_at)}
                    </span>
                    <span className="text-muted-foreground text-xs">{isOnline ? "Active session" : "Account activity"}</span>
                </div>
            );
        },
        sortUndefined: "last",
    },
    {
        accessorKey: "created_at",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Joined" />,
        cell: ({ row }) => (
            <div className="text-muted-foreground flex min-w-24 items-center gap-1.5 text-sm">
                <CalendarDays aria-hidden="true" className="size-3.5" />
                {formatDate(row.original.created_at)}
            </div>
        ),
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const user = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-10" aria-label={`Actions for ${user.name}`}>
                            <MoreHorizontal aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">Manage {user.name}</DropdownMenuLabel>
                        <DropdownMenuItem asChild>
                            <Link href={route("administrators.users.edit", user.id)} className="flex items-center">
                                <FileEdit aria-hidden="true" className="text-muted-foreground mr-2 size-4" />
                                Edit details
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onAction("impersonate", user.id, user.name)}>
                            <UserCog aria-hidden="true" className="mr-2 size-4 text-amber-600" />
                            Impersonate user
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        {!user.email_verified_at ? (
                            <DropdownMenuItem onClick={() => onAction("verify", user.id, user.name)}>
                                <ShieldCheck aria-hidden="true" className="mr-2 size-4 text-emerald-600" />
                                Mark email verified
                            </DropdownMenuItem>
                        ) : null}
                        <DropdownMenuItem onClick={() => onAction("reset_password", user.id, user.name)}>
                            <KeyRound aria-hidden="true" className="mr-2 size-4 text-blue-600" />
                            Send password reset
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                            onClick={() => onAction("delete", user.id, user.name)}
                        >
                            <Trash2 aria-hidden="true" className="mr-2 size-4" />
                            Delete user
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
