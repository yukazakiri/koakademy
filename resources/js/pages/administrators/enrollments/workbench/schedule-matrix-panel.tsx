import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { cn } from "@/lib/utils";
import { AlertTriangle, CalendarDays, Globe, MapPin } from "lucide-react";
import { useMemo } from "react";
import {
    deliveryModeShortLabel,
    type DeliveryMode,
    type SchedulingPolicy,
} from "../../classes/lib/class-defaults";
import {
    checksStudentScheduleConflicts,
    effectiveScheduleDeliveryMode,
    isOnlineDeliveryMode,
    normalizeDay,
    parseTimeToMinutes,
    resolveSchedulingPolicy,
} from "./schedule-conflicts";
import type { DraftSubjectEnrollment, ScheduleConflict } from "./types";

export interface ScheduleMatrixPanelProps {
    subjectsEnrolled: DraftSubjectEnrollment[];
    scheduleConflicts: ScheduleConflict[];
    schedulingPolicy?: SchedulingPolicy;
    highlightedSubjectId?: number | null;
    onHoverSubject?: (subjectId: number | null) => void;
}

const WEEK_DAYS = [
    { key: "Monday", short: "Mon" },
    { key: "Tuesday", short: "Tue" },
    { key: "Wednesday", short: "Wed" },
    { key: "Thursday", short: "Thu" },
    { key: "Friday", short: "Fri" },
    { key: "Saturday", short: "Sat" },
    { key: "Sunday", short: "Sun" },
] as const;

const START_HOUR = 7; // 7:00 AM
const END_HOUR = 21; // 9:00 PM
const START_MINUTES = START_HOUR * 60;
const END_MINUTES = END_HOUR * 60;
const HOUR_HEIGHT_PX = 40;
const TOTAL_GRID_HEIGHT_PX = (END_HOUR - START_HOUR) * HOUR_HEIGHT_PX;

const SUBJECT_PALETTE = [
    "border-sky-500/35 bg-sky-500/12 text-sky-950 dark:border-sky-400/40 dark:bg-sky-400/15 dark:text-sky-100",
    "border-emerald-500/35 bg-emerald-500/12 text-emerald-950 dark:border-emerald-400/40 dark:bg-emerald-400/15 dark:text-emerald-100",
    "border-violet-500/35 bg-violet-500/12 text-violet-950 dark:border-violet-400/40 dark:bg-violet-400/15 dark:text-violet-100",
    "border-amber-500/35 bg-amber-500/12 text-amber-950 dark:border-amber-400/40 dark:bg-amber-400/15 dark:text-amber-100",
    "border-cyan-500/35 bg-cyan-500/12 text-cyan-950 dark:border-cyan-400/40 dark:bg-cyan-400/15 dark:text-cyan-100",
    "border-indigo-500/35 bg-indigo-500/12 text-indigo-950 dark:border-indigo-400/40 dark:bg-indigo-400/15 dark:text-indigo-100",
    "border-teal-500/35 bg-teal-500/12 text-teal-950 dark:border-teal-400/40 dark:bg-teal-400/15 dark:text-teal-100",
    "border-rose-500/35 bg-rose-500/12 text-rose-950 dark:border-rose-400/40 dark:bg-rose-400/15 dark:text-rose-100",
] as const;

interface RawScheduleBlock {
    id: string;
    subjectId: number;
    subjectCode: string;
    subjectTitle: string;
    sectionName: string;
    day: string;
    startMin: number;
    endMin: number;
    timeLabel: string;
    room: string | null;
    deliveryMode: DeliveryMode;
    checksConflicts: boolean;
    colorClass: string;
    hasConflict: boolean;
}

interface PositionedScheduleBlock extends RawScheduleBlock {
    lane: number;
    laneCount: number;
}

function formatHourAxis(hour24: number): string {
    const period = hour24 >= 12 ? "PM" : "AM";
    const h12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
    return `${h12} ${period}`;
}

