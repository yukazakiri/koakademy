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
import { Copy, Eye, MoreHorizontal, Pencil, Settings, Trash2 } from "lucide-react";
import { route } from "ziggy-js";

export type ClassRow = {
    id: number;
    record_title: string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string | number;
    classification: "college" | "shs" | string;
    display_info: string | null;
    faculty: string;
    students_count: number;
    maximum_slots: number;
    filament: {
        view_url: string;
        edit_url: string;
    };
};

interface GetColumnsProps {
    onManage: (id: number) => void;
    onCopy: (id: number) => void;
    onEdit: (id: number) => void;
    onDelete: (row: ClassRow) => void;
}

import { DataTableColumnHeader } from "./data-table-column-header";

export const getColumns = ({ onManage, onCopy, onEdit, onDelete }: GetColumnsProps): ColumnDef<ClassRow>[] => [
    {
        id: "select",
        header: ({ table }) => (
            <Checkbox
                checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                aria-label="Select all"
                className="translate-y-[2px]"
            />
        ),
        cell: ({ row }) => (
            <Checkbox
                checked={row.getIsSelected()}
                onCheckedChange={(value) => row.toggleSelected(!!value)}
                aria-label="Select row"
                className="translate-y-[2px]"
            />
        ),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: "record_title",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Class" />,
        cell: ({ row }) => {
            const data = row.original;
            return (
                <div className="flex max-w-[300px] flex-col gap-1">
                    <a
                        href={route("administrators.classes.show", { class: data.id })}
                        className="text-foreground hover:text-primary truncate font-medium transition-colors"
                        title={data.record_title}
                    >
                        {data.record_title}
                    </a>
                    <div className="text-muted-foreground truncate text-xs" title={data.subject_title}>
                        {data.subject_title}
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="h-5 px-1.5 py-0 text-[10px] capitalize">
                            {data.classification}
                        </Badge>
                        <Badge variant="outline" className="h-5 px-1.5 py-0 text-[10px]">
                            Section {data.section}
                        </Badge>
                        {data.display_info ? (
                            <Badge variant="secondary" className="h-5 px-1.5 py-0 text-[10px]">
                                {data.display_info}
                            </Badge>
                        ) : null}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "faculty",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Faculty" />,
        cell: ({ row }) => <div className="text-sm">{row.getValue("faculty")}</div>,
    },
    {
        id: "period",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Period" />,
        accessorFn: (row) => `${row.school_year} ${row.semester}`,
        cell: ({ row }) => (
            <div className="text-muted-foreground text-sm">
                {row.original.school_year} • Sem {row.original.semester}
            </div>
        ),
    },
    {
        id: "enrollment",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Enrollment" />,
        accessorFn: (row) => row.students_count / (row.maximum_slots || 1),
        cell: ({ row }) => {
            const data = row.original;
            const atCapacity = data.maximum_slots > 0 && data.students_count >= data.maximum_slots;

            return (
                <Badge variant={atCapacity ? "destructive" : "secondary"}>
                    {data.students_count} / {data.maximum_slots || "—"}
                </Badge>
            );
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const data = row.original;

            return (
                <div className="flex items-center justify-end gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 px-2"
                        onClick={() => onEdit(data.id)}
                        title="Quick edit"
                    >
                        <Pencil className="mr-1.5 h-3.5 w-3.5" />
                        Edit
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                                <span className="sr-only">More actions</span>
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel>Class actions</DropdownMenuLabel>
                            <DropdownMenuItem asChild>
                                <Link
                                    href={route("administrators.classes.show", { class: data.id })}
                                    className="flex w-full cursor-pointer items-center"
                                >
                                    <Eye className="mr-2 h-4 w-4" /> Open class page
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onEdit(data.id)}>
                                <Pencil className="mr-2 h-4 w-4" /> Edit class
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onManage(data.id)}>
                                <Settings className="mr-2 h-4 w-4" /> Manage details
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onCopy(data.id)}>
                                <Copy className="mr-2 h-4 w-4" /> Duplicate class
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={() => onDelete(data)} className="text-destructive focus:text-destructive">
                                <Trash2 className="mr-2 h-4 w-4" /> Delete class
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            );
        },
    },
];
