import { Badge } from "@/components/reui/badge";
import { DataGrid, DataGridContainer, dataGridFeatures, type DataGridFeatures } from "@/components/reui/data-grid/data-grid";
import { DataGridColumnHeader } from "@/components/reui/data-grid/data-grid-column-header";
import { DataGridColumnVisibility } from "@/components/reui/data-grid/data-grid-column-visibility";
import { DataGridPagination } from "@/components/reui/data-grid/data-grid-pagination";
import { DataGridScrollArea } from "@/components/reui/data-grid/data-grid-scroll-area";
import { DataGridTable } from "@/components/reui/data-grid/data-grid-table";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
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
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { type ColumnDef, type PaginationState, type RowSelectionState, type SortingState, useTable } from "@tanstack/react-table-v9";
import {
    AlertTriangle,
    ArrowRight,
    Copy,
    CreditCard,
    Download,
    Eye,
    FileText,
    Loader2,
    MoreHorizontal,
    Pencil,
    RotateCcw,
    Send,
    Settings2,
    Trash2,
    X,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import type { EnrollmentRow, EnrollmentTransitionOption } from "./columns";
import { exportEnrollmentsToCsv, formatMoney, formatShortDate, getInitials, resolveStatusMeta, tuitionSettlementPercent } from "./enrollment-utils";
import type { EnrollmentManagementProps, EnrollmentPipeline, EnrollmentWorkspacePreferences } from "./types";

type EnrollmentDataGridProps = {
    data: EnrollmentRow[];
    pagination: EnrollmentManagementProps["enrollments"];
    pipeline: EnrollmentPipeline;
    currency: string;
    sort: string;
    direction: "asc" | "desc";
    isLoading?: boolean;
    bulkBusy?: boolean;
    preferences?: Pick<EnrollmentWorkspacePreferences, "density" | "showTuitionBars">;
    onInspect: (enrollment: EnrollmentRow, options?: { openCashier?: boolean; transitionKey?: string }) => void;
    onQuickAdvance: (enrollment: EnrollmentRow, transition?: EnrollmentTransitionOption) => void;
    onResendAssessment: (enrollment: EnrollmentRow) => void;
    onDelete: (enrollment: EnrollmentRow) => void;
    onForceDelete: (enrollment: EnrollmentRow) => void;
    onRestore: (enrollment: EnrollmentRow) => void;
    onBulkAction: (action: "advance" | "resend_assessment" | "delete" | "restore", rows: EnrollmentRow[]) => void;
    onPaginationChange: (page: number, perPage: number) => void;
    onSortingChange: (sort: string, direction: "asc" | "desc") => void;
};

export function EnrollmentDataGrid({
    data,
    pagination,
    pipeline,
    currency,
    sort,
    direction,
    isLoading = false,
    bulkBusy = false,
    preferences,
    onInspect,
    onQuickAdvance,
    onResendAssessment,
    onDelete,
    onForceDelete,
    onRestore,
    onBulkAction,
    onPaginationChange,
    onSortingChange,
}: EnrollmentDataGridProps) {
    const isDense = preferences?.density === "compact";
    const showTuitionBars = preferences?.showTuitionBars ?? true;
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

    // Clear stale selection when the page changes
    useEffect(() => {
        setRowSelection({});
    }, [pagination.current_page, data]);

    const sorting = useMemo<SortingState>(() => {
        if (!sort) return [];
        return [{ id: sort, desc: direction === "desc" }];
    }, [sort, direction]);

    const paginationState = useMemo<PaginationState>(
        () => ({
            pageIndex: Math.max(0, (pagination?.current_page ?? 1) - 1),
            pageSize: pagination?.per_page ?? 20,
        }),
        [pagination?.current_page, pagination?.per_page],
    );

    const columns = useMemo<ColumnDef<DataGridFeatures, EnrollmentRow>[]>(
        () => [
            {
                id: "select",
                header: ({ table }) => (
                    <Checkbox
                        checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                        aria-label="Select all enrollments"
                    />
                ),
                cell: ({ row }) => (
                    <div onClick={(e) => e.stopPropagation()}>
                        <Checkbox
                            checked={row.getIsSelected()}
                            onCheckedChange={(value) => row.toggleSelected(!!value)}
                            aria-label={`Select ${row.original.student_name ?? "enrollment"}`}
                        />
                    </div>
                ),
                size: 40,
                enableSorting: false,
                enableHiding: false,
            },
            {
                id: "student_name",
                accessorKey: "student_name",
                header: ({ column }) => <DataGridColumnHeader column={column} title="Student" />,
                cell: ({ row }) => {
                    const enrollment = row.original;
                    const displayId = enrollment.student_number ?? enrollment.student_id;

                    return (
                        <div className="flex max-w-[240px] min-w-0 items-center gap-2.5">
                            <Avatar className="border-border/70 size-8 shrink-0 border">
                                <AvatarFallback
                                    className={cn(
                                        "text-[11px] font-semibold",
                                        enrollment.is_trashed ? "bg-destructive/15 text-destructive" : "bg-primary/10 text-primary",
                                    )}
                                >
                                    {getInitials(enrollment.student_name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-1.5">
                                    <p
                                        className={cn(
                                            "truncate text-sm font-semibold",
                                            enrollment.is_trashed ? "text-muted-foreground line-through" : "text-foreground",
                                        )}
                                    >
                                        {enrollment.student_name ?? "Unknown Student"}
                                    </p>
                                    {enrollment.is_trashed && (
                                        <Badge variant="destructive-light" size="xs" radius="full">
                                            Deleted
                                        </Badge>
                                    )}
                                    {(enrollment.active_holds_count ?? 0) > 0 && (
                                        <Badge
                                            variant={(enrollment.blocking_holds_count ?? 0) > 0 ? "destructive-light" : "warning-light"}
                                            size="xs"
                                            radius="full"
                                            title={`Active hold(s): ${(enrollment.hold_offices ?? []).join(", ")}`}
                                        >
                                            {(enrollment.blocking_holds_count ?? 0) > 0
                                                ? `${enrollment.blocking_holds_count} Hold${enrollment.blocking_holds_count === 1 ? "" : "s"}`
                                                : `${enrollment.active_holds_count} Notice`}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-muted-foreground truncate text-xs tabular-nums">
                                    <span className="font-mono">ID {displayId ?? "—"}</span>
                                    <span className="mx-1">·</span>
                                    <span>
                                        {enrollment.subjects_count} subj{enrollment.subjects_count === 1 ? "" : "s"}
                                    </span>
                                    <span className="mx-1 hidden 2xl:inline">·</span>
                                    <span className="hidden 2xl:inline">{formatShortDate(enrollment.created_at)}</span>
                                </p>
                            </div>
                        </div>
                    );
                },
                size: 240,
                enableHiding: false,
            },
            {
                id: "course",
                accessorKey: "course",
                header: "Program",
                cell: ({ row }) => {
                    const enrollment = row.original;
                    return (
                        <div className="max-w-[205px] min-w-0">
                            <div className="flex items-center gap-1.5">
                                <span className="truncate text-sm font-medium">{enrollment.course ?? "Unassigned"}</span>
                                {enrollment.academic_year && (
                                    <Badge variant="outline" size="xs" className="shrink-0">
                                        Yr {enrollment.academic_year}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-muted-foreground truncate text-xs" title={enrollment.course_title ?? undefined}>
                                {enrollment.department ?? "No dept"}
                                {enrollment.course_title ? ` · ${enrollment.course_title}` : ""}
                            </p>
                        </div>
                    );
                },
                size: 205,
                enableSorting: false,
            },
            {
                id: "status",
                accessorKey: "status",
                header: "Pipeline Stage",
                cell: ({ row }) => {
                    const enrollment = row.original;
                    const meta = resolveStatusMeta(enrollment.status, enrollment.terminal_outcome, pipeline);

                    return (
                        <div className="flex flex-col gap-0.5">
                            <Badge variant={meta.badge} radius="full" size="lg" className="w-fit gap-1.5">
                                <span className={cn("size-1.5 rounded-full", meta.dot)} aria-hidden="true" />
                                {meta.label}
                            </Badge>
                            {meta.isCancelled && enrollment.status && (
                                <span className="text-muted-foreground pl-1 text-[10.5px]">was {enrollment.status}</span>
                            )}
                        </div>
                    );
                },
                size: 170,
                enableSorting: false,
            },
            {
                id: "tuition",
                accessorFn: (row) => row.tuition?.overall ?? 0,
                header: ({ column }) => <DataGridColumnHeader column={column} title="Tuition / Balance" />,
                cell: ({ row }) => {
                    const tuition = row.original.tuition;
                    const overall = tuition?.overall ?? null;
                    const balance = tuition?.balance ?? 0;
                    const pct = tuitionSettlementPercent(overall, balance);

                    return (
                        <div className="flex min-w-[140px] flex-col gap-1">
                            <div className="flex items-baseline justify-between gap-2 text-xs tabular-nums">
                                <span className="text-foreground font-semibold">{formatMoney(overall, currency)}</span>
                                <span
                                    className={cn(
                                        "font-medium",
                                        balance > 0 ? "text-amber-600 dark:text-amber-400" : "text-emerald-600 dark:text-emerald-400",
                                    )}
                                >
                                    {balance > 0 ? `${formatMoney(balance, currency)} due` : "Paid"}
                                </span>
                            </div>
                            {showTuitionBars && (
                                <div className="bg-muted h-1 w-full overflow-hidden rounded-full">
                                    <div
                                        className={cn("h-full rounded-full", balance > 0 ? "bg-amber-500" : "bg-emerald-500")}
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            )}
                        </div>
                    );
                },
                size: 175,
            },
            {
                id: "actions",
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => {
                    const enrollment = row.original;
                    const statusMeta = resolveStatusMeta(enrollment.status, enrollment.terminal_outcome, pipeline);
                    const nextStep = enrollment.next_step;
                    const primaryTransition = enrollment.allowed_transitions?.[0];
                    const hasMultipleBranches = (enrollment.allowed_transitions?.length ?? 0) > 1;
                    const needsReason = Boolean(primaryTransition?.requires_reason || nextStep?.requires_reason);
                    const nextNeedsCashier = Boolean(primaryTransition?.needs_payment || nextStep?.action_type === "cashier_verification");
                    const canAdvance = Boolean(
                        enrollment.can_advance && (nextStep || primaryTransition) && !enrollment.is_trashed && !statusMeta.isCancelled,
                    );

                    return (
                        <div className="flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                            {canAdvance ? (
                                <Button
                                    type="button"
                                    size="xs"
                                    variant={nextNeedsCashier ? "outline" : "secondary"}
                                    className="h-7 gap-1 px-2.5 text-xs"
                                    onClick={() => {
                                        if (hasMultipleBranches || needsReason || nextNeedsCashier) {
                                            onInspect(enrollment, {
                                                openCashier: nextNeedsCashier,
                                                transitionKey: primaryTransition?.key,
                                            });
                                        } else {
                                            onQuickAdvance(enrollment, primaryTransition);
                                        }
                                    }}
                                >
                                    {hasMultipleBranches ? (
                                        <>
                                            Review
                                            <ArrowRight className="size-3" />
                                        </>
                                    ) : nextNeedsCashier ? (
                                        <>
                                            <CreditCard className="size-3" />
                                            Verify
                                        </>
                                    ) : (
                                        <>
                                            Advance
                                            <ArrowRight className="size-3" />
                                        </>
                                    )}
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    className="text-muted-foreground hover:text-foreground h-7 px-2 text-xs"
                                    onClick={() => onInspect(enrollment)}
                                >
                                    Inspect
                                </Button>
                            )}

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon-xs" className="h-7 w-7">
                                        <span className="sr-only">Open enrollment menu</span>
                                        <MoreHorizontal className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-48">
                                    <DropdownMenuLabel>Enrollment actions</DropdownMenuLabel>
                                    <DropdownMenuItem onClick={() => onInspect(enrollment)}>
                                        <Eye className="mr-2 size-4" />
                                        Quick inspect sheet
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route("administrators.enrollments.show", enrollment.id)} className="flex w-full items-center">
                                            <FileText className="mr-2 size-4" />
                                            Open full record
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={route("administrators.enrollments.edit", enrollment.id)} className="flex w-full items-center">
                                            <Pencil className="mr-2 size-4" />
                                            Edit enrollment
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onClick={() => {
                                            void navigator.clipboard.writeText(String(enrollment.student_number ?? enrollment.student_id ?? ""));
                                            toast.success("Copied Student ID");
                                        }}
                                    >
                                        <Copy className="mr-2 size-4" />
                                        Copy Student ID
                                    </DropdownMenuItem>
                                    {!enrollment.is_trashed && (
                                        <DropdownMenuItem onClick={() => onResendAssessment(enrollment)}>
                                            <Send className="mr-2 size-4" />
                                            Resend assessment
                                        </DropdownMenuItem>
                                    )}
                                    {statusMeta.isCompleted && (
                                        <DropdownMenuItem
                                            onClick={() => {
                                                window.open(route("assessment.download", { record: enrollment.id }), "_blank");
                                            }}
                                        >
                                            <Download className="mr-2 size-4" />
                                            Download assessment PDF
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuSeparator />
                                    {enrollment.is_trashed ? (
                                        <>
                                            <DropdownMenuItem
                                                onClick={() => onRestore(enrollment)}
                                                className="text-emerald-600 focus:text-emerald-600"
                                            >
                                                <RotateCcw className="mr-2 size-4" />
                                                Restore record
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() => onForceDelete(enrollment)}
                                                className="text-destructive focus:text-destructive"
                                            >
                                                <AlertTriangle className="mr-2 size-4" />
                                                Delete permanently
                                            </DropdownMenuItem>
                                        </>
                                    ) : (
                                        <DropdownMenuItem onClick={() => onDelete(enrollment)} className="text-destructive focus:text-destructive">
                                            <Trash2 className="mr-2 size-4" />
                                            Move to trash
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    );
                },
                size: 130,
                enableSorting: false,
                enableHiding: false,
                meta: {
                    headerClassName: "text-right",
                    cellClassName: "text-right",
                },
            },
        ],
        [currency, pipeline, showTuitionBars, onInspect, onQuickAdvance, onResendAssessment, onDelete, onForceDelete, onRestore],
    );

    const table = useTable({
        features: dataGridFeatures,
        columns,
        data,
        getRowId: (row: EnrollmentRow) => String(row.id),
        manualPagination: true,
        manualSorting: true,
        pageCount: Math.max(1, pagination?.last_page ?? 1),
        enableRowSelection: true,
        state: {
            sorting,
            pagination: paginationState,
            rowSelection,
        },
        onRowSelectionChange: setRowSelection,
        onPaginationChange: (updater) => {
            const next = typeof updater === "function" ? updater(paginationState) : updater;
            onPaginationChange(next.pageIndex + 1, next.pageSize);
        },
        onSortingChange: (updater) => {
            const next = typeof updater === "function" ? updater(sorting) : updater;
            if (next.length > 0) {
                onSortingChange(next[0].id, next[0].desc ? "desc" : "asc");
            } else {
                onSortingChange("created_at", "desc");
            }
        },
    });

    const selectedRows = useMemo(() => {
        const selectedIds = new Set(Object.keys(rowSelection).filter((key) => rowSelection[key]));
        return data.filter((row) => selectedIds.has(String(row.id)));
    }, [data, rowSelection]);

    const eligibleBulkAdvanceCount = useMemo(
        () =>
            selectedRows.filter((row) => {
                if (row.is_trashed || row.terminal_outcome === "cancelled" || !row.can_advance) return false;
                if (row.workflow_runtime === "policy_v1" && row.allowed_transitions) {
                    const eligible = row.allowed_transitions.filter((t) => !t.needs_payment && !t.requires_reason);
                    return eligible.length === 1;
                }

                return Boolean(row.next_step && row.next_step.action_type !== "cashier_verification");
            }).length,
        [selectedRows],
    );

    const hasSelectedTrashed = selectedRows.some((row) => row.is_trashed);
    const hasSelectedActive = selectedRows.some((row) => !row.is_trashed);

    return (
        <DataGrid
            table={table}
            recordCount={pagination?.total ?? data.length}
            isLoading={isLoading}
            onRowClick={(row) => onInspect(row)}
            emptyMessage="No enrollments match your current search or stage filter."
            tableLayout={{
                dense: isDense,
                cellBorder: false,
                rowBorder: true,
                headerBackground: true,
                headerBorder: true,
                width: "auto",
            }}
        >
            <div className="space-y-3">
                {/* Quiet Column Visibility + Selection Hint Strip */}
                <div className="flex items-center justify-between gap-2 px-1">
                    <p className="text-muted-foreground text-xs tabular-nums">
                        {pagination?.total
                            ? `Showing ${pagination.from ?? 0}–${pagination.to ?? data.length} of ${pagination.total.toLocaleString()} records`
                            : "No matching records"}
                    </p>
                    <DataGridColumnVisibility
                        table={table}
                        trigger={
                            <Button variant="ghost" size="xs" className="text-muted-foreground hover:text-foreground h-7 gap-1.5 text-xs">
                                <Settings2 className="size-3.5" aria-hidden="true" />
                                Columns
                            </Button>
                        }
                    />
                </div>

                {/* ReUI DataGrid Table */}
                <DataGridContainer className="border-border/70 bg-background/60">
                    <DataGridScrollArea>
                        <DataGridTable />
                    </DataGridScrollArea>
                </DataGridContainer>

                {/* Floating Bulk Action Dock */}
                {selectedRows.length > 0 && (
                    <div className="border-border bg-foreground text-background sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-2.5 shadow-lg">
                        <div className="flex items-center gap-2.5 text-xs font-medium">
                            <span className="bg-background text-foreground inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 font-semibold tabular-nums">
                                {selectedRows.length}
                            </span>
                            <span>enrollment{selectedRows.length === 1 ? "" : "s"} selected</span>
                        </div>

                        <div className="flex flex-wrap items-center gap-1.5">
                            {eligibleBulkAdvanceCount > 0 && (
                                <Button
                                    type="button"
                                    size="xs"
                                    disabled={bulkBusy}
                                    onClick={() => onBulkAction("advance", selectedRows)}
                                    className="bg-background text-foreground hover:bg-background/90 h-7 gap-1.5 text-xs"
                                >
                                    {bulkBusy ? <Loader2 className="size-3 animate-spin" /> : <ArrowRight className="size-3" />}
                                    Advance ({eligibleBulkAdvanceCount})
                                </Button>
                            )}
                            {hasSelectedActive && (
                                <Button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    disabled={bulkBusy}
                                    onClick={() => onBulkAction("resend_assessment", selectedRows)}
                                    className="text-background hover:bg-background/15 hover:text-background h-7 gap-1.5 text-xs"
                                >
                                    <Send className="size-3" />
                                    Resend assessment
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="xs"
                                variant="ghost"
                                onClick={() => {
                                    exportEnrollmentsToCsv(selectedRows, `enrollments-selected-${new Date().toISOString().slice(0, 10)}.csv`);
                                    toast.success(`Exported ${selectedRows.length} enrollment${selectedRows.length === 1 ? "" : "s"} to CSV.`);
                                }}
                                className="text-background hover:bg-background/15 hover:text-background h-7 gap-1.5 text-xs"
                            >
                                <Download className="size-3" />
                                Export CSV
                            </Button>
                            {hasSelectedTrashed && (
                                <Button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    disabled={bulkBusy}
                                    onClick={() => onBulkAction("restore", selectedRows)}
                                    className="hover:bg-background/15 h-7 gap-1.5 text-xs text-emerald-300 hover:text-emerald-200"
                                >
                                    <RotateCcw className="size-3" />
                                    Restore
                                </Button>
                            )}
                            {hasSelectedActive && (
                                <Button
                                    type="button"
                                    size="xs"
                                    variant="ghost"
                                    disabled={bulkBusy}
                                    onClick={() => onBulkAction("delete", selectedRows)}
                                    className="hover:bg-background/15 h-7 gap-1.5 text-xs text-rose-300 hover:text-rose-200"
                                >
                                    <Trash2 className="size-3" />
                                    Trash
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="icon-xs"
                                variant="ghost"
                                onClick={() => setRowSelection({})}
                                className="text-background/70 hover:bg-background/15 hover:text-background h-7 w-7"
                                title="Clear selection"
                            >
                                <X className="size-3.5" />
                                <span className="sr-only">Clear selection</span>
                            </Button>
                        </div>
                    </div>
                )}

                {/* ReUI DataGrid Pagination */}
                <DataGridPagination sizes={[10, 20, 50, 100]} className="px-1" />
            </div>
        </DataGrid>
    );
}
