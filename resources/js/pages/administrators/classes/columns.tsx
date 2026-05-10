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

import { DataTableColumnHeader } from "./data-table-column-header";

export type ClassRow = {
    id: number;
    record_title: string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string | number;
    classification: "college" | "shs" | string;
    course_abbreviations: string[] | null;
    shs_track: string | null;
    shs_strand: string | null;
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
            const classColor = data.classification === "shs" ? "border-l-amber-500" : "border-l-blue-500";

            return (
                <div className={`min-w-[280px] space-y-1.5 border-l-2 py-1 pl-3 ${classColor}`}>
                    <Link
                        href={route("administrators.classes.show", { class: data.id })}
                        className="text-foreground hover:text-primary line-clamp-1 font-medium transition-colors"
                        title={data.record_title}
                    >
                        {data.record_title}
                    </Link>
                    <div className="text-muted-foreground line-clamp-1 text-xs" title={data.subject_title}>
                        {data.subject_title}
                    </div>
                    <div className="flex flex-wrap items-center gap-1">
                        <Badge
                            variant="outline"
                            className={`h-5 px-1.5 text-[10px] ${data.classification === "shs" ? "border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300" : "border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-300"}`}
                        >
                            {data.classification === "shs" ? "SHS" : "College"}
                        </Badge>
                        <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                            {data.subject_code}
                        </Badge>
                        <Badge variant="outline" className="h-5 px-1.5 text-[10px]">
                            Sec {data.section}
                        </Badge>
                        {data.classification === "shs" && data.shs_track ? (
                            <Badge variant="outline" className="h-5 px-1.5 text-[10px]">
                                {data.shs_strand ? `${data.shs_track} – ${data.shs_strand}` : data.shs_track}
                            </Badge>
                        ) : null}
                        {data.course_abbreviations?.map((code) => (
                            <Badge key={code} variant="outline" className="h-5 px-1.5 text-[10px]">
                                {code}
                            </Badge>
                        ))}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "faculty",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Faculty" />,
        cell: ({ row }) => (
            <span className="text-sm" title={String(row.getValue("faculty") || "Not assigned")}>
                {row.getValue("faculty") || "Not assigned"}
            </span>
        ),
    },
    {
        id: "period",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Period" />,
        accessorFn: (row) => `${row.school_year} ${row.semester}`,
        cell: ({ row }) => (
            <div className="text-sm">
                <span className="font-medium">{row.original.school_year}</span>
                <span className="text-muted-foreground"> · Sem {row.original.semester}</span>
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
            const percentage = data.maximum_slots > 0 ? Math.round((data.students_count / data.maximum_slots) * 100) : 0;
            const barColor = atCapacity ? "bg-destructive" : percentage >= 75 ? "bg-amber-500" : "bg-primary";
            const capacityDot = atCapacity ? "bg-destructive" : percentage >= 75 ? "bg-amber-500" : "bg-emerald-500";
            const capacityLabel = atCapacity ? "Full" : "Open";
            const slotsLeft = Math.max(data.maximum_slots - data.students_count, 0);

            return (
                <div className="min-w-[140px] space-y-1.5">
                    <div className="flex items-center justify-between text-xs">
                        <div className="flex items-center gap-1.5">
                            <span className={`h-2 w-2 shrink-0 rounded-full ${capacityDot}`} />
                            <span className="font-medium">{capacityLabel}</span>
                            {!atCapacity && <span className="text-muted-foreground">· {slotsLeft}</span>}
                        </div>
                        <span className={`tabular-nums ${atCapacity ? "text-destructive font-medium" : "text-muted-foreground"}`}>
                            {data.students_count}/{data.maximum_slots} ({percentage}%)
                        </span>
                    </div>
                    <div className="bg-secondary h-2 w-full overflow-hidden rounded-full">
                        <div className={`h-full rounded-full transition-all ${barColor}`} style={{ width: `${Math.min(percentage, 100)}%` }} />
                    </div>
                </div>
            );
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const data = row.original;

            return (
                <div className="flex items-center justify-end gap-1">
                    <Button type="button" variant="outline" size="sm" className="h-8 px-2" onClick={() => onEdit(data.id)} title="Quick edit">
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
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
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
