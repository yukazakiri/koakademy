import {
    ColumnDef,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
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
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { BulkExportButton, type ExportColumn } from "@/components/ui/bulk-export-button";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { AdminLink } from "@/lib/admin-navigation";
import { router } from "@inertiajs/react";
import {
    Check,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Copy,
    Eye,
    FileText,
    GraduationCap,
    HelpCircle,
    Loader2,
    Mail,
    MinusCircle,
    MoreHorizontal,
    RotateCcw,
    Search,
    Settings2,
    Trash2,
    UserCheck,
    X,
    Zap,
} from "lucide-react";
import { toast } from "sonner";
import { getInitials, getStatusColor, type Student } from "./columns";

declare let route: (name: string, params?: Record<string, unknown> | string | number) => string;

interface DataTableProps<TData extends Student, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pageIndex: number;
    pageSize: number;
    pageCount?: number;
    totalCount?: number;
    from?: number;
    to?: number;
    sorting: SortingState;
    onPageIndexChange: (pageIndex: number) => void;
    onPageSizeChange: (pageSize: number) => void;
    onSortingChange: (sorting: SortingState) => void;
    viewMode?: "list" | "grid";
    bulkActions?: {
        statusOptions?: { value: string; label: string }[];
    };
    onSoftDelete?: (student: Student) => void;
    onForceDelete?: (student: Student) => void;
    onRestore?: (student: Student) => void;
    onClearFilters?: () => void;
}

