import { Avatar, AvatarFallback } from "@/components/ui/avatar";
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
import { ArrowUpDown, Eye, FileText, MoreHorizontal } from "lucide-react";

declare let route: (name: string, params?: number | Record<string, unknown>) => string;

export type DocumentStudent = {
    id: number;
    student_id: string | null;
    first_name: string;
    last_name: string;
    middle_name: string | null;
    document_location: Record<string, string> | null;
};

const REQUIRED_DOCUMENTS = [
    { key: "birth_certificate", label: "Birth Certificate" },
    { key: "form_138", label: "Form 138" },
    { key: "form_137", label: "Form 137" },
    { key: "good_moral_cert", label: "Good Moral" },
    { key: "transfer_credentials", label: "Transfer Credentials" },
    { key: "transcript_records", label: "Transcript Records" },
    { key: "picture_1x1", label: "1x1 Picture" },
];

const getInitials = (firstName: string, lastName: string): string => {
    return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
};

const getDocumentStatus = (student: DocumentStudent) => {
    const docs = student.document_location;
    let submitted = 0;

    if (docs) {
        REQUIRED_DOCUMENTS.forEach(({ key }) => {
            if (docs[key]) submitted++;
        });
    }

    const isComplete = submitted === REQUIRED_DOCUMENTS.length;
    return { submitted, total: REQUIRED_DOCUMENTS.length, isComplete };
};

export const columns: ColumnDef<DocumentStudent>[] = [
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
        accessorKey: "student_id",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    ID
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => <div className="font-mono text-xs">{row.getValue("student_id") ?? "—"}</div>,
    },
    {
        accessorKey: "name",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    Student Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const student = row.original;
            const name = `${student.last_name}, ${student.first_name}${student.middle_name ? ` ${student.middle_name}` : ""}`;
            return (
                <div className="flex items-center gap-3">
                    <Avatar className="h-8 w-8 border">
                        <AvatarFallback className="bg-primary/10 text-primary text-xs font-medium">
                            {getInitials(student.first_name, student.last_name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="text-sm font-medium">{name}</span>
                </div>
            );
        },
    },
    {
        id: "documents_status",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    Documents Status
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const student = row.original;
            const { submitted, total, isComplete } = getDocumentStatus(student);
            return (
                <Badge variant={isComplete ? "default" : "secondary"} className={isComplete ? "bg-green-500 hover:bg-green-600" : ""}>
                    {submitted} / {total} Required
                </Badge>
            );
        },
        sortingFn: (rowA, rowB) => {
            const statusA = getDocumentStatus(rowA.original);
            const statusB = getDocumentStatus(rowB.original);
            const percentageA = statusA.submitted / statusA.total;
            const percentageB = statusB.submitted / statusB.total;
            return percentageA - percentageB;
        },
    },
    {
        id: "document_details",
        header: "Document Details",
        cell: ({ row }) => {
            const student = row.original;
            const docs = student.document_location;
            const { submitted } = getDocumentStatus(student);

            if (!docs || submitted === 0) {
                return <span className="text-muted-foreground text-xs">No documents submitted</span>;
            }

            const submittedDocs = REQUIRED_DOCUMENTS.filter(({ key }) => docs[key]);

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" className="h-8 w-full justify-start text-xs">
                            <FileText className="mr-2 h-3 w-3" />
                            View {submittedDocs.length} Document{submittedDocs.length !== 1 ? "s" : ""}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-64">
                        <DropdownMenuLabel>Submitted Documents</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {submittedDocs.map(({ key, label }) => (
                            <DropdownMenuItem key={key} className="text-xs">
                                <span className="mr-2 text-green-500">✓</span>
                                {label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
        enableSorting: false,
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const student = row.original;

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
                        <DropdownMenuItem onClick={() => navigator.clipboard.writeText(String(student.student_id))}>Copy Student ID</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                href={route("administrators.students.documents.index", student.id) as string}
                                className="flex w-full cursor-pointer items-center"
                            >
                                <Eye className="mr-2 h-4 w-4" /> Manage Documents
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link
                                href={route("administrators.students.show", student.id) as string}
                                className="flex w-full cursor-pointer items-center"
                            >
                                <FileText className="mr-2 h-4 w-4" /> View Student
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
