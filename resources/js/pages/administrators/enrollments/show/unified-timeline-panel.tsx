import { Badge, type BadgeProps } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import {
    Timeline,
    TimelineContent,
    TimelineDate,
    TimelineHeader,
    TimelineIndicator,
    TimelineItem,
    TimelineSeparator,
    TimelineTitle,
} from "@/components/reui/timeline";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Tabs, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import {
    ArrowLeftRight,
    Clock,
    Printer,
    Repeat,
    User,
} from "lucide-react";
import { formatMoney, formatShortDate } from "../enrollment-utils";
import { AuditHistoryPanel } from "../workbench/audit-history-panel";
import type { EnrollmentData, EnrollmentTimelineEvent } from "./types";

export interface UnifiedTimelinePanelProps {
    enrollment: EnrollmentData;
    currency: string;
    onOpenLoadChange: () => void;
    onOpenTransfer: () => void;
}

function resolveTonePresentation(tone: EnrollmentTimelineEvent["tone"]): {
    badge: BadgeProps["variant"];
    indicator: string;
} {
    switch (tone) {
        case "success":
            return {
                badge: "success-light",
                indicator: "border-emerald-500 bg-emerald-500/20",
            };
        case "warning":
            return {
                badge: "warning-light",
                indicator: "border-amber-500 bg-amber-500/20",
            };
        case "destructive":
            return {
                badge: "destructive-light",
                indicator: "border-destructive bg-destructive/20",
            };
        default:
            return {
                badge: "info-light",
                indicator: "border-primary bg-primary/20",
            };
    }
}

function resolveStatusBadge(status: string): BadgeProps["variant"] {
    const normalized = status.toLowerCase();
    if (normalized.includes("approv") || normalized.includes("complet")) {
        return "success-light";
    }
    if (normalized.includes("pend")) {
        return "warning-light";
    }
    if (normalized.includes("reject") || normalized.includes("cancel")) {
        return "destructive-light";
    }
    return "secondary";
}

function DossierTimelineFrame({ enrollment }: { enrollment: EnrollmentData }) {
    return (
        <Frame>
            <FrameHeader className="flex-row items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                    <IconTile
                        variant="soft"
                        size="sm"
                        className="text-primary"
                    >
                        <Clock />
                    </IconTile>
                    <div className="min-w-0">
                        <FrameTitle>Chronological Dossier Timeline</FrameTitle>
                        <FrameDescription>
                            End-to-end record of creation, pipeline transitions,
                            payments, and load activity.
                        </FrameDescription>
                    </div>
                </div>
                <Badge variant="outline" className="tabular-nums">
                    {enrollment.timeline.length}{" "}
                    {enrollment.timeline.length === 1 ? "Event" : "Events"}
                </Badge>
            </FrameHeader>
            <FramePanel>
                {enrollment.timeline.length === 0 ? (
                    <div className="rounded-lg border border-dashed py-8 text-center text-xs text-muted-foreground">
                        No timeline events recorded yet.
                    </div>
                ) : (
                    <Timeline value={enrollment.timeline.length} className="pt-1">
                        {enrollment.timeline.map((event, index) => {
                            const toneStyle = resolveTonePresentation(
                                event.tone,
                            );
                            return (
                                <TimelineItem
                                    key={event.id}
                                    step={index + 1}
                                    className="not-last:pb-5"
                                >
                                    <TimelineHeader>
                                        <TimelineSeparator />
                                        <TimelineIndicator
                                            className={toneStyle.indicator}
                                        />
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <TimelineTitle>
                                                {event.title}
                                            </TimelineTitle>
                                            <Badge
                                                size="xs"
                                                variant={toneStyle.badge}
                                                className="capitalize"
                                            >
                                                {event.type.replaceAll(
                                                    "_",
                                                    " ",
                                                )}
                                            </Badge>
                                        </div>
                                        <TimelineDate className="flex flex-wrap items-center gap-2">
                                            <span>
                                                {event.date_label ||
                                                    formatShortDate(
                                                        event.timestamp,
                                                    )}
                                            </span>
                                            {event.actor && (
                                                <span className="inline-flex items-center gap-1">
                                                    <User className="size-3" />
                                                    {event.actor}
                                                </span>
                                            )}
                                        </TimelineDate>
                                    </TimelineHeader>
                                    {event.description && (
                                        <TimelineContent>
                                            <p className="text-xs leading-relaxed text-muted-foreground">
                                                {event.description}
                                            </p>
                                        </TimelineContent>
                                    )}
                                </TimelineItem>
                            );
                        })}
                    </Timeline>
                )}
            </FramePanel>
        </Frame>
    );
}