function readableLabel(value: string | null, fallback = "Not specified"): string {
    if (!value) return fallback;

    return value.replace(/_/g, " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

function formatAddedAt(value: string | null): string | null {
    if (!value) return null;

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return new Intl.DateTimeFormat("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(date);
}

const studentExportColumns: ExportColumn<Student>[] = [
    { key: "student_id", label: "Student ID", getValue: (student) => student.student_id },
    { key: "name", label: "Student Name", getValue: (student) => student.name },
    { key: "course", label: "Course Code", getValue: (student) => student.course },
    { key: "course_title", label: "Course Title", defaultSelected: false, getValue: (student) => student.course_title },
    { key: "academic_year", label: "Academic Year", getValue: (student) => student.academic_year },
    { key: "status", label: "Status", getValue: (student) => readableLabel(student.status) },
    { key: "type", label: "Student Type", getValue: (student) => readableLabel(student.type) },
    {
        key: "previous_sem_clearance",
        label: "Clearance",
        defaultSelected: false,
        getValue: (student) =>
            student.previous_sem_clearance === "cleared" ? "Cleared" : student.previous_sem_clearance === "not_cleared" ? "Pending" : "No record",
    },
    { key: "scholarship_type", label: "Scholarship", getValue: (student) => student.scholarship_type || "None" },
    {
        key: "employment_status",
        label: "Employment Status",
        defaultSelected: false,
        getValue: (student) => student.employment_status || "Not specified",
    },
    {
        key: "is_indigenous_person",
        label: "Indigenous Person",
        defaultSelected: false,
        getValue: (student) => student.is_indigenous_person,
    },
    {
        key: "region_of_origin",
        label: "Region of Origin",
        defaultSelected: false,
        getValue: (student) => student.region_of_origin,
    },
    {
        key: "created_at",
        label: "Date Added",
        defaultSelected: false,
        getValue: (student) => formatAddedAt(student.created_at),
    },
];

function GridCardStudentId({ studentId }: { studentId: string | number | null }) {
    const [copied, setCopied] = React.useState(false);

    if (!studentId) return <span className="text-muted-foreground font-mono text-xs">No ID</span>;

    const handleCopy = async (e: React.MouseEvent) => {
        e.stopPropagation();
        try {
            await navigator.clipboard.writeText(String(studentId));
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // ignore
        }
    };

    return (
        <button
            type="button"
            onClick={handleCopy}
            className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 font-mono text-xs transition-colors"
            title={copied ? "Copied!" : "Click to copy student ID"}
        >
            <span>{studentId}</span>
            {copied ? <Check className="size-3 text-emerald-600 dark:text-emerald-400" /> : <Copy className="size-3 opacity-60 hover:opacity-100" />}
        </button>
    );
}

export function DataTable<TData extends Student, TValue>({
    columns,
    data,
    pageIndex,
    pageSize,
    pageCount,
    totalCount,
    from,
    to,
    sorting,
    onPageIndexChange,
    onPageSizeChange,
    onSortingChange,
    viewMode = "list",
    bulkActions,
    onSoftDelete,
    onForceDelete,
    onRestore,
    onClearFilters,
}: DataTableProps<TData, TValue>) {
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [emailDialogOpen, setEmailDialogOpen] = React.useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [forceDeleteDialogOpen, setForceDeleteDialogOpen] = React.useState(false);
    const [forceDeleteConfirmText, setForceDeleteConfirmText] = React.useState("");
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const defaultEmailSubject = "Important Update Regarding Your Student Record";
    const defaultEmailMessage =
        "We hope this message finds you well.\n\nWe are writing to inform you of an important update to your student record. Please review this information at your earliest convenience and respond if any details require clarification.\n\nThank you for your attention and cooperation.";
    const [emailSubject, setEmailSubject] = React.useState(defaultEmailSubject);
    const [emailMessage, setEmailMessage] = React.useState(defaultEmailMessage);

    React.useEffect(() => {
        if (!forceDeleteDialogOpen) {
            setForceDeleteConfirmText("");
        }
    }, [forceDeleteDialogOpen]);

    React.useEffect(() => {
        setRowSelection({});
    }, [data]);

    const totalMatching = totalCount !== undefined ? totalCount : data.length;
    const fromIndex = from !== undefined ? from : totalMatching === 0 ? 0 : pageIndex * pageSize + 1;
    const toIndex = to !== undefined ? to : totalMatching === 0 ? 0 : Math.min((pageIndex + 1) * pageSize, totalMatching);
    const totalPages = pageCount !== undefined ? pageCount : Math.max(1, Math.ceil(totalMatching / pageSize));

    const table = useReactTable({
        data,
        columns,
        getRowId: (row) => String(row.id),
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        pageCount: totalPages,
        onSortingChange: (updater) => {
            const nextSorting = typeof updater === "function" ? updater(sorting) : updater;
            onSortingChange(nextSorting);
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
                pageIndex,
                pageSize,
            },
        },
        onPaginationChange: (updater) => {
            const currentPagination = { pageIndex, pageSize };
            const nextPagination = typeof updater === "function" ? updater(currentPagination) : updater;

            if (nextPagination.pageSize !== pageSize) {
                onPageSizeChange(nextPagination.pageSize);
            } else if (nextPagination.pageIndex !== pageIndex) {
                onPageIndexChange(nextPagination.pageIndex);
            }
        },
    });

    const selectedRows = table.getFilteredSelectedRowModel().rows;
    const selectedData = selectedRows.map((row) => row.original);
    const selectedIds = selectedRows
        .map((row) => {
            const record = row.original as { id?: number };
            return record.id;
        })
        .filter((id): id is number => typeof id === "number");
    const selectedCount = selectedIds.length;
    const hasSelection = selectedCount > 0;

    const resetSelection = () => {
        table.resetRowSelection();
    };

    const resetEmailForm = () => {
        setEmailSubject(defaultEmailSubject);
        setEmailMessage(defaultEmailMessage);
    };

    const handleBulkStatusSubmit = (status: string) => {
        if (!hasSelection || !status || isSubmitting) {
            return;
        }

        setIsSubmitting(true);
        router.patch(
            route("administrators.students.bulk-update-status"),
            { student_ids: selectedIds, status },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Updated status for ${selectedCount} student(s).`);
                    resetSelection();
                },
                onError: () => {
                    toast.error("Failed to update student statuses.");
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleBulkClearanceSubmit = (isCleared: boolean) => {
        if (!hasSelection || isSubmitting) {
            return;
        }

        setIsSubmitting(true);
        router.post(
            route("administrators.students.bulk-manage-clearance"),
            { student_ids: selectedIds, is_cleared: isCleared },
            {
                preserveScroll: true,
                onSuccess: () => {
                    const message = isCleared ? "marked as cleared" : "marked as pending";
                    toast.success(`${selectedCount} student(s) ${message}.`);
                    resetSelection();
                },
                onError: () => {
                    toast.error("Failed to update student clearance.");
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleBulkDelete = () => {
        if (!hasSelection || isSubmitting) {
            return;
        }

        setIsSubmitting(true);
        router.delete(route("administrators.students.bulk-destroy"), {
            data: { student_ids: selectedIds },
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Moved ${selectedCount} student(s) to trash.`);
                setDeleteDialogOpen(false);
                resetSelection();
            },
            onError: () => {
                toast.error("Failed to delete students.");
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const expectedForceConfirm = `PERMANENTLY DELETE ${selectedCount} STUDENT${selectedCount === 1 ? "" : "S"}`;

    const handleBulkForceDelete = () => {
        if (!hasSelection || forceDeleteConfirmText !== expectedForceConfirm || isSubmitting) {
            toast.error(`Type "${expectedForceConfirm}" exactly to confirm.`);
            return;
        }

        setIsSubmitting(true);
        router.delete(route("administrators.students.bulk-force-destroy"), {
            data: { student_ids: selectedIds, confirm_text: forceDeleteConfirmText },
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Permanently deleted ${selectedCount} student(s).`);
                setForceDeleteDialogOpen(false);
                setForceDeleteConfirmText("");
                resetSelection();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(typeof firstError === "string" ? firstError : "Failed to permanently delete students.");
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleBulkEmailSubmit = () => {
        if (!hasSelection || !emailSubject.trim() || !emailMessage.trim() || isSubmitting) {
            return;
        }

        setIsSubmitting(true);
        router.post(
            route("administrators.students.bulk-email"),
            { student_ids: selectedIds, subject: emailSubject.trim(), message: emailMessage.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Email sent to ${selectedCount} student(s).`);
                    setEmailDialogOpen(false);
                    resetEmailForm();
                    resetSelection();
                },
                onError: () => {
                    toast.error("Failed to send email.");
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleRowSoftDelete = (student: Student) => {
        if (onSoftDelete) {
            onSoftDelete(student);
        } else {
            window.dispatchEvent(new CustomEvent("students:soft-delete", { detail: student }));
        }
    };

    const handleRowForceDelete = (student: Student) => {
        if (onForceDelete) {
            onForceDelete(student);
        } else {
            window.dispatchEvent(new CustomEvent("students:force-delete", { detail: student }));
        }
    };

    const handleRowRestore = (student: Student) => {
        if (onRestore) {
            onRestore(student);
        } else {
            window.dispatchEvent(new CustomEvent("students:restore", { detail: student }));
        }
    };

    const pageRows = table.getRowModel().rows;

    return (
        <div className="space-y-4">
            {/* Contextual Floating Bulk Action Bar */}
            {hasSelection ? (
                <div className="border-primary/20 bg-card/95 animate-in fade-in slide-in-from-top-2 sticky top-3 z-30 flex flex-wrap items-center justify-between gap-2.5 rounded-lg border p-3 shadow-lg backdrop-blur duration-200">
                    <div className="flex items-center gap-2">
                        <Badge variant="default" className="px-2.5 py-1 text-xs font-semibold">
                            {selectedCount} selected
                        </Badge>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={resetSelection}
                            className="text-muted-foreground hover:text-foreground h-7 gap-1 px-2 text-xs"
                        >
                            <X className="size-3.5" />
                            Deselect all
                        </Button>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        {bulkActions?.statusOptions?.length ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    render={
                                        <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs" disabled={isSubmitting}>
                                            <GraduationCap className="size-3.5" />
                                            Change Status
                                        </Button>
                                    }
                                />
                                <DropdownMenuContent align="end" className="w-48">
                                    <DropdownMenuLabel className="text-xs">Set Status to</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {bulkActions.statusOptions.map((option) => (
                                        <DropdownMenuItem key={option.value} onClick={() => handleBulkStatusSubmit(option.value)}>
                                            {option.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : null}

                        <DropdownMenu>
                            <DropdownMenuTrigger
                                render={
                                    <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs" disabled={isSubmitting}>
                                        <CheckCircle className="size-3.5" />
                                        Clearance
                                    </Button>
                                }
                            />
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuLabel className="text-xs">Update Clearance</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => handleBulkClearanceSubmit(true)}>
                                    <CheckCircle className="mr-2 size-3.5 text-emerald-600 dark:text-emerald-400" />
                                    Mark as Cleared
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleBulkClearanceSubmit(false)}>
                                    <HelpCircle className="mr-2 size-3.5 text-amber-600 dark:text-amber-400" />
                                    Mark as Pending
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            disabled={isSubmitting}
                            onClick={() => setEmailDialogOpen(true)}
                        >
                            <Mail className="size-3.5" />
                            Send Email
                        </Button>

                        <BulkExportButton
                            data={selectedData}
                            columns={studentExportColumns}
                            filename="selected-students"
                            title="Selected Students"
                            getSortValue={(student) => student.name}
                            getSortTieBreaker={(student) => String(student.student_id ?? student.id)}
                        />

                        <DropdownMenu>
                            <DropdownMenuTrigger
                                render={
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-8 px-2 text-xs"
                                        disabled={isSubmitting}
                                        aria-label="More bulk options"
                                    >
                                        <MoreHorizontal className="size-4" />
                                    </Button>
                                }
                            />
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuLabel className="text-xs">Destructive Actions</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => setDeleteDialogOpen(true)} className="text-amber-600 focus:text-amber-600">
                                    <Trash2 className="mr-2 size-3.5" /> Move to Trash
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => setForceDeleteDialogOpen(true)} className="text-destructive focus:text-destructive">
                                    <Zap className="mr-2 size-3.5" /> Force Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            ) : null}

            {/* Table Utility Controls */}
            <div className="flex items-center justify-between gap-2 px-0.5">
                <p className="text-muted-foreground text-xs">
                    {totalMatching === 0 ? "No records" : `Showing ${fromIndex}–${toIndex} of ${totalMatching} students`}
                </p>

                <div className="flex items-center gap-2">
                    <div className="flex items-center gap-1.5">
                        <span className="text-muted-foreground hidden text-xs sm:inline">Rows:</span>
                        <Select
                            value={`${pageSize}`}
                            onValueChange={(value) => {
                                if (value) {
                                    onPageSizeChange(Number(value));
                                }
                            }}
                        >
                            <SelectTrigger className="h-8 w-[72px] text-xs">
                                <SelectValue placeholder={pageSize} />
                            </SelectTrigger>
                            <SelectContent side="top">
                                {[10, 20, 50, 100].map((pageSizeOption) => (
                                    <SelectItem key={pageSizeOption} value={`${pageSizeOption}`} className="text-xs">
                                        {pageSizeOption}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {viewMode === "list" && (
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                render={
                                    <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
                                        <Settings2 className="size-3.5" />
                                        <span className="hidden sm:inline">Columns</span>
                                    </Button>
                                }
                            />
                            <DropdownMenuContent align="end" className="w-44">
                                <DropdownMenuLabel className="text-xs">Toggle Columns</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {table
                                    .getAllColumns()
                                    .filter((column) => column.getCanHide())
                                    .map((column) => {
                                        return (
                                            <DropdownMenuCheckboxItem
                                                key={column.id}
                                                className="text-xs capitalize"
                                                checked={column.getIsVisible()}
                                                onCheckedChange={(value) => column.toggleVisibility(!!value)}
                                            >
                                                {column.id.replace(/_/g, " ")}
                                            </DropdownMenuCheckboxItem>
                                        );
                                    })}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>

            {/* Email Dialog */}
            <Dialog
                open={emailDialogOpen}
                onOpenChange={(open) => {
                    setEmailDialogOpen(open);
                    if (!open) {
                        resetEmailForm();
                    }
                }}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Send Email to Students</DialogTitle>
                        <DialogDescription>
                            Send a notification or update to {selectedCount} selected student{selectedCount === 1 ? "" : "s"}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3.5 py-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="bulk-email-subject">Subject</Label>
                            <Input
                                id="bulk-email-subject"
                                value={emailSubject}
                                onChange={(event) => setEmailSubject(event.target.value)}
                                placeholder="Email subject..."
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="bulk-email-message">Message</Label>
                            <Textarea
                                id="bulk-email-message"
                                value={emailMessage}
                                onChange={(event) => setEmailMessage(event.target.value)}
                                rows={6}
                                placeholder="Write your message here..."
                            />
                            <p className="text-muted-foreground text-[11px]">
                                A personalized greeting and formal signature will be included automatically.
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEmailDialogOpen(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleBulkEmailSubmit}
                            disabled={!hasSelection || !emailSubject.trim() || !emailMessage.trim() || isSubmitting}
                            className="gap-1.5"
                        >
                            {isSubmitting && <Loader2 className="size-3.5 animate-spin" />}
                            Send to {selectedCount} Student{selectedCount === 1 ? "" : "s"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Soft Delete Confirmation */}
            <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <Trash2 className="size-5 text-amber-600" />
                            Move {selectedCount} Student{selectedCount === 1 ? "" : "s"} to Trash?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            The selected student record{selectedCount === 1 ? "" : "s"} will be moved to trash and hidden from active views. You can
                            restore them at any time from the Trashed view.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSubmitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleBulkDelete}
                            className="bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-700"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Trash2 className="mr-2 size-4" />}
                            Move to Trash
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Permanent Force Delete Confirmation */}
            <AlertDialog open={forceDeleteDialogOpen} onOpenChange={(open) => !isSubmitting && setForceDeleteDialogOpen(open)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-destructive flex items-center gap-2">
                            <Zap className="size-5" />
                            Permanently Delete {selectedCount} Student{selectedCount === 1 ? "" : "s"}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently erase all {selectedCount} selected student record{selectedCount === 1 ? "" : "s"} along with their
                            enrollments, tuition, clearances, grades, and documents. This action{" "}
                            <span className="text-foreground font-semibold">cannot be undone</span>.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="space-y-2 py-1">
                        <Label htmlFor="bulk-force-confirm">
                            Type <span className="text-foreground font-mono font-semibold">{expectedForceConfirm}</span> to confirm:
                        </Label>
                        <Input
                            id="bulk-force-confirm"
                            value={forceDeleteConfirmText}
                            onChange={(e) => setForceDeleteConfirmText(e.target.value)}
                            placeholder={expectedForceConfirm}
                            autoComplete="off"
                            disabled={isSubmitting}
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isSubmitting}>Cancel</AlertDialogCancel>
                        <Button
                            onClick={handleBulkForceDelete}
                            disabled={isSubmitting || forceDeleteConfirmText.trim() !== expectedForceConfirm}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {isSubmitting ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Zap className="mr-2 size-4" />}
                            Permanently Delete
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* View Mode: List (Table) */}
            {viewMode === "list" ? (
                <div className="bg-card overflow-x-auto rounded-lg border shadow-xs">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id} className="bg-muted/40 hover:bg-muted/40">
                                    {headerGroup.headers.map((header) => {
                                        return (
                                            <TableHead key={header.id} className="py-3 text-xs font-semibold">
                                                {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                            </TableHead>
                                        );
                                    })}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {pageRows?.length ? (
                                pageRows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        data-state={row.getIsSelected() && "selected"}
                                        className="hover:bg-muted/30 transition-colors"
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id} className="py-2.5 text-xs">
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={columns.length} className="h-44 text-center">
                                        <div className="text-muted-foreground flex flex-col items-center justify-center gap-2">
                                            <Search className="size-8 opacity-30" />
                                            <p className="text-sm font-medium">No students match your criteria</p>
                                            {onClearFilters && (
                                                <Button variant="outline" size="sm" onClick={onClearFilters} className="mt-1 h-7 text-xs">
                                                    Reset search & filters
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            ) : (
                /* View Mode: Grid (Responsive Cards) */
                <div>
                    {pageRows?.length ? (
                        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {pageRows.map((row) => {
                                const student = row.original;
                                const isSelected = row.getIsSelected();

                                return (
                                    <Card
                                        key={row.id}
                                        className={`relative transition-all hover:shadow-md ${
                                            isSelected ? "ring-primary border-primary/50 bg-primary/5 ring-2" : ""
                                        }`}
                                    >
                                        <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 p-4 pb-2">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <Checkbox
                                                    checked={isSelected}
                                                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                                                    aria-label={`Select ${student.name}`}
                                                    className="mt-0.5"
                                                />
                                                <Avatar className="size-10 shrink-0 border">
                                                    <AvatarImage src={student.avatar_url ?? undefined} alt={student.name} />
                                                    <AvatarFallback className="bg-primary/10 text-primary text-sm font-semibold">
                                                        {getInitials(student.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex min-w-0 flex-col">
                                                    <AdminLink
                                                        href={route("administrators.students.show", student.id)}
                                                        className="text-foreground truncate text-sm font-semibold hover:underline"
                                                        title={student.name}
                                                    >
                                                        {student.name}
                                                    </AdminLink>
                                                    <GridCardStudentId studentId={student.student_id} />
                                                </div>
                                            </div>

                                            <DropdownMenu>
                                                <DropdownMenuTrigger
                                                    render={<Button variant="ghost" size="icon" className="-mr-1 size-7 shrink-0" />}
                                                    aria-label={`Options for ${student.name}`}
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-48">
                                                    <DropdownMenuItem render={<AdminLink href={route("administrators.students.show", student.id)} />}>
                                                        <Eye className="mr-2 size-4" /> View Details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem render={<AdminLink href={route("administrators.students.edit", student.id)} />}>
                                                        <UserCheck className="mr-2 size-4" /> Edit Profile
                                                    </DropdownMenuItem>
                                                    {student.filament?.view_url && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                render={<a href={student.filament.view_url} target="_blank" rel="noreferrer" />}
                                                                className="opacity-80"
                                                            >
                                                                <FileText className="mr-2 size-4" /> View in Filament
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                    <DropdownMenuSeparator />
                                                    {student.deleted_at ? (
                                                        <DropdownMenuItem onClick={() => handleRowRestore(student)}>
                                                            <RotateCcw className="mr-2 size-4" /> Restore
                                                        </DropdownMenuItem>
                                                    ) : (
                                                        <DropdownMenuItem
                                                            onClick={() => handleRowSoftDelete(student)}
                                                            className="text-amber-600 focus:text-amber-600"
                                                        >
                                                            <Trash2 className="mr-2 size-4" /> Move to Trash
                                                        </DropdownMenuItem>
                                                    )}
                                                    <DropdownMenuItem
                                                        onClick={() => handleRowForceDelete(student)}
                                                        className="text-destructive focus:text-destructive"
                                                    >
                                                        <Zap className="mr-2 size-4" /> Force Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </CardHeader>

                                        <CardContent className="space-y-2.5 p-4 pt-2 pb-3">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <Badge
                                                    variant="outline"
                                                    className={`text-[10px] font-semibold uppercase shadow-none ${getStatusColor(student.status)}`}
                                                >
                                                    {student.status ?? "Unknown"}
                                                </Badge>
                                                {student.course && (
                                                    <Badge variant="outline" className="text-[10px] font-normal">
                                                        {student.course}
                                                    </Badge>
                                                )}
                                                {student.academic_year && (
                                                    <Badge variant="secondary" className="text-[10px] font-normal">
                                                        {student.academic_year}
                                                    </Badge>
                                                )}
                                            </div>

                                            <div className="text-muted-foreground space-y-1 border-t pt-2 text-xs">
                                                <div className="flex items-center justify-between">
                                                    <span>Clearance:</span>
                                                    <div className="flex items-center gap-1">
                                                        {student.previous_sem_clearance === "cleared" ? (
                                                            <span className="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                                                                <CheckCircle className="size-3" /> Cleared
                                                            </span>
                                                        ) : student.previous_sem_clearance === "not_cleared" ? (
                                                            <span className="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400">
                                                                <HelpCircle className="size-3" /> Pending
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground inline-flex items-center gap-1">
                                                                <MinusCircle className="size-3" /> No record
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>

                                                {student.scholarship_type && student.scholarship_type !== "None" && (
                                                    <div className="flex items-center justify-between">
                                                        <span>Scholarship:</span>
                                                        <span className="text-foreground max-w-[150px] truncate font-medium">
                                                            {student.scholarship_type}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </CardContent>

                                        <CardFooter className="gap-2 p-4 pt-0">
                                            <AdminLink
                                                href={route("administrators.students.show", student.id)}
                                                className={buttonVariants({
                                                    variant: "outline",
                                                    size: "sm",
                                                    className: "h-8 w-full text-xs font-medium",
                                                })}
                                            >
                                                View Profile
                                            </AdminLink>
                                        </CardFooter>
                                    </Card>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="bg-muted/10 flex h-56 flex-col items-center justify-center rounded-lg border border-dashed p-6 text-center">
                            <Search className="text-muted-foreground mb-2 size-8 opacity-30" />
                            <p className="text-foreground text-sm font-medium">No students match your criteria</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">Try adjusting your search keywords or clearing active filters.</p>
                            {onClearFilters && (
                                <Button variant="outline" size="sm" onClick={onClearFilters} className="mt-3 h-8 text-xs">
                                    Reset search & filters
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* Streamlined Responsive Pagination */}
            {totalMatching > 0 && (
                <div className="flex flex-col items-center justify-between gap-3 border-t pt-4 sm:flex-row">
                    <div className="text-muted-foreground text-xs">
                        Showing <span className="text-foreground font-medium">{fromIndex}</span> to{" "}
                        <span className="text-foreground font-medium">{toIndex}</span> of{" "}
                        <span className="text-foreground font-medium">{totalMatching}</span> entries
                    </div>

                    <div className="flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="icon"
                            className="hidden size-8 sm:inline-flex"
                            onClick={() => onPageIndexChange(0)}
                            disabled={pageIndex === 0}
                            aria-label="First page"
                        >
                            <ChevronsLeft className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1 px-2.5 text-xs"
                            onClick={() => onPageIndexChange(Math.max(0, pageIndex - 1))}
                            disabled={pageIndex === 0}
                        >
                            <ChevronLeft className="size-4" />
                            <span>Previous</span>
                        </Button>

                        <span className="text-muted-foreground px-2 text-xs font-medium">
                            Page {pageIndex + 1} of {totalPages}
                        </span>

                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1 px-2.5 text-xs"
                            onClick={() => onPageIndexChange(Math.min(totalPages - 1, pageIndex + 1))}
                            disabled={pageIndex >= totalPages - 1}
                        >
                            <span>Next</span>
                            <ChevronRight className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="hidden size-8 sm:inline-flex"
                            onClick={() => onPageIndexChange(totalPages - 1)}
                            disabled={pageIndex >= totalPages - 1}
                            aria-label="Last page"
                        >
                            <ChevronsRight className="size-4" />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
