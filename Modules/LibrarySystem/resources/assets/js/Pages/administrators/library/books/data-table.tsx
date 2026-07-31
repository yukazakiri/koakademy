import {
    ColumnDef,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import * as React from "react";

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { router } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Loader2, Settings2, Trash2, Zap } from "lucide-react";
import { toast } from "sonner";
import type { Book } from "./columns";

declare let route: any; // eslint-disable-line @typescript-eslint/no-explicit-any

interface PaginationInfo {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    from: number;
    to: number;
}

interface DataTableProps<TData extends Book, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination?: PaginationInfo;
    filters?: Record<string, string | number | null | undefined>;
    routeName?: string;
}

export function DataTable<TData extends Book, TValue>({
    columns,
    data,
    pagination,
    filters = {},
    routeName = "administrators.library.books.index",
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [forceDeleteDialogOpen, setForceDeleteDialogOpen] = React.useState(false);
    const [forceDeleteConfirmText, setForceDeleteConfirmText] = React.useState("");
    const [individualTarget, setIndividualTarget] = React.useState<Book | null>(null);
    const [individualDeleteDialogOpen, setIndividualDeleteDialogOpen] = React.useState(false);
    const [individualForceDeleteDialogOpen, setIndividualForceDeleteDialogOpen] = React.useState(false);
    const [individualForceDeleteConfirmText, setIndividualForceDeleteConfirmText] = React.useState("");
    const [isSubmitting, setIsSubmitting] = React.useState(false);

    React.useEffect(() => {
        if (!forceDeleteDialogOpen) {
            setForceDeleteConfirmText("");
        }
    }, [forceDeleteDialogOpen]);

    React.useEffect(() => {
        if (!individualForceDeleteDialogOpen) {
            setIndividualForceDeleteConfirmText("");
        }
    }, [individualForceDeleteDialogOpen]);

    React.useEffect(() => {
        setRowSelection({});
    }, [data]);

    React.useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const sort = urlParams.get("sort");
        const direction = urlParams.get("direction");
        if (sort) {
            setSorting([{ id: sort, desc: direction === "desc" }]);
        }
    }, []);

    React.useEffect(() => {
        const handleSoftDelete = (event: Event) => {
            const customEvent = event as CustomEvent<Book>;
            setIndividualTarget(customEvent.detail);
            setIndividualDeleteDialogOpen(true);
        };
        const handleForceDelete = (event: Event) => {
            const customEvent = event as CustomEvent<Book>;
            setIndividualTarget(customEvent.detail);
            setIndividualForceDeleteDialogOpen(true);
        };

        window.addEventListener("books:soft-delete", handleSoftDelete);
        window.addEventListener("books:force-delete", handleForceDelete);

        return () => {
            window.removeEventListener("books:soft-delete", handleSoftDelete);
            window.removeEventListener("books:force-delete", handleForceDelete);
        };
    }, []);

    const table = useReactTable({
        data,
        columns,
        getRowId: (row) => String(row.id),
        getCoreRowModel: getCoreRowModel(),
        manualPagination: !!pagination,
        manualSorting: !!pagination,
        pageCount: pagination?.last_page ?? -1,
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: (updater) => {
            const newSorting = typeof updater === "function" ? updater(sorting) : updater;
            setSorting(newSorting);

            if (newSorting.length > 0) {
                const { id, desc } = newSorting[0];
                router.get(
                    route(routeName),
                    { ...filters, sort: id, direction: desc ? "desc" : "asc", page: 1 },
                    { only: ["books", "filters"], preserveScroll: true, preserveState: true, replace: true },
                );
            } else {
                router.get(
                    route(routeName),
                    { ...filters, sort: "created_at", direction: "desc", page: 1 },
                    { only: ["books", "filters"], preserveScroll: true, preserveState: true, replace: true },
                );
            }
        },
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        state: {
            sorting,
            columnVisibility,
            rowSelection,
            pagination: {
                pageIndex: pagination ? pagination.current_page - 1 : 0,
                pageSize: pagination ? pagination.per_page : 10,
            },
        },
    });

    const navigateToPage = (url: string | null) => {
        if (url) {
            router.get(url, {}, { only: ["books", "filters"], preserveScroll: true, preserveState: true, replace: true });
        }
    };

    const buildPageUrl = (page: number): string => {
        return route(routeName, { ...filters, page });
    };

    const handlePerPageChange = (value: string | null) => {
        if (!value) return;

        router.get(
            route(routeName),
            { ...filters, per_page: value, page: 1 },
            { only: ["books", "filters"], preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const selectedRows = table.getFilteredSelectedRowModel().rows;
    const selectedIds = selectedRows
        .map((row) => {
            const record = row.original as { id?: number };
            return record.id;
        })
        .filter((id): id is number => typeof id === "number");
    const selectedCount = selectedIds.length;
    const hasSelection = selectedCount > 0;
    const expectedForceConfirm = `PERMANENTLY DELETE ${selectedCount} BOOK${selectedCount === 1 ? "" : "S"}`;

    const resetSelection = () => {
        table.resetRowSelection();
    };

    const handleBulkDelete = () => {
        if (!hasSelection || isSubmitting) return;

        setIsSubmitting(true);
        router.delete(route("administrators.library.books.bulk-destroy"), {
            data: { book_ids: selectedIds },
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Deleted ${selectedCount} book(s).`);
                setDeleteDialogOpen(false);
                resetSelection();
            },
            onError: () => {
                toast.error("Failed to delete books.");
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleBulkForceDelete = () => {
        if (!hasSelection || isSubmitting) return;

        if (forceDeleteConfirmText !== expectedForceConfirm) {
            toast.error(`Type "${expectedForceConfirm}" exactly to confirm.`);
            return;
        }

        setIsSubmitting(true);
        router.delete(route("administrators.library.books.bulk-force-destroy"), {
            data: {
                book_ids: selectedIds,
                confirm_text: forceDeleteConfirmText,
            },
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as Record<string, unknown>).flash as { type: string; message: string } | undefined;

                if (flash?.type === 'error') {
                    return;
                }

                toast.success(`Permanently deleted ${selectedCount} book(s).`);
                setForceDeleteDialogOpen(false);
                setForceDeleteConfirmText("");
                resetSelection();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(typeof firstError === "string" ? firstError : "Failed to permanently delete books.");
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleIndividualDelete = () => {
        if (!individualTarget || isSubmitting) return;

        setIsSubmitting(true);
        router.delete(route("administrators.library.books.destroy", individualTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`"${individualTarget.title}" moved to trash.`);
                setIndividualDeleteDialogOpen(false);
                setIndividualTarget(null);
            },
            onError: () => {
                toast.error("Failed to delete book.");
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const individualExpectedForceConfirm = individualTarget
        ? "PERMANENTLY DELETE 1 BOOK"
        : "";

    const handleIndividualForceDelete = () => {
        if (!individualTarget || isSubmitting) return;

        if (individualForceDeleteConfirmText !== individualExpectedForceConfirm) {
            toast.error(`Type "${individualExpectedForceConfirm}" exactly to confirm.`);
            return;
        }

        setIsSubmitting(true);
        router.delete(route("administrators.library.books.bulk-force-destroy"), {
            data: {
                book_ids: [individualTarget.id],
                confirm_text: individualForceDeleteConfirmText,
            },
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as Record<string, unknown>).flash as { type: string; message: string } | undefined;

                if (flash?.type === 'error') {
                    return;
                }

                toast.success(`"${individualTarget.title}" permanently deleted.`);
                setIndividualForceDeleteDialogOpen(false);
                setIndividualForceDeleteConfirmText("");
                setIndividualTarget(null);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(typeof firstError === "string" ? firstError : "Failed to permanently delete book.");
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    return (
        <div>
            <div className="flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary" className="h-7 px-2 text-xs">
                        Selected {selectedCount}
                    </Badge>
                    <Button variant="destructive" size="sm" className="gap-2" disabled={!hasSelection} onClick={() => setDeleteDialogOpen(true)}>
                        <Trash2 className="h-4 w-4" />
                        Delete
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        className="gap-2 border-red-900 bg-red-700 hover:bg-red-800"
                        disabled={!hasSelection}
                        onClick={() => setForceDeleteDialogOpen(true)}
                    >
                        <Zap className="h-4 w-4" />
                        Force Delete
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    {pagination && (
                        <div className="flex items-center space-x-2">
                            <p className="text-sm font-medium">Rows per page</p>
                            <Select value={`${pagination.per_page}`} onValueChange={handlePerPageChange}>
                                <SelectTrigger className="h-8 w-[70px]">
                                    <SelectValue placeholder={pagination.per_page} />
                                </SelectTrigger>
                                <SelectContent side="top">
                                    {[10, 20, 50, 100].map((pageSize) => (
                                        <SelectItem key={pageSize} value={`${pageSize}`}>
                                            {pageSize}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="ml-auto">
                                <Settings2 className="mr-2 h-4 w-4" />
                                Columns
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {table
                                .getAllColumns()
                                .filter((column) => column.getCanHide())
                                .map((column) => (
                                    <DropdownMenuCheckboxItem
                                        key={column.id}
                                        className="capitalize"
                                        checked={column.getIsVisible()}
                                        onCheckedChange={(value) => column.toggleVisibility(!!value)}
                                    >
                                        {column.id}
                                    </DropdownMenuCheckboxItem>
                                ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} data-state={row.getIsSelected() && "selected"}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="text-muted-foreground h-24 text-center">
                                    No books match the current filters.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {pagination && (
                <div className="flex items-center justify-between px-2 py-4">
                    <div className="text-muted-foreground flex-1 text-sm">
                        Showing {pagination.from} to {pagination.to} of {pagination.total} books
                    </div>
                    <div className="flex items-center space-x-2 lg:space-x-4">
                        <Button
                            variant="outline"
                            className="hidden h-8 w-8 p-0 lg:flex"
                            onClick={() => navigateToPage(buildPageUrl(1))}
                            disabled={pagination.current_page === 1}
                        >
                            <span className="sr-only">Go to first page</span>
                            <ChevronsLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => navigateToPage(pagination.prev_page_url)}
                            disabled={!pagination.prev_page_url}
                        >
                            <span className="sr-only">Go to previous page</span>
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <div className="flex w-[100px] items-center justify-center text-sm font-medium">
                            Page {pagination.current_page} of {pagination.last_page}
                        </div>
                        <Button
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => navigateToPage(pagination.next_page_url)}
                            disabled={!pagination.next_page_url}
                        >
                            <span className="sr-only">Go to next page</span>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            className="hidden h-8 w-8 p-0 lg:flex"
                            onClick={() => navigateToPage(buildPageUrl(pagination.last_page))}
                            disabled={pagination.current_page === pagination.last_page}
                        >
                            <span className="sr-only">Go to last page</span>
                            <ChevronsRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete selected books?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will move {selectedCount} book{selectedCount === 1 ? "" : "s"} to trash.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSubmitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleBulkDelete} disabled={isSubmitting} className="bg-destructive hover:bg-destructive/90">
                            {isSubmitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Trash2 className="mr-2 h-4 w-4" />}
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={forceDeleteDialogOpen} onOpenChange={setForceDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Permanently delete selected books?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. Type <strong>{expectedForceConfirm}</strong> below to confirm.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="confirm-text">Confirmation text</Label>
                            <Input
                                id="confirm-text"
                                value={forceDeleteConfirmText}
                                onChange={(event) => setForceDeleteConfirmText(event.target.value)}
                                placeholder={expectedForceConfirm}
                            />
                        </div>
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSubmitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleBulkForceDelete}
                            disabled={isSubmitting || forceDeleteConfirmText !== expectedForceConfirm}
                            className="bg-destructive hover:bg-destructive/90"
                        >
                            {isSubmitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Zap className="mr-2 h-4 w-4" />}
                            Permanently Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={individualDeleteDialogOpen} onOpenChange={setIndividualDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete book?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will move "{individualTarget?.title}" to trash.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSubmitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleIndividualDelete} disabled={isSubmitting} className="bg-destructive hover:bg-destructive/90">
                            {isSubmitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Trash2 className="mr-2 h-4 w-4" />}
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={individualForceDeleteDialogOpen} onOpenChange={setIndividualForceDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Permanently delete book?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. Type <strong>{individualExpectedForceConfirm}</strong> below to confirm.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="individual-confirm-text">Confirmation text</Label>
                            <Input
                                id="individual-confirm-text"
                                value={individualForceDeleteConfirmText}
                                onChange={(event) => setIndividualForceDeleteConfirmText(event.target.value)}
                                placeholder={individualExpectedForceConfirm}
                            />
                        </div>
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSubmitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleIndividualForceDelete}
                            disabled={isSubmitting || individualForceDeleteConfirmText !== individualExpectedForceConfirm}
                            className="bg-destructive hover:bg-destructive/90"
                        >
                            {isSubmitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Zap className="mr-2 h-4 w-4" />}
                            Permanently Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