function LoadChangesAndTransfersFrame({
    enrollment,
    currency,
    onOpenLoadChange,
    onOpenTransfer,
}: UnifiedTimelinePanelProps) {
    const loadChanges = enrollment.load_changes_summary.recent;
    const transfers = enrollment.transfers_summary.recent;
    const canModify =
        !enrollment.is_trashed && !enrollment.workflow.is_terminal_locked;

    return (
        <Frame>
            <FrameHeader className="flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2.5">
                    <IconTile
                        variant="soft"
                        size="sm"
                        className="text-primary"
                    >
                        <ArrowLeftRight />
                    </IconTile>
                    <div className="min-w-0">
                        <FrameTitle>
                            Change-of-Load (COL) & Transfer Records
                        </FrameTitle>
                        <FrameDescription>
                            Add/drop/swap slips ({enrollment.load_changes_summary.total_count})
                            and course or section transfers (
                            {enrollment.transfers_summary.total_count}).
                        </FrameDescription>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="xs"
                        variant="outline"
                        onClick={onOpenLoadChange}
                        disabled={!canModify}
                    >
                        <ArrowLeftRight className="size-3" />
                        Open COL Dialog
                    </Button>
                    <Button
                        type="button"
                        size="xs"
                        variant="outline"
                        onClick={onOpenTransfer}
                        disabled={!canModify}
                    >
                        <Repeat className="size-3" />
                        Open Transfer Dialog
                    </Button>
                </div>
            </FrameHeader>

            {/* Change of Load Records */}
            <FramePanel className="space-y-2.5">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Change-of-Load (COL) Requests
                    </p>
                    {enrollment.load_changes_summary.pending_count > 0 && (
                        <Badge
                            size="xs"
                            variant="warning-light"
                            className="tabular-nums"
                        >
                            {enrollment.load_changes_summary.pending_count}{" "}
                            Pending
                        </Badge>
                    )}
                </div>

                {loadChanges.length === 0 ? (
                    <div className="rounded-lg border border-dashed py-6 text-center text-xs text-muted-foreground">
                        No Change-of-Load records on file.
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-3">
                                        Reference
                                    </TableHead>
                                    <TableHead>Change Details</TableHead>
                                    <TableHead>Delta</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="pr-3 text-right">
                                        Slip
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {loadChanges.map((col) => (
                                    <TableRow key={col.id}>
                                        <TableCell className="pl-3 align-top">
                                            <p className="font-mono text-xs font-semibold">
                                                {col.reference_number}
                                            </p>
                                            <p className="text-[11px] text-muted-foreground">
                                                {formatShortDate(col.created_at)}
                                            </p>
                                        </TableCell>
                                        <TableCell className="align-top">
                                            <div className="space-y-0.5 text-xs">
                                                <Badge
                                                    size="xs"
                                                    variant="outline"
                                                    className="capitalize"
                                                >
                                                    {col.change_type.replaceAll(
                                                        "_",
                                                        " ",
                                                    )}
                                                </Badge>
                                                {col.added_subject_code && (
                                                    <p className="text-emerald-700 dark:text-emerald-400">
                                                        +{" "}
                                                        {col.added_subject_code}
                                                        {col.added_subject_title
                                                            ? ` (${col.added_subject_title})`
                                                            : ""}
                                                    </p>
                                                )}
                                                {col.dropped_subject_code && (
                                                    <p className="text-destructive">
                                                        −{" "}
                                                        {
                                                            col.dropped_subject_code
                                                        }
                                                        {col.dropped_subject_title
                                                            ? ` (${col.dropped_subject_title})`
                                                            : ""}
                                                    </p>
                                                )}
                                                {col.reason && (
                                                    <p className="text-[11px] text-muted-foreground">
                                                        {col.reason}
                                                    </p>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="align-top text-xs tabular-nums">
                                            <p>
                                                {col.units_delta > 0 ? "+" : ""}
                                                {col.units_delta} units
                                            </p>
                                            <p
                                                className={cn(
                                                    "text-[11px]",
                                                    col.tuition_delta > 0
                                                        ? "text-amber-600 dark:text-amber-400"
                                                        : col.tuition_delta < 0
                                                          ? "text-emerald-600 dark:text-emerald-400"
                                                          : "text-muted-foreground",
                                                )}
                                            >
                                                {col.tuition_delta > 0
                                                    ? "+"
                                                    : ""}
                                                {formatMoney(
                                                    col.tuition_delta,
                                                    currency,
                                                )}
                                            </p>
                                        </TableCell>
                                        <TableCell className="align-top">
                                            <Badge
                                                size="xs"
                                                variant={resolveStatusBadge(
                                                    col.status,
                                                )}
                                                className="capitalize"
                                            >
                                                {col.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="pr-3 text-right align-top">
                                            <Button
                                                size="xs"
                                                variant="outline"
                                                nativeButton={false}
                                                render={
                                                    <a
                                                        href={col.slip_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    />
                                                }
                                            >
                                                <Printer className="size-3" />
                                                Print COL Slip
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </FramePanel>

            {/* Transfer Records */}
            <FramePanel className="space-y-2.5">
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    Course & Section Transfer History
                </p>

                {transfers.length === 0 ? (
                    <div className="rounded-lg border border-dashed py-6 text-center text-xs text-muted-foreground">
                        No course shifting or section transfer records on file.
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-3">Type</TableHead>
                                    <TableHead>From → To</TableHead>
                                    <TableHead>Impact</TableHead>
                                    <TableHead className="pr-3">
                                        Performed By
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transfers.map((tr) => (
                                    <TableRow key={tr.id}>
                                        <TableCell className="pl-3 align-top">
                                            <Badge
                                                size="xs"
                                                variant="info-light"
                                                className="capitalize"
                                            >
                                                {tr.transfer_type.replaceAll(
                                                    "_",
                                                    " ",
                                                )}
                                            </Badge>
                                            <p className="mt-1 text-[11px] text-muted-foreground">
                                                {formatShortDate(tr.created_at)}
                                            </p>
                                        </TableCell>
                                        <TableCell className="align-top text-xs">
                                            {(tr.from_course ||
                                                tr.to_course) && (
                                                <p className="font-medium text-foreground">
                                                    {tr.from_course ?? "—"} →{" "}
                                                    {tr.to_course ?? "—"}
                                                </p>
                                            )}
                                            {(tr.from_section ||
                                                tr.to_section) && (
                                                <p className="text-[11px] text-muted-foreground">
                                                    Section{" "}
                                                    {tr.from_section ?? "—"} →{" "}
                                                    {tr.to_section ?? "—"}
                                                </p>
                                            )}
                                            {tr.reason && (
                                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                                    {tr.reason}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="align-top text-xs tabular-nums">
                                            <p>
                                                {tr.reassigned_classes_count}{" "}
                                                reassigned ·{" "}
                                                {tr.credited_subjects_count}{" "}
                                                credited
                                            </p>
                                            <p className="text-[11px] text-muted-foreground">
                                                Fee delta:{" "}
                                                {tr.fee_delta > 0 ? "+" : ""}
                                                {formatMoney(
                                                    tr.fee_delta,
                                                    currency,
                                                )}
                                            </p>
                                        </TableCell>
                                        <TableCell className="pr-3 align-top text-xs text-muted-foreground">
                                            {tr.performed_by}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </FramePanel>
        </Frame>
    );
}

export function UnifiedTimelinePanel({
    enrollment,
    currency,
    onOpenLoadChange,
    onOpenTransfer,
}: UnifiedTimelinePanelProps) {
    const formatCurrency = (amount: number) => formatMoney(amount, currency);

    return (
        <div className="min-w-0 space-y-4">
            <Tabs defaultValue="all" className="min-w-0">
                <div className="overflow-x-auto pb-1">
                    <TabsList>
                        <TabsTab value="all">All Activity</TabsTab>
                        <TabsTab value="timeline">
                            Dossier Timeline ({enrollment.timeline.length})
                        </TabsTab>
                        <TabsTab value="audit">
                            Revision Audit ({enrollment.edit_history.length})
                        </TabsTab>
                        <TabsTab value="col">
                            COL & Transfers (
                            {enrollment.load_changes_summary.total_count +
                                enrollment.transfers_summary.total_count}
                            )
                        </TabsTab>
                    </TabsList>
                </div>

                <TabsPanel value="all" className="space-y-4 pt-2">
                    <DossierTimelineFrame enrollment={enrollment} />
                    <AuditHistoryPanel
                        history={enrollment.edit_history}
                        formatCurrency={formatCurrency}
                    />
                    <LoadChangesAndTransfersFrame
                        enrollment={enrollment}
                        currency={currency}
                        onOpenLoadChange={onOpenLoadChange}
                        onOpenTransfer={onOpenTransfer}
                    />
                </TabsPanel>

                <TabsPanel value="timeline" className="pt-2">
                    <DossierTimelineFrame enrollment={enrollment} />
                </TabsPanel>

                <TabsPanel value="audit" className="pt-2">
                    <AuditHistoryPanel
                        history={enrollment.edit_history}
                        formatCurrency={formatCurrency}
                    />
                </TabsPanel>

                <TabsPanel value="col" className="pt-2">
                    <LoadChangesAndTransfersFrame
                        enrollment={enrollment}
                        currency={currency}
                        onOpenLoadChange={onOpenLoadChange}
                        onOpenTransfer={onOpenTransfer}
                    />
                </TabsPanel>
            </Tabs>
        </div>
    );
}
