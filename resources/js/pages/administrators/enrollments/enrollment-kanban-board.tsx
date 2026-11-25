import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import {
    Kanban,
    KanbanBoard,
    KanbanColumn,
    KanbanColumnContent,
    KanbanItem,
    KanbanItemHandle,
    KanbanOverlay,
    type KanbanMoveEvent,
} from "@/components/reui/kanban";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ArrowRight, BookOpen, CheckCircle2, ChevronDown, ChevronUp, CreditCard, GripVertical, Sparkles } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import type { EnrollmentRow, EnrollmentTransitionOption } from "./columns";
import { formatMoney, getInitials, resolvePipelineColor, tuitionSettlementPercent } from "./enrollment-utils";
import type { EnrollmentPipeline, EnrollmentWorkspacePreferences } from "./types";

type LaneDefinition = {
    key: string;
    stepKey: string;
    status: string;
    label: string;
    color: string;
    actionType: string;
    isCompletion: boolean;
    matchStatuses: string[];
};

type EnrollmentKanbanBoardProps = {
    enrollments: EnrollmentRow[];
    pipeline: EnrollmentPipeline;
    currency: string;
    preferences?: Pick<EnrollmentWorkspacePreferences, "collapseCompletedLane" | "completedPreviewLimit" | "showTuitionBars" | "density">;
    onInspect: (enrollment: EnrollmentRow, options?: { openCashier?: boolean; transitionKey?: string }) => void;
    onQuickAdvance: (enrollment: EnrollmentRow, transition?: EnrollmentTransitionOption) => void;
    onSwitchToTableForStatus: (status: string) => void;
};

