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
import { ColumnDef } from "@tanstack/react-table";
import { AlertCircle, CheckCircle2, Clock, Eye, HelpCircle, MoreHorizontal, Trash } from "lucide-react";
import { DataTableColumnHeader } from "./data-table-column-header";

// Declare route globally
declare const route: any;

export type HelpTicket = {
    id: number;
    user_id: number;
    type: string;
    subject: string;
    message: string;
    status: "open" | "in_progress" | "resolved" | "closed";
    priority: "low" | "medium" | "high" | "critical";
    created_at: string;
    updated_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
        avatar_url: string | null;
    };
};

interface ColumnProps {
    onAction: (type: "delete" | "view", ticket: HelpTicket) => void;
}

export const createColumns = ({ onAction }: ColumnProps): ColumnDef<HelpTicket>[] => [
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
        accessorKey: "id",
        header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
        cell: ({ row }) => <div className="w-[40px]">#{row.getValue("id")}</div>,
        enableSorting: true,
        enableHiding: false,
    },
    {
        accessorKey: "subject",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Subject" />,
        cell: ({ row }) => {
            return (
                <div className="flex flex-col space-y-1">
                    <span className="max-w-[300px] truncate font-medium">{row.getValue("subject")}</span>
                    <span className="text-muted-foreground max-w-[300px] truncate text-xs">{row.original.message}</span>
                </div>
            );
        },
    },
    {
        accessorKey: "type",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
        cell: ({ row }) => {
            const type = row.getValue("type") as string;
            return (
                <Badge variant="outline" className="capitalize">
                    {type.replace("_", " ")}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: "priority",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Priority" />,
        cell: ({ row }) => {
            const priority = row.getValue("priority") as string;

            const icons = {
                low: <CheckCircle2 className="mr-2 h-4 w-4 text-green-500" />,
                medium: <Clock className="mr-2 h-4 w-4 text-orange-500" />,
                high: <AlertCircle className="mr-2 h-4 w-4 text-red-500" />,
                critical: <AlertCircle className="mr-2 h-4 w-4 font-bold text-red-700" />,
            };

            return (
                <div className="flex items-center capitalize">
                    {icons[priority as keyof typeof icons] || <HelpCircle className="mr-2 h-4 w-4 text-slate-500" />}
                    <span>{priority}</span>
                </div>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: "status",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => {
            const status = row.getValue("status") as string;

            const variants: Record<string, string> = {
                open: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800",
                in_progress: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800",
                resolved: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-200 dark:border-green-800",
                closed: "bg-gray-100 text-gray-800 dark:bg-gray-800/30 dark:text-gray-400 border-gray-200 dark:border-gray-700",
            };

            return (
                <Badge className={`${variants[status]} border capitalize`} variant="outline">
                    {status.replace("_", " ")}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: "user.name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="User" />,
        cell: ({ row }) => {
            const user = row.original.user;
            if (!user) return <span className="text-muted-foreground italic">Unknown User</span>;

            return (
                <div className="flex flex-col">
                    <span className="font-medium">{user.name}</span>
                    <span className="text-muted-foreground text-xs">{user.email}</span>
                </div>
            );
        },
    },
    {
        accessorKey: "created_at",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Submitted" />,
        cell: ({ row }) => {
            const date = new Date(row.getValue("created_at"));
            return <div className="text-muted-foreground">{date.toLocaleDateString()}</div>;
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const ticket = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => onAction("view", ticket)}>
                            <Eye className="mr-2 h-4 w-4" />
                            View Details
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={() => onAction("delete", ticket)} className="text-red-600 focus:text-red-600">
                            <Trash className="mr-2 h-4 w-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
