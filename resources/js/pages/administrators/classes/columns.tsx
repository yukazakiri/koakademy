import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { show } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import type { ColumnDef } from "@tanstack/react-table";
import { ClassEnrollment, ClassFaculty, classProgramLabel, semesterLabel } from "./components/class-list-details";
import { ClassRowActions, type ClassActionHandlers } from "./components/class-row-actions";
import { DataTableColumnHeader } from "./data-table-column-header";
import { deliveryModeLabel, type DeliveryMode } from "./lib/class-defaults";

export type ClassRow = {
    id: number;
    record_title: string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string | number;
    classification: "college" | "shs" | string;
    delivery_mode?: DeliveryMode;
    delivery_summary?: "face_to_face" | "online" | "mixed";
    academic_year: number | null;
    grade_level: string | null;
    course_abbreviations: string[] | null;
    shs_track: string | null;
    shs_strand: string | null;
    faculty: string;
    students_count: number;
    maximum_slots: number;
    deleted_at: string | null;
    filament: {
        view_url: string;
        edit_url: string;
    };
};

export type ClassCapabilities = {
    create: boolean;
    update: boolean;
    delete: boolean;
    restore: boolean;
    force_delete: boolean;
};

/** Column IDs are the sort keys accepted by the class list endpoint. */
export const getColumns = (actions: ClassActionHandlers): ColumnDef<ClassRow>[] => [
    {
        id: "select",
        enableSorting: false,
        enableHiding: false,
        header: ({ table }) => (
            <label className="flex size-8 cursor-pointer items-center justify-center">
                <Checkbox
                    checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(Boolean(value))}
                    aria-label="Select all classes on this page"
                />
            </label>
        ),
        cell: ({ row }) => (
            <label className="flex size-8 cursor-pointer items-center justify-center">
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(Boolean(value))}
                    aria-label={`Select ${row.original.record_title}`}
                />
            </label>
        ),
    },
    {
        accessorKey: "record_title",
        enableHiding: false,
        header: ({ column }) => <DataTableColumnHeader column={column} title="Class section" />,
        cell: ({ row: { original: row } }) => (
            <div className="max-w-96 min-w-60 space-y-1 whitespace-normal">
                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={show.url(row.id)}
                        className="text-foreground hover:text-primary focus-visible:outline-ring rounded-sm text-sm font-semibold focus-visible:outline-2"
                    >
                        {row.record_title}
                    </Link>
                    {row.deleted_at ? <Badge variant="outline">Archived</Badge> : null}
                    <Badge variant="secondary">{deliveryModeLabel(row.delivery_summary ?? row.delivery_mode)}</Badge>
                </div>
                <p className="text-muted-foreground text-sm">{row.subject_title}</p>
                <p className="text-muted-foreground text-sm">
                    {row.subject_code} · Section {row.section}
                </p>
            </div>
        ),
    },
    {
        id: "program",
        enableSorting: false,
        header: "Program & level",
        cell: ({ row: { original: row } }) => (
            <div className="max-w-60 min-w-36 space-y-1 text-sm whitespace-normal">
                <span>{row.classification === "shs" ? "Senior high" : "College"}</span>
                <p className="text-muted-foreground">{classProgramLabel(row)}</p>
            </div>
        ),
    },
    {
        accessorKey: "faculty",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Faculty" />,
        cell: ({ row }) => (
            <div className="max-w-52 min-w-36 whitespace-normal">
                <ClassFaculty faculty={row.original.faculty} />
            </div>
        ),
    },
    {
        id: "period",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Term" />,
        accessorFn: (row) => `${row.school_year} ${row.semester}`,
        cell: ({ row }) => (
            <div className="space-y-1 text-sm">
                <p>{row.original.school_year}</p>
                <p className="text-muted-foreground">{semesterLabel(row.original.semester)}</p>
            </div>
        ),
    },
    {
        accessorKey: "students_count",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Enrollment" />,
        cell: ({ row }) => <ClassEnrollment classRow={row.original} />,
    },
    {
        id: "actions",
        enableSorting: false,
        enableHiding: false,
        header: () => <span className="sr-only">Class actions</span>,
        cell: ({ row }) => <ClassRowActions classRow={row.original} {...actions} />,
    },
];