export function EnrollmentKanbanBoard({
    enrollments,
    pipeline,
    currency,
    preferences,
    onInspect,
    onQuickAdvance,
    onSwitchToTableForStatus,
}: EnrollmentKanbanBoardProps) {
    const defaultCollapsed = preferences?.collapseCompletedLane ?? true;
    const previewLimit = preferences?.completedPreviewLimit ?? 4;
    const showTuitionBars = preferences?.showTuitionBars ?? true;
    const isCompact = preferences?.density === "compact";

    const [completedExpanded, setCompletedExpanded] = useState(!defaultCollapsed);

    useEffect(() => {
        setCompletedExpanded(!defaultCollapsed);
    }, [defaultCollapsed]);

    const lanes = useMemo<LaneDefinition[]>(() => {
        const result: LaneDefinition[] = [];
        const seenKeys = new Set<string>();

        for (const step of pipeline.steps) {
            const laneKey = step.status || step.key;
            if (!laneKey || seenKeys.has(laneKey)) continue;
            seenKeys.add(laneKey);

            const isCompletion = Boolean(step.is_completion) || step.status === pipeline.cashier_verified_status;
            const matchStatuses = [step.status];
            if (isCompletion) {
                matchStatuses.push("Enrolled", "Completed");
            }

            result.push({
                key: laneKey,
                stepKey: step.key || laneKey,
                status: step.status,
                label: step.label || step.status,
                color: step.color || (isCompletion ? "green" : "blue"),
                actionType: step.action_type ?? "standard",
                isCompletion,
                matchStatuses: Array.from(new Set(matchStatuses)),
            });
        }

        if (!result.some((lane) => lane.isCompletion)) {
            result.push({
                key: "Enrolled",
                stepKey: "enrolled",
                status: "Enrolled",
                label: "Enrolled",
                color: "green",
                actionType: "standard",
                isCompletion: true,
                matchStatuses: ["Enrolled", "Completed"],
            });
        }

        return result;
    }, [pipeline.steps, pipeline.cashier_verified_status]);

    const groupedFromProps = useMemo<Record<string, EnrollmentRow[]>>(() => {
        const record: Record<string, EnrollmentRow[]> = {};
        for (const lane of lanes) {
            record[lane.key] = [];
        }

        const fallbackLaneKey = lanes[0]?.key ?? "Enrolled";

        for (const row of enrollments) {
            if (row.is_trashed || row.terminal_outcome === "cancelled") continue;

            // Match policy_v1 enrollments by current_step_key first, then fallback to status matching
            const targetLane =
                (row.current_step_key ? lanes.find((lane) => lane.stepKey === row.current_step_key) : undefined) ??
                lanes.find((lane) => row.status && lane.matchStatuses.includes(row.status)) ??
                lanes.find((lane) => lane.key === fallbackLaneKey);

            if (targetLane) {
                record[targetLane.key].push(row);
            }
        }

        return record;
    }, [enrollments, lanes]);

    const [columns, setColumns] = useState<Record<string, EnrollmentRow[]>>(groupedFromProps);

    useEffect(() => {
        setColumns(groupedFromProps);
    }, [groupedFromProps]);

    const handleMove = (event: KanbanMoveEvent) => {
        const { activeContainer, overContainer, activeIndex } = event;
        if (activeContainer === overContainer) return;

        const sourceLaneIndex = lanes.findIndex((lane) => lane.key === activeContainer);
        const targetLaneIndex = lanes.findIndex((lane) => lane.key === overContainer);
        const movedRow = columns[activeContainer]?.[activeIndex];

        if (!movedRow || sourceLaneIndex === -1 || targetLaneIndex === -1) {
            setColumns(groupedFromProps);
            return;
        }

        const targetLane = lanes[targetLaneIndex];

        // Dynamic policy_v1: allow dragging to ANY target lane that matches an allowed_transition
        const policyTransitions = movedRow.allowed_transitions ?? [];
        if (movedRow.workflow_runtime === "policy_v1" && policyTransitions.length > 0) {
            const matchedTransition = policyTransitions.find((t) => t.to === targetLane.stepKey || targetLane.matchStatuses.includes(t.to_status));

            if (!matchedTransition) {
                setColumns(groupedFromProps);
                toast.info("This policy does not permit moving directly to that stage from the current step.");
                return;
            }

            if (matchedTransition.needs_payment || matchedTransition.requires_reason) {
                setColumns(groupedFromProps);
                onInspect(movedRow, {
                    openCashier: matchedTransition.needs_payment,
                    transitionKey: matchedTransition.key,
                });
                return;
            }

            setColumns((prev) => {
                const next = { ...prev };
                const src = [...(next[activeContainer] ?? [])];
                const [item] = src.splice(activeIndex, 1);
                if (!item) return prev;
                next[activeContainer] = src;
                next[overContainer] = [item, ...(next[overContainer] ?? [])];
                return next;
            });

            onQuickAdvance(movedRow, matchedTransition);
            return;
        }

        // Legacy sequential pipeline: only allow advancing to immediate next stage via drag
        if (targetLaneIndex !== sourceLaneIndex + 1) {
            setColumns(groupedFromProps);
            toast.info(
                targetLaneIndex < sourceLaneIndex
                    ? "Open the student's dossier sheet to revert a verified step."
                    : "Enrollments move one pipeline stage at a time so each verification is recorded.",
            );
            return;
        }

        if (!movedRow.can_advance) {
            setColumns(groupedFromProps);
            toast.error("You do not have permission to advance this enrollment to that stage.");
            return;
        }

        if (targetLane.actionType === "cashier_verification" || movedRow.next_step?.action_type === "cashier_verification") {
            setColumns(groupedFromProps);
            onInspect(movedRow, { openCashier: true });
            return;
        }

        setColumns((prev) => {
            const next = { ...prev };
            const src = [...(next[activeContainer] ?? [])];
            const [item] = src.splice(activeIndex, 1);
            if (!item) return prev;
            next[activeContainer] = src;
            next[overContainer] = [item, ...(next[overContainer] ?? [])];
            return next;
        });

        onQuickAdvance(movedRow);
    };

    const renderCard = (row: EnrollmentRow, isOverlay = false) => {
        const balance = row.tuition?.balance ?? 0;
        const overall = row.tuition?.overall ?? null;
        const settlementPct = tuitionSettlementPercent(overall, balance);
        const primaryTransition = row.allowed_transitions?.[0];
        const nextNeedsCashier = primaryTransition?.needs_payment || row.next_step?.action_type === "cashier_verification";
        const hasMultipleBranches = (row.allowed_transitions?.length ?? 0) > 1;
        const needsReason = Boolean(primaryTransition?.requires_reason || row.next_step?.requires_reason);
        const canMove = Boolean(row.can_advance && (row.next_step || primaryTransition));

        return (
            <div
                className={cn(
                    "group/card border-border/70 bg-card hover:border-foreground/20 relative flex flex-col rounded-lg border transition-colors",
                    isCompact ? "gap-1.5 p-2.5" : "gap-2.5 p-3",
                    isOverlay && "ring-primary/30 shadow-lg ring-2",
                )}
            >
                <div className="flex items-start justify-between gap-2">
                    <button
                        type="button"
                        onClick={() => onInspect(row)}
                        className="flex min-w-0 flex-1 items-start gap-2.5 text-left focus-visible:outline-none"
                    >
                        <Avatar className={cn("border-border/70 mt-0.5 shrink-0 border", isCompact ? "size-6" : "size-7")}>
                            <AvatarFallback className="bg-muted text-[10px] font-semibold">{getInitials(row.student_name)}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="text-foreground group-hover/card:text-primary truncate text-[13px] font-semibold transition-colors">
                                {row.student_name ?? "Unknown Student"}
                            </p>
                            <p className="text-muted-foreground truncate font-mono text-[11px]">ID {row.student_number ?? row.student_id ?? "—"}</p>
                        </div>
                    </button>

                    {canMove && !isOverlay && (
                        <KanbanItemHandle className="text-muted-foreground/60 hover:text-foreground -mr-1 rounded p-1">
                            <GripVertical className="size-3.5" aria-hidden="true" />
                        </KanbanItemHandle>
                    )}
                </div>

                {/* Program + subjects line */}
                <div className="flex flex-wrap items-center justify-between gap-1.5 text-[11px]">
                    <div className="flex items-center gap-1.5">
                        <Badge variant="outline" size="sm" className="font-medium">
                            {row.course ?? "Unassigned"}
                        </Badge>
                        {row.department && <span className="text-muted-foreground">{row.department}</span>}
                        {row.academic_year && <span className="text-muted-foreground">· Yr {row.academic_year}</span>}
                    </div>
                    <span className="text-muted-foreground inline-flex items-center gap-1 tabular-nums">
                        <BookOpen className="size-3" aria-hidden="true" />
                        {row.subjects_count}
                    </span>
                </div>

                {/* Tuition micro-bar */}
                <div className="space-y-1.5 border-t pt-2">
                    <div className="flex items-center justify-between gap-2 text-[11px] tabular-nums">
                        <span className="text-muted-foreground">{formatMoney(overall, currency)}</span>
                        <span
                            className={cn(
                                "font-semibold",
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
                                style={{ width: `${settlementPct}%` }}
                            />
                        </div>
                    )}
                </div>

                {/* Contextual bottom micro-bar */}
                <div className="flex items-center justify-between gap-2 pt-0.5">
                    <button
                        type="button"
                        onClick={() => onInspect(row)}
                        className="text-muted-foreground hover:text-foreground text-[11px] font-medium transition-colors"
                    >
                        Inspect dossier
                    </button>
                    {canMove && !isOverlay && (
                        <Button
                            type="button"
                            size="xs"
                            variant={nextNeedsCashier ? "outline" : "secondary"}
                            className="h-6 gap-1 px-2 text-[11px]"
                            onClick={(e) => {
                                e.stopPropagation();
                                if (hasMultipleBranches || needsReason || nextNeedsCashier) {
                                    onInspect(row, {
                                        openCashier: nextNeedsCashier,
                                        transitionKey: primaryTransition?.key,
                                    });
                                } else {
                                    onQuickAdvance(row, primaryTransition);
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
                                    Cashier
                                </>
                            ) : (
                                <>
                                    Advance
                                    <ArrowRight className="size-3" />
                                </>
                            )}
                        </Button>
                    )}
                </div>
            </div>
        );
    };

    const rowLookup = useMemo(() => {
        const map = new Map<string, EnrollmentRow>();
        for (const list of Object.values(columns)) {
            for (const item of list) {
                map.set(String(item.id), item);
            }
        }
        return map;
    }, [columns]);

    const isWideBoard = lanes.length > 4;

    return (
        <div className={cn(isWideBoard && "overflow-x-auto pb-2")}>
            <Kanban value={columns} onValueChange={setColumns} getItemValue={(item) => String(item.id)} onMove={handleMove}>
                <KanbanBoard
                    className={cn(
                        "gap-3",
                        isWideBoard
                            ? "flex flex-nowrap"
                            : lanes.length <= 2
                              ? "grid-cols-1 md:grid-cols-2"
                              : lanes.length === 3
                                ? "grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
                                : "grid-cols-1 md:grid-cols-2 xl:grid-cols-4",
                    )}
                >
                    {lanes.map((lane) => {
                        const items = columns[lane.key] ?? [];
                        const tone = resolvePipelineColor(lane.color);
                        const isCompactCompleted = lane.isCompletion && !completedExpanded;
                        const visibleItems = isCompactCompleted ? items.slice(0, previewLimit) : items;
                        const hiddenCount = Math.max(0, items.length - visibleItems.length);

                        return (
                            <KanbanColumn key={lane.key} value={lane.key} disabled className={cn("min-w-0", isWideBoard && "w-[300px] shrink-0")}>
                                <Frame spacing="xs" className="h-full">
                                    <div className="flex items-center justify-between gap-2 px-2.5 py-2">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <span className={cn("size-2 shrink-0 rounded-full", tone.dot)} aria-hidden="true" />
                                            <span className="truncate text-xs font-semibold" title={lane.label}>
                                                {lane.label}
                                            </span>
                                            <Badge variant="outline" size="xs" radius="full" className="tabular-nums">
                                                {items.length}
                                            </Badge>
                                        </div>
                                        {lane.isCompletion && items.length > previewLimit && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="xs"
                                                className="text-muted-foreground h-6 gap-1 px-1.5 text-[11px]"
                                                onClick={() => setCompletedExpanded((prev) => !prev)}
                                            >
                                                {completedExpanded ? (
                                                    <>
                                                        Compact <ChevronUp className="size-3" />
                                                    </>
                                                ) : (
                                                    <>
                                                        All <ChevronDown className="size-3" />
                                                    </>
                                                )}
                                            </Button>
                                        )}
                                    </div>

                                    <FramePanel className="bg-background/60 flex min-h-[240px] flex-1 flex-col gap-2 p-2">
                                        {lane.isCompletion && items.length > 0 && !completedExpanded && (
                                            <div className="border-border/60 bg-card/70 flex items-center justify-between gap-2 rounded-md border px-2.5 py-2">
                                                <div className="flex items-center gap-2">
                                                    <CheckCircle2 className="size-4 text-emerald-500" aria-hidden="true" />
                                                    <div>
                                                        <p className="text-xs font-semibold tabular-nums">{items.length} completed</p>
                                                        <p className="text-muted-foreground text-[10.5px]">Latest {previewLimit} shown</p>
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="xs"
                                                    className="h-6 text-[11px]"
                                                    onClick={() => onSwitchToTableForStatus(lane.status)}
                                                >
                                                    Table view
                                                </Button>
                                            </div>
                                        )}

                                        <KanbanColumnContent value={lane.key} className="flex-1 gap-2">
                                            {visibleItems.length === 0 ? (
                                                <div className="border-border/60 text-muted-foreground flex flex-1 flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed px-3 py-8 text-center text-xs">
                                                    <Sparkles className="size-4 opacity-40" aria-hidden="true" />
                                                    <span>Queue clear</span>
                                                </div>
                                            ) : (
                                                visibleItems.map((row) => (
                                                    <KanbanItem key={row.id} value={String(row.id)} disabled={!row.can_advance}>
                                                        {renderCard(row)}
                                                    </KanbanItem>
                                                ))
                                            )}
                                        </KanbanColumnContent>

                                        {hiddenCount > 0 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="xs"
                                                className="text-muted-foreground hover:text-foreground w-full justify-center text-xs"
                                                onClick={() => setCompletedExpanded(true)}
                                            >
                                                Show {hiddenCount} more completed
                                            </Button>
                                        )}
                                    </FramePanel>
                                </Frame>
                            </KanbanColumn>
                        );
                    })}
                </KanbanBoard>

                <KanbanOverlay>
                    {({ value }) => {
                        const row = rowLookup.get(String(value));
                        return row ? renderCard(row, true) : null;
                    }}
                </KanbanOverlay>
            </Kanban>
        </div>
    );
}
