import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";
import { ColumnDef } from "@tanstack/react-table";
import { ArrowUpDown, MoreHorizontal, Pencil, Trash2, Zap } from "lucide-react";

declare let route: any; // eslint-disable-line @typescript-eslint/no-explicit-any

export type Book = {
    id: number;
    title: string;
    isbn: string | null;
    call_number: string | null;
    accession_number: string | null;
    author: { id?: number | null; name?: string | null } | null;
    category: { id?: number | null; name?: string | null; color?: string | null } | null;
    status: string;
    available_copies: number;
    total_copies: number;
    publication_year: number | null;
    location: string | null;
    cover_image_url: string | null;
    updated_at: string | null;
    created_at: string | null;
};

const statusStyles: Record<string, string> = {
    available: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-500/15",
    borrowed: "bg-amber-500/10 text-amber-700 dark:text-amber-300 hover:bg-amber-500/15",
    maintenance: "bg-rose-500/10 text-rose-700 dark:text-rose-300 hover:bg-rose-500/15",
};

export const columns: ColumnDef<Book>[] = [
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
        accessorKey: "title",
        header: ({ column }) => (
            <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                Title
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => {
            const book = row.original;
            return (
                <div className="flex items-center gap-3">
                    {book.cover_image_url ? (
                        <img
                            src={book.cover_image_url}
                            alt=""
                            className="h-12 w-9 rounded-md border object-cover"
                            loading="lazy"
                        />
                    ) : (
                        <div className="bg-muted text-muted-foreground flex h-12 w-9 items-center justify-center rounded-md border text-[10px] uppercase">
                            Cover
                        </div>
                    )}
                    <div className="space-y-1">
                        <p className="text-foreground font-medium">{book.title}</p>
                        <p className="text-muted-foreground text-xs">{book.isbn ? `ISBN ${book.isbn}` : "No ISBN"}</p>
                        {(book.call_number || book.accession_number) && (
                            <p className="text-muted-foreground text-xs">
                                {[book.call_number ? `Call ${book.call_number}` : null, book.accession_number ? `Acc. ${book.accession_number}` : null]
                                    .filter(Boolean)
                                    .join(" · ")}
                            </p>
                        )}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "author",
        header: "Author",
        cell: ({ row }) => <span className="text-muted-foreground text-sm">{row.original.author?.name ?? "Unknown"}</span>,
    },
    {
        accessorKey: "category",
        header: "Category",
        cell: ({ row }) => {
            const category = row.original.category;
            return (
                <div className="flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full" style={{ backgroundColor: category?.color ?? "#64748b" }} />
                    <span className="text-muted-foreground text-sm">{category?.name ?? "Uncategorized"}</span>
                </div>
            );
        },
    },
    {
        accessorKey: "publication_year",
        header: ({ column }) => (
            <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                Year
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => <span className="text-muted-foreground text-sm">{row.original.publication_year ?? "—"}</span>,
    },
    {
        accessorKey: "copies",
        header: "Copies",
        cell: ({ row }) => (
            <span className="text-muted-foreground text-sm">
                {row.original.available_copies}/{row.original.total_copies}
            </span>
        ),
    },
    {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => (
            <Badge className={statusStyles[row.original.status] ?? "bg-muted text-muted-foreground"}>{row.original.status}</Badge>
        ),
    },
    {
        id: "actions",
        header: "",
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const book = row.original;
            return (
                <div className="flex justify-end">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                <MoreHorizontal className="h-4 w-4" />
                                <span className="sr-only">Open menu</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <Link href={route("administrators.library.books.edit", book.id)} className="flex items-center gap-2">
                                    <Pencil className="h-4 w-4" />
                                    Edit
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => window.dispatchEvent(new CustomEvent("books:soft-delete", { detail: book }))}
                                className="text-destructive focus:text-destructive flex items-center gap-2"
                            >
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => window.dispatchEvent(new CustomEvent("books:force-delete", { detail: book }))}
                                className="text-destructive focus:text-destructive flex items-center gap-2"
                            >
                                <Zap className="h-4 w-4" />
                                Force Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            );
        },
    },
];
