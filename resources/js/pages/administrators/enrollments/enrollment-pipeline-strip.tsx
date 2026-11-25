import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { ArrowUpRight, Ban, CircleDollarSign, Trash2 } from "lucide-react";
import { useMemo } from "react";
import { route } from "ziggy-js";
import { formatCompactMoney, resolvePipelineColor } from "./enrollment-utils";
import type { EnrollmentManagementProps } from "./types";

type PipelineStageBucket = {
    value: string;
    label: string;
    count: number;
    color: string;
    isCompletion: boolean;
};

type EnrollmentPipelineStripProps = {
    analytics: EnrollmentManagementProps["analytics"];
    pipeline: EnrollmentManagementProps["enrollment_pipeline"];
    applicantsCount: number;
    activeStatusFilter: string;
    activePaymentFilter: string;
    currency: string;
    onStatusSelect: (status: string) => void;
    onPaymentToggle: (mode: "all" | "unpaid" | "paid") => void;
};

export function EnrollmentPipelineStrip({
    analytics,
    pipeline,
    applicantsCount,
    activeStatusFilter,
    activePaymentFilter,
    currency,
    onStatusSelect,
    onPaymentToggle,
}: EnrollmentPipelineStripProps) {
    const statusCountMap = useMemo(() => {
        const map = new Map<string, number>();
        for (const item of analytics?.by_status ?? []) {
            if (!item.status) continue;
            map.set(item.status, (map.get(item.status) ?? 0) + Number(item.count));
        }
        return map;
    }, [analytics?.by_status]);

    const stages = useMemo<PipelineStageBucket[]>(() => {
        const seen = new Set<string>();
        const list: PipelineStageBucket[] = [];

        for (const step of pipeline.steps) {
            if (!step.status || seen.has(step.status)) continue;
            seen.add(step.status);
            list.push({
                value: step.status,
                label: step.status,
                count: statusCountMap.get(step.status) ?? 0,
                color: step.color || (step.is_completion ? "green" : "blue"),
                isCompletion: Boolean(step.is_completion),
            });
        }

        // Include any active DB statuses (e.g. "Enrolled" or legacy statuses) not listed in pipeline.steps
        for (const [status, count] of statusCountMap.entries()) {
            if (seen.has(status) || count <= 0) continue;
            seen.add(status);
            const isCompleted = status === "Enrolled" || status === "Completed" || status === pipeline.cashier_verified_status;
            list.push({
                value: status,
                label: status,
                count,
                color: isCompleted ? "green" : /pending/i.test(status) ? "amber" : "blue",
                isCompletion: isCompleted,
            });
        }

        return list;
    }, [pipeline.steps, pipeline.cashier_verified_status, statusCountMap]);

    const totalAcrossStages = useMemo(() => stages.reduce((sum, stage) => sum + stage.count, 0), [stages]);
    const totalActive = analytics?.active_count ?? totalAcrossStages;
    const trashedCount = analytics?.trashed_count ?? 0;
    const completedCount = analytics?.completed_count ?? stages.filter((stage) => stage.isCompletion).reduce((sum, stage) => sum + stage.count, 0);
    const inPipelineCount =
        analytics?.in_pipeline_count ?? stages.filter((stage) => !stage.isCompletion).reduce((sum, stage) => sum + stage.count, 0);
    const completionRate = totalActive > 0 ? Math.min(100, Math.round((completedCount / totalActive) * 100)) : 0;
    const unpaidCount = analytics?.unpaid_count ?? 0;
    const unpaidTotal = analytics?.unpaid_balance_total ?? 0;

    return (
        <Frame spacing="sm" className="w-full">
            <FramePanel className="space-y-3.5">
                {/* Top summary hairline */}
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                        <span className="text-muted-foreground font-semibold tracking-[0.08em] uppercase">Term pipeline</span>
                        <span className="text-foreground font-semibold tabular-nums">{totalActive.toLocaleString()} active</span>
                        <span className="text-border" aria-hidden="true">
                            ·
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            <strong className="text-emerald-600 dark:text-emerald-400">{completionRate}%</strong> completed
                        </span>
                        <span className="text-border" aria-hidden="true">
                            ·
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            <strong className={cn(inPipelineCount > 0 ? "text-amber-600 dark:text-amber-400" : "text-foreground")}>
                                {inPipelineCount}
                            </strong>{" "}
                            awaiting handoff
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => onPaymentToggle(activePaymentFilter === "unpaid" ? "all" : "unpaid")}
                            className={cn(
                                "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                                activePaymentFilter === "unpaid"
                                    ? "border-amber-500/40 bg-amber-500/15 text-amber-700 dark:text-amber-300"
                                    : "border-border/80 bg-background/70 text-muted-foreground hover:text-foreground hover:bg-muted/60",
                            )}
                            title="Filter enrollments with an unpaid balance"
                        >
                            <CircleDollarSign className="size-3.5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                            <span className="tabular-nums">
                                {unpaidCount > 0 ? `${unpaidCount} unpaid · ${formatCompactMoney(unpaidTotal, currency)}` : "All settled"}
                            </span>
                        </button>

                        {applicantsCount > 0 && (
                            <Link
                                href={route("administrators.enrollments.applicants")}
                                className="border-border/80 bg-background/70 hover:bg-muted/60 text-foreground inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                            >
                                <span className="size-1.5 rounded-full bg-blue-500" aria-hidden="true" />
                                <span className="tabular-nums">
                                    {applicantsCount} applicant{applicantsCount === 1 ? "" : "s"}
                                </span>
                                <ArrowUpRight className="text-muted-foreground size-3" aria-hidden="true" />
                            </Link>
                        )}
                    </div>
                </div>

                {/* Proportional Segmented Pipeline Bar */}
                <TooltipProvider delay={120}>
                    <div
                        className="bg-muted flex h-2 w-full overflow-hidden rounded-full"
                        role="img"
                        aria-label={`Pipeline breakdown across ${stages.length} stages`}
                    >
                        {totalAcrossStages === 0 ? (
                            <div className="bg-muted-foreground/20 h-full w-full" />
                        ) : (
                            stages
                                .filter((stage) => stage.count > 0)
                                .map((stage) => {
                                    const pct = Math.max(2, (stage.count / totalAcrossStages) * 100);
                                    const tone = resolvePipelineColor(stage.color);
                                    const isDimmed = activeStatusFilter !== "all" && activeStatusFilter !== stage.value;

                                    return (
                                        <Tooltip key={stage.value}>
                                            <TooltipTrigger
                                                render={
                                                    <button
                                                        type="button"
                                                        onClick={() => onStatusSelect(activeStatusFilter === stage.value ? "all" : stage.value)}
                                                        style={{ width: `${pct}%` }}
                                                        className={cn(
                                                            "border-background/60 h-full border-r transition-opacity duration-150 last:border-r-0 focus-visible:outline-none",
                                                            tone.bar,
                                                            isDimmed ? "opacity-30 hover:opacity-75" : "opacity-95 hover:opacity-100",
                                                        )}
                                                        aria-label={`Filter by ${stage.label} (${stage.count})`}
                                                    />
                                                }
                                            />
                                            <TooltipContent side="top" className="text-xs">
                                                <span className="font-medium">{stage.label}</span>
                                                <span className="text-muted-foreground ml-1.5 tabular-nums">
                                                    {stage.count} ({Math.round((stage.count / totalAcrossStages) * 100)}%)
                                                </span>
                                            </TooltipContent>
                                        </Tooltip>
                                    );
                                })
                        )}
                    </div>
                </TooltipProvider>

                {/* Clickable Stage Queue Pills */}
                <div className="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => onStatusSelect("all")}
                        className={cn(
                            "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors active:scale-[0.98]",
                            activeStatusFilter === "all"
                                ? "border-foreground/20 bg-foreground text-background"
                                : "text-muted-foreground hover:bg-muted hover:text-foreground border-transparent bg-transparent",
                        )}
                    >
                        <span>All</span>
                        <span
                            className={cn(
                                "rounded-full px-1.5 py-0 text-[10.5px] tabular-nums",
                                activeStatusFilter === "all" ? "bg-background/20 text-background" : "bg-muted text-muted-foreground",
                            )}
                        >
                            {totalActive}
                        </span>
                    </button>

                    {stages.map((stage) => {
                        const tone = resolvePipelineColor(stage.color);
                        const isActive = activeStatusFilter === stage.value;
                        return (
                            <button
                                key={stage.value}
                                type="button"
                                onClick={() => onStatusSelect(isActive ? "all" : stage.value)}
                                className={cn(
                                    "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors active:scale-[0.98]",
                                    isActive
                                        ? "border-primary/40 bg-primary/10 text-foreground"
                                        : "text-muted-foreground hover:bg-muted hover:text-foreground border-transparent bg-transparent",
                                )}
                            >
                                <span className={cn("size-1.5 rounded-full", tone.dot)} aria-hidden="true" />
                                <span>{stage.label}</span>
                                <Badge variant={isActive ? "secondary" : "outline"} size="xs" radius="full" className="tabular-nums">
                                    {stage.count}
                                </Badge>
                            </button>
                        );
                    })}

                    {/* Utility queue pills: Cancelled & Deleted */}
                    <button
                        type="button"
                        onClick={() => onStatusSelect(activeStatusFilter === "cancelled" ? "all" : "cancelled")}
                        className={cn(
                            "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                            activeStatusFilter === "cancelled"
                                ? "border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                                : "text-muted-foreground hover:bg-muted hover:text-foreground border-transparent",
                        )}
                    >
                        <Ban className="size-3 opacity-75" aria-hidden="true" />
                        <span>Cancelled</span>
                    </button>

                    {trashedCount > 0 && (
                        <button
                            type="button"
                            onClick={() => onStatusSelect(activeStatusFilter === "trashed" ? "all" : "trashed")}
                            className={cn(
                                "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                                activeStatusFilter === "trashed"
                                    ? "border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                                    : "text-muted-foreground hover:bg-muted hover:text-foreground border-transparent",
                            )}
                        >
                            <Trash2 className="size-3 opacity-75" aria-hidden="true" />
                            <span>Trash</span>
                            <Badge variant="outline" size="xs" radius="full" className="tabular-nums">
                                {trashedCount}
                            </Badge>
                        </button>
                    )}
                </div>
            </FramePanel>
        </Frame>
    );
}