function formatCompactTime(minutes: number): string {
    const h24 = Math.floor(minutes / 60);
    const m = minutes % 60;
    const period = h24 >= 12 ? "p" : "a";
    const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
    return m === 0
        ? `${h12}${period}`
        : `${h12}:${String(m).padStart(2, "0")}${period}`;
}

function layoutDayBlocks(blocks: RawScheduleBlock[]): PositionedScheduleBlock[] {
    if (blocks.length === 0) return [];
    const sorted = [...blocks].sort(
        (a, b) => a.startMin - b.startMin || b.endMin - a.endMin,
    );

    // Group overlapping blocks into connected clusters
    const clusters: RawScheduleBlock[][] = [];
    let currentCluster: RawScheduleBlock[] = [];
    let clusterEnd = -1;

    for (const block of sorted) {
        if (currentCluster.length === 0 || block.startMin < clusterEnd) {
            currentCluster.push(block);
            clusterEnd = Math.max(clusterEnd, block.endMin);
        } else {
            clusters.push(currentCluster);
            currentCluster = [block];
            clusterEnd = block.endMin;
        }
    }
    if (currentCluster.length > 0) {
        clusters.push(currentCluster);
    }

    const positioned: PositionedScheduleBlock[] = [];
    for (const cluster of clusters) {
        const laneEnds: number[] = [];
        const assigned: { block: RawScheduleBlock; lane: number }[] = [];

        for (const block of cluster) {
            let laneIndex = laneEnds.findIndex((end) => end <= block.startMin);
            if (laneIndex === -1) {
                laneIndex = laneEnds.length;
                laneEnds.push(block.endMin);
            } else {
                laneEnds[laneIndex] = block.endMin;
            }
            assigned.push({ block, lane: laneIndex });
        }

        const totalLanes = Math.max(1, laneEnds.length);
        for (const item of assigned) {
            positioned.push({
                ...item.block,
                lane: item.lane,
                laneCount: totalLanes,
            });
        }
    }

    return positioned;
}

