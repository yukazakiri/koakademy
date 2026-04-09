"use client";

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
import { ColumnDef } from "@tanstack/react-table";
import { ArrowUpDown, FileEdit, KeyRound, MoreHorizontal, ShieldCheck, Trash2, UserCog } from "lucide-react";

declare const route: any;

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
    created_at: string;
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
                aria-label="Select all"
            />
        ),
        cell: ({ row }) => (
            <Checkbox checked={row.getIsSelected()} onCheckedChange={(value) => row.toggleSelected(!!value)} aria-label="Select row" />
        ),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: "name",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    User
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const user = row.original;
            const isOnline = onlineUserIds.includes(user.id);
            return (
                <div className="flex items-center gap-3">
                    <div className="relative">
                        {user.avatar_url ? (
                            <img src={user.avatar_url} alt={user.name} className="h-9 w-9 rounded-full object-cover" />
                        ) : (
                            <div className="bg-muted flex h-9 w-9 items-center justify-center rounded-full">{user.name.charAt(0)}</div>
                        )}
                        {isOnline && (
                            <span className="border-background absolute right-0 bottom-0 h-2.5 w-2.5 rounded-full border-2 bg-emerald-500" />
                        )}
                    </div>
                    <div className="flex flex-col">
                        <span className="font-medium">{user.name}</span>
                        <span className="text-muted-foreground text-xs">{user.email}</span>
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
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    Role
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return <Badge variant="outline">{row.getValue("role")}</Badge>;
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: "school",
        header: "Organization",
        cell: ({ row }) => {
            const user = row.original;
            return (
                <div className="flex flex-col text-sm">
                    <span>{user.school?.name || "—"}</span>
                    <span className="text-muted-foreground text-xs">{user.department?.name || "—"}</span>
                </div>
            );
        },
        enableSorting: false,
    },
    {
        accessorKey: "email_verified_at",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    Status
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const isVerified = row.getValue("email_verified_at");
            return isVerified ? (
                <Badge variant="default" className="bg-emerald-500 hover:bg-emerald-600">
                    Verified
                </Badge>
            ) : (
                <Badge variant="secondary">Unverified</Badge>
            );
        },
        filterFn: (row, id, value) => {
            const isVerified = !!row.getValue(id);
            if (value === "verified") return isVerified;
            if (value === "unverified") return !isVerified;
            return true;
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const user = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuItem asChild>
                            <Link href={route("administrators.users.edit", user.id)}>
                                <FileEdit className="text-muted-foreground mr-2 h-4 w-4" />
                                Edit Details
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => onAction("impersonate", user.id, user.name)}>
                            <UserCog className="mr-2 h-4 w-4 text-amber-500" />
                            Impersonate User
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        {!user.email_verified_at && (
                            <DropdownMenuItem onClick={() => onAction("verify", user.id, user.name)}>
                                <ShieldCheck className="mr-2 h-4 w-4 text-emerald-500" />
                                Mark Email Verified
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem onClick={() => onAction("reset_password", user.id, user.name)}>
                            <KeyRound className="mr-2 h-4 w-4 text-blue-500" />
                            Send Password Reset
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="text-red-600 focus:bg-red-50 focus:text-red-600"
                            onClick={() => onAction("delete", user.id, user.name)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete User
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