export function ScheduleMatrixPanel({
    subjectsEnrolled,
    scheduleConflicts,
    schedulingPolicy,
    highlightedSubjectId = null,
    onHoverSubject,
}: ScheduleMatrixPanelProps) {
    const resolvedPolicy = useMemo(
        () => resolveSchedulingPolicy(schedulingPolicy),
        [schedulingPolicy],
    );

    const hours = useMemo(
        () =>
            Array.from(
                { length: END_HOUR - START_HOUR },
                (_, idx) => START_HOUR + idx,
            ),
        [],
    );

    const conflictLookup = useMemo(() => {
        const bySchedule = new Set<string>();
        const byDayFallback = new Set<string>();
        for (const conflict of scheduleConflicts) {
            const day = normalizeDay(conflict.day);
            if (typeof conflict.scheduleAIndex === "number") {
                bySchedule.add(
                    `${conflict.subjectAId}:${day}:${conflict.scheduleAIndex}`,
                );
            } else {
                byDayFallback.add(`${conflict.subjectAId}:${day}`);
            }

            if (typeof conflict.scheduleBIndex === "number") {
                bySchedule.add(
                    `${conflict.subjectBId}:${day}:${conflict.scheduleBIndex}`,
                );
            } else {
                byDayFallback.add(`${conflict.subjectBId}:${day}`);
            }
        }
        return { bySchedule, byDayFallback };
    }, [scheduleConflicts]);

    const {
        blocksByDay,
        scheduledSubjectCount,
        unscheduledAssignedSubjects,
        hasSundayBlocks,
        exemptOnlineBlockCount,
    } = useMemo(() => {
        const rawByDay = new Map<string, RawScheduleBlock[]>();
        for (const d of WEEK_DAYS) {
            rawByDay.set(d.key, []);
        }

        let scheduledSubjects = 0;
        let exemptOnlineBlocks = 0;
        const tbaSubjects: { code: string; section: string }[] = [];

        subjectsEnrolled.forEach((subject, subjectIndex) => {
            if (!subject.class_id) return;
            const matchedSection = subject.sections?.find(
                (sec) => sec.id === subject.class_id,
            );
            if (!matchedSection) return;

            const colorClass =
                SUBJECT_PALETTE[subjectIndex % SUBJECT_PALETTE.length];
            let placedCount = 0;

            (matchedSection.schedules ?? []).forEach((sched, schedIdx) => {
                const normalizedDay = normalizeDay(sched.day);
                const dayBucket = rawByDay.get(normalizedDay);
                if (!dayBucket) return;

                const startMin = parseTimeToMinutes(sched.start_time);
                const endMin = parseTimeToMinutes(sched.end_time);
                if (startMin < 0 || endMin <= startMin) return;

                const clampedStart = Math.max(START_MINUTES, startMin);
                const clampedEnd = Math.min(END_MINUTES, endMin);
                if (clampedEnd <= clampedStart) return;

                const deliveryMode = effectiveScheduleDeliveryMode(
                    sched,
                    matchedSection.delivery_mode,
                );
                const checksConflicts = checksStudentScheduleConflicts(
                    deliveryMode,
                    resolvedPolicy,
                );
                if (isOnlineDeliveryMode(deliveryMode) && !checksConflicts) {
                    exemptOnlineBlocks += 1;
                }

                const hasConflict =
                    checksConflicts &&
                    (conflictLookup.bySchedule.has(
                        `${subject.subject_id}:${normalizedDay}:${schedIdx}`,
                    ) ||
                        conflictLookup.byDayFallback.has(
                            `${subject.subject_id}:${normalizedDay}`,
                        ));

                dayBucket.push({
                    id: `${subject.subject_id}-${matchedSection.id}-${normalizedDay}-${schedIdx}`,
                    subjectId: subject.subject_id,
                    subjectCode: subject.subject_code,
                    subjectTitle: subject.subject_title,
                    sectionName: matchedSection.section,
                    day: normalizedDay,
                    startMin: clampedStart,
                    endMin: clampedEnd,
                    timeLabel: `${formatCompactTime(startMin)}–${formatCompactTime(endMin)}`,
                    room: isOnlineDeliveryMode(deliveryMode)
                        ? deliveryModeShortLabel(deliveryMode)
                        : sched.room,
                    deliveryMode,
                    checksConflicts,
                    colorClass,
                    hasConflict,
                });
                placedCount += 1;
            });

            if (placedCount > 0) {
                scheduledSubjects += 1;
            } else {
                tbaSubjects.push({
                    code: subject.subject_code,
                    section: matchedSection.section,
                });
            }
        });

        const positionedMap = new Map<string, PositionedScheduleBlock[]>();
        for (const d of WEEK_DAYS) {
            positionedMap.set(
                d.key,
                layoutDayBlocks(rawByDay.get(d.key) ?? []),
            );
        }

        return {
            blocksByDay: positionedMap,
            scheduledSubjectCount: scheduledSubjects,
            unscheduledAssignedSubjects: tbaSubjects,
            hasSundayBlocks: (rawByDay.get("Sunday")?.length ?? 0) > 0,
            exemptOnlineBlockCount: exemptOnlineBlocks,
        };
    }, [subjectsEnrolled, conflictLookup, resolvedPolicy]);

    const visibleDays = useMemo(
        () =>
            resolvedPolicy.allow_sunday_scheduling || hasSundayBlocks
                ? WEEK_DAYS
                : WEEK_DAYS.slice(0, 6),
        [resolvedPolicy.allow_sunday_scheduling, hasSundayBlocks],
    );

    const gridColsClass =
        visibleDays.length === 7
            ? "grid-cols-[52px_repeat(7,minmax(0,1fr))]"
            : "grid-cols-[52px_repeat(6,minmax(0,1fr))]";

    const hasAnyBlocks = scheduledSubjectCount > 0;

    return (
        <Frame spacing="sm" className="min-w-0 w-full">
            <FrameHeader className="flex-row flex-wrap items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <IconTile
                        variant="soft"
                        size="xs"
                        className="shrink-0 text-primary"
                    >
                        <CalendarDays />
                    </IconTile>
                    <div className="min-w-0">
                        <FrameTitle className="truncate">
                            Weekly Schedule Matrix
                        </FrameTitle>
                        <FrameDescription className="truncate text-xs tabular-nums">
                            {scheduledSubjectCount} of {subjectsEnrolled.length}{" "}
                            subject(s) plotted ·{" "}
                            {visibleDays.length === 7 ? "Mon–Sun" : "Mon–Sat"},
                            7 AM–9 PM
                        </FrameDescription>
                    </div>
                </div>

                {!resolvedPolicy.check_student_cohort_conflicts ? (
                    <Badge variant="secondary" size="sm">
                        Conflict checks off
                    </Badge>
                ) : scheduleConflicts.length > 0 ? (
                    <Badge variant="destructive-light" size="sm">
                        <AlertTriangle className="size-3" />
                        {scheduleConflicts.length}{" "}
                        {scheduleConflicts.length === 1 ? "clash" : "clashes"}
                    </Badge>
                ) : hasAnyBlocks ? (
                    <Badge variant="success-light" size="sm">
                        No clashes
                    </Badge>
                ) : null}
            </FrameHeader>

            <FramePanel className="min-w-0 overflow-hidden p-0">
                {scheduleConflicts.length > 0 && (
                    <div className="border-b border-border/70 p-3">
                        <Alert variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>
                                Schedule conflict detected (
                                {scheduleConflicts.length})
                            </AlertTitle>
                            <AlertDescription className="mt-1 space-y-1">
                                <ul className="space-y-1 text-xs">
                                    {scheduleConflicts.map((conflict, idx) => (
                                        <li
                                            key={`${conflict.subjectAId}-${conflict.subjectBId}-${conflict.overlapWindow}-${idx}`}
                                            className="flex flex-wrap items-center gap-1.5"
                                        >
                                            <span className="font-mono font-semibold text-foreground">
                                                {conflict.subjectACode}
                                            </span>
                                            <span className="text-muted-foreground">
                                                ↔
                                            </span>
                                            <span className="font-mono font-semibold text-foreground">
                                                {conflict.subjectBCode}
                                            </span>
                                            <span className="text-muted-foreground">
                                                ·
                                            </span>
                                            <span className="font-medium text-destructive tabular-nums">
                                                {conflict.overlapWindow}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                {!hasAnyBlocks ? (
                    <div className="flex flex-col items-center justify-center gap-2.5 px-6 py-12 text-center">
                        <IconTile
                            variant="frame"
                            size="lg"
                            className="text-muted-foreground"
                        >
                            <CalendarDays />
                        </IconTile>
                        <div className="max-w-sm space-y-1">
                            <p className="text-sm font-medium text-foreground">
                                No scheduled sections yet
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Assign class sections to your enrolled subjects
                                to preview the weekly timetable and catch time
                                clashes automatically.
                            </p>
                        </div>
                        {unscheduledAssignedSubjects.length > 0 && (
                            <div className="mt-1 flex flex-wrap items-center justify-center gap-1.5">
                                {unscheduledAssignedSubjects.map((item) => (
                                    <Badge
                                        key={`${item.code}-${item.section}`}
                                        variant="outline"
                                        size="xs"
                                    >
                                        {item.code} ({item.section}) — TBA
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Internal horizontal scroll prevents outer page overflow */}
                        <div className="w-full overflow-x-auto">
                            <div className="min-w-[540px]">
                                {/* Sticky day column header */}
                                <div
                                    className={cn(
                                        "grid border-b border-border/70 bg-muted/30",
                                        gridColsClass,
                                    )}
                                >
                                    <div className="px-2 py-2 text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                                        Time
                                    </div>
                                    {visibleDays.map((day) => {
                                        const count =
                                            blocksByDay.get(day.key)?.length ??
                                            0;
                                        return (
                                            <div
                                                key={day.key}
                                                className="flex items-center justify-between border-l border-border/60 px-2 py-2"
                                            >
                                                <span className="text-xs font-semibold text-foreground">
                                                    {day.short}
                                                </span>
                                                {count > 0 && (
                                                    <span className="text-[10px] text-muted-foreground tabular-nums">
                                                        {count}
                                                    </span>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                                {/* Timetable body */}
                                <div
                                    className={cn("relative grid", gridColsClass)}
                                    style={{ height: TOTAL_GRID_HEIGHT_PX }}
                                >
                                    {/* Time axis column */}
                                    <div className="relative select-none bg-muted/10">
                                        {hours.map((hour, index) => (
                                            <div
                                                key={hour}
                                                style={{
                                                    top: index * HOUR_HEIGHT_PX,
                                                    height: HOUR_HEIGHT_PX,
                                                }}
                                                className="absolute inset-x-0 border-t border-border/50 px-1.5 pt-0.5 text-right text-[10px] text-muted-foreground tabular-nums first:border-t-0"
                                            >
                                                {formatHourAxis(hour)}
                                            </div>
                                        ))}
                                    </div>

                                    {/* Day columns */}
                                    {visibleDays.map((day) => {
                                        const dayBlocks =
                                            blocksByDay.get(day.key) ?? [];

                                        return (
                                            <div
                                                key={day.key}
                                                className="relative border-l border-border/60"
                                            >
                                                {/* Hour guide lines */}
                                                {hours.map((hour, index) => (
                                                    <div
                                                        key={hour}
                                                        style={{
                                                            top:
                                                                index *
                                                                HOUR_HEIGHT_PX,
                                                            height: HOUR_HEIGHT_PX,
                                                        }}
                                                        className="pointer-events-none absolute inset-x-0 border-t border-border/40 first:border-t-0"
                                                    />
                                                ))}

                                                {/* Subject schedule blocks */}
                                                {dayBlocks.map((block) => {
                                                    const topPx =
                                                        ((block.startMin -
                                                            START_MINUTES) /
                                                            60) *
                                                        HOUR_HEIGHT_PX;
                                                    const rawHeightPx =
                                                        ((block.endMin -
                                                            block.startMin) /
                                                            60) *
                                                        HOUR_HEIGHT_PX;
                                                    const heightPx = Math.max(
                                                        22,
                                                        rawHeightPx - 2,
                                                    );
                                                    const widthPct =
                                                        100 / block.laneCount;
                                                    const leftPct =
                                                        block.lane * widthPct;

                                                    const isHighlighted =
                                                        highlightedSubjectId !==
                                                            null &&
                                                        highlightedSubjectId ===
                                                            block.subjectId;
                                                    const isDimmed =
                                                        highlightedSubjectId !==
                                                            null &&
                                                        highlightedSubjectId !==
                                                            block.subjectId;

                                                    const isCompact =
                                                        heightPx < 42;

                                                    return (
                                                        <div
                                                            key={block.id}
                                                            onMouseEnter={() =>
                                                                onHoverSubject?.(
                                                                    block.subjectId,
                                                                )
                                                            }
                                                            onMouseLeave={() =>
                                                                onHoverSubject?.(
                                                                    null,
                                                                )
                                                            }
                                                            style={{
                                                                top: topPx + 1,
                                                                height: heightPx,
                                                                left: `calc(${leftPct}% + 2px)`,
                                                                width: `calc(${widthPct}% - 4px)`,
                                                            }}
                                                            title={`${block.subjectCode} (${block.sectionName}) · ${block.subjectTitle}\n${block.day} ${block.timeLabel}${block.room ? ` · ${block.room}` : ""}${!block.checksConflicts ? "\nExempt from conflict checks by scheduling policy" : ""}${block.hasConflict ? "\n⚠ Schedule clash detected" : ""}`}
                                                            className={cn(
                                                                "absolute z-10 overflow-hidden rounded-md border px-1.5 py-1 text-left transition-all duration-150 cursor-default",
                                                                !block.checksConflicts &&
                                                                    !block.hasConflict &&
                                                                    "border-dashed",
                                                                block.hasConflict
                                                                    ? "border-destructive bg-destructive/12 text-foreground ring-1 ring-destructive/50 dark:bg-destructive/20"
                                                                    : block.colorClass,
                                                                isHighlighted &&
                                                                    !block.hasConflict &&
                                                                    "z-20 shadow-sm ring-2 ring-primary brightness-105",
                                                                isHighlighted &&
                                                                    block.hasConflict &&
                                                                    "z-20 shadow-sm ring-2 ring-destructive brightness-105",
                                                                isDimmed &&
                                                                    "opacity-40",
                                                            )}
                                                        >
                                                            <div className="flex min-w-0 items-center justify-between gap-1">
                                                                <span className="truncate font-mono text-[10px] leading-tight font-semibold">
                                                                    {
                                                                        block.subjectCode
                                                                    }
                                                                </span>
                                                                {block.hasConflict ? (
                                                                    <AlertTriangle className="size-3 shrink-0 text-destructive" />
                                                                ) : isOnlineDeliveryMode(
                                                                      block.deliveryMode,
                                                                  ) ? (
                                                                    <Globe className="size-2.5 shrink-0 opacity-75" />
                                                                ) : null}
                                                            </div>

                                                            {!isCompact && (
                                                                <p className="truncate text-[10px] leading-tight opacity-85 tabular-nums">
                                                                    {
                                                                        block.timeLabel
                                                                    }
                                                                </p>
                                                            )}

                                                            {heightPx >= 54 && (
                                                                <p className="mt-0.5 flex min-w-0 items-center gap-0.5 truncate text-[10px] leading-tight opacity-75">
                                                                    {block.room ? (
                                                                        <>
                                                                            {isOnlineDeliveryMode(
                                                                                block.deliveryMode,
                                                                            ) ? (
                                                                                <Globe className="size-2.5 shrink-0" />
                                                                            ) : (
                                                                                <MapPin className="size-2.5 shrink-0" />
                                                                            )}
                                                                            <span className="truncate">
                                                                                {
                                                                                    block.room
                                                                                }
                                                                            </span>
                                                                        </>
                                                                    ) : (
                                                                        <span className="truncate">
                                                                            {
                                                                                block.sectionName
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </p>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>

                        {(!resolvedPolicy.check_student_cohort_conflicts ||
                            exemptOnlineBlockCount > 0) && (
                            <div className="flex flex-wrap items-center gap-1.5 border-t border-border/70 bg-muted/15 px-3 py-2 text-[11px] text-muted-foreground">
                                <Globe className="size-3 shrink-0 text-muted-foreground" />
                                <span>
                                    {!resolvedPolicy.check_student_cohort_conflicts
                                        ? "Student schedule conflict detection is disabled in institution scheduling policy."
                                        : `Dashed online sessions (${exemptOnlineBlockCount}) are exempt from time-conflict detection under your scheduling policy.`}
                                </span>
                            </div>
                        )}

                        {unscheduledAssignedSubjects.length > 0 && (
                            <div className="flex flex-wrap items-center gap-1.5 border-t border-border/70 bg-muted/20 px-3 py-2 text-[11px] text-muted-foreground">
                                <span className="font-medium">
                                    TBA schedule:
                                </span>
                                {unscheduledAssignedSubjects.map((item) => (
                                    <Badge
                                        key={`${item.code}-${item.section}`}
                                        variant="outline"
                                        size="xs"
                                    >
                                        {item.code} ({item.section})
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </FramePanel>
        </Frame>
    );
}
