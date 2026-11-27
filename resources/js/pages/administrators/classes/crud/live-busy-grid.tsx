import { cn } from "@/lib/utils";
import type { BusyOverlayBlock, CrudScheduleBlock, PreflightConflict, WeeklyBusyOverlay } from "./types";
import { AlertTriangle, Building2, Calendar, GraduationCap, UserRound } from "lucide-react";
import { useMemo, useState } from "react";

const BASE_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
const ALL_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as const;

const START_HOUR = 7;
const END_HOUR = 20;
const TOTAL_MINUTES = (END_HOUR - START_HOUR) * 60;

function parseMinutes(time?: string | null): number | null {
    if (!time) return null;
    const parts = time.trim().split(":");
    const hours = Number(parts[0]);
    const minutes = Number(parts[1] ?? 0);
    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return null;
    return hours * 60 + minutes;
}

function normalizeDay(day: string): string {
    const trimmed = day.trim().toLowerCase();
    const match = ALL_DAYS.find((d) => d.toLowerCase() === trimmed || d.toLowerCase().startsWith(trimmed.slice(0, 3)));
    return match ?? day;
}

function formatHourTick(hour: number): string {
    if (hour === 12) return "12p";
    return hour > 12 ? `${hour - 12}p` : `${hour}a`;
}

function formatShortTime(time: string): string {
    const mins = parseMinutes(time);
    if (mins === null) return time;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    const suffix = h >= 12 ? "p" : "a";
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return m === 0 ? `${h12}${suffix}` : `${h12}:${String(m).padStart(2, "0")}${suffix}`;
}

export type LiveBusyGridBlock = CrudScheduleBlock & {
    label?: string;
    room_name?: string | null;
    section?: string;
    has_conflict?: boolean;
};

type OverlayFilter = "all" | "faculty" | "room" | "cohort";

type LiveBusyGridProps = {
    schedules: LiveBusyGridBlock[];
    busyOverlay?: WeeklyBusyOverlay | null;
    conflicts?: PreflightConflict[];
    allowSunday?: boolean;
    compact?: boolean;
    roomsById?: Map<number, { label: string; capacity?: number | null }>;
    onBlockClick?: (index: number, block: LiveBusyGridBlock) => void;
    activeScheduleIndex?: number | null;
    className?: string;
};

export function LiveBusyGrid({
    schedules,
    busyOverlay,
    conflicts = [],
    allowSunday = false,
    compact = false,
    roomsById,
    onBlockClick,
    activeScheduleIndex = null,
    className,
}: LiveBusyGridProps) {
    const [filter, setFilter] = useState<OverlayFilter>("all");

    const hasSundayBlock = useMemo(() => {
        if (allowSunday) return true;
        const inProposed = schedules.some((s) => normalizeDay(s.day_of_week) === "Sunday");
        const inOverlay = [
            ...(busyOverlay?.faculty_busy ?? []),
            ...(busyOverlay?.room_busy ?? []),
            ...(busyOverlay?.cohort_busy ?? []),
        ].some((b) => normalizeDay(b.day_of_week) === "Sunday");
        return inProposed || inOverlay;
    }, [allowSunday, schedules, busyOverlay]);

    const days = hasSundayBlock ? ALL_DAYS : BASE_DAYS;
    const hourTicks = useMemo(() => {
        const ticks: number[] = [];
        for (let h = START_HOUR; h <= END_HOUR; h += compact ? 2 : 1) {
            ticks.push(h);
        }
        return ticks;
    }, [compact]);

    const conflictedIndices = useMemo(() => {
        const set = new Set<number>();
        for (const c of conflicts) {
            if (c.schedule_index !== null && (c.severity === "blocking" || c.kind === "room" || c.kind === "faculty" || c.kind === "self")) {
                set.add(c.schedule_index);
            }
        }
        return set;
    }, [conflicts]);

    const overlayBlocks = useMemo<BusyOverlayBlock[]>(() => {
        if (!busyOverlay) return [];
        const all: BusyOverlayBlock[] = [];
        if (filter === "all" || filter === "faculty") all.push(...(busyOverlay.faculty_busy ?? []));
        if (filter === "all" || filter === "room") all.push(...(busyOverlay.room_busy ?? []));
        if (filter === "all" || filter === "cohort") all.push(...(busyOverlay.cohort_busy ?? []));
        return all;
    }, [busyOverlay, filter]);

    const counts = useMemo(
        () => ({
            faculty: busyOverlay?.faculty_busy?.length ?? 0,
            room: busyOverlay?.room_busy?.length ?? 0,
            cohort: busyOverlay?.cohort_busy?.length ?? 0,
        }),
        [busyOverlay],
    );

    const gridHeightPx = compact ? 280 : 390;

    return (
        <div className={cn("flex flex-col gap-2.5", className)}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                    <Calendar className="size-3.5 text-primary" aria-hidden="true" />
                    <span>Weekly Busy Grid</span>
                    <span className="text-muted-foreground font-normal">({schedules.length} {schedules.length === 1 ? "block" : "blocks"})</span>
                </div>

                <div className="flex flex-wrap items-center gap-1" role="group" aria-label="Busy overlay filter">
                    {(
                        [
                            { id: "all", label: "All", count: counts.faculty + counts.room + counts.cohort },
                            { id: "faculty", label: "Faculty", count: counts.faculty, icon: UserRound },
                            { id: "room", label: "Room", count: counts.room, icon: Building2 },
                            { id: "cohort", label: "Cohort", count: counts.cohort, icon: GraduationCap },
                        ] as const
                    ).map((item) => {
                        const active = filter === item.id;
                        return (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => setFilter(item.id)}
                                className={cn(
                                    "inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium transition-colors",
                                    active
                                        ? "border-primary/40 bg-primary/10 text-primary"
                                        : "border-border/60 bg-background text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                                )}
                            >
                                <span>{item.label}</span>
                                {item.count > 0 && <span className="tabular-nums opacity-75">{item.count}</span>}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="bg-muted/20 overflow-hidden rounded-xl border">
                {/* Day Headers */}
                <div
                    className="bg-muted/40 grid border-b text-[11px] font-semibold text-muted-foreground"
                    style={{ gridTemplateColumns: `38px repeat(${days.length}, minmax(0, 1fr))` }}
                >
                    <div className="border-r px-1 py-1.5 text-center text-[10px]">Time</div>
                    {days.map((day) => (
                        <div key={day} className="truncate border-r px-1 py-1.5 text-center last:border-r-0">
                            {day.slice(0, 3)}
                        </div>
                    ))}
                </div>

                {/* Time-scaled Body */}
                <div
                    className="relative grid"
                    style={{
                        gridTemplateColumns: `38px repeat(${days.length}, minmax(0, 1fr))`,
                        height: `${gridHeightPx}px`,
                    }}
                >
                    {/* Time axis */}
                    <div className="relative border-r bg-background/60">
                        {hourTicks.map((hour) => {
                            const topPct = (((hour - START_HOUR) * 60) / TOTAL_MINUTES) * 100;
                            if (topPct > 96) return null;
                            return (
                                <span
                                    key={hour}
                                    style={{ top: `${topPct}%` }}
                                    className="text-muted-foreground absolute right-1 -translate-y-1/2 text-[10px] tabular-nums"
                                >
                                    {formatHourTick(hour)}
                                </span>
                            );
                        })}
                    </div>

                    {/* Day columns */}
                    {days.map((day) => {
                        const dayOverlay = overlayBlocks.filter((b) => normalizeDay(b.day_of_week) === day);
                        const dayProposed = schedules
                            .map((s, idx) => ({ s, idx }))
                            .filter(({ s }) => normalizeDay(s.day_of_week) === day);

                        return (
                            <div key={day} className="relative border-r last:border-r-0">
                                {/* Hour gridlines */}
                                {hourTicks.map((hour) => {
                                    const topPct = (((hour - START_HOUR) * 60) / TOTAL_MINUTES) * 100;
                                    return (
                                        <div
                                            key={hour}
                                            style={{ top: `${topPct}%` }}
                                            className="border-border/40 pointer-events-none absolute inset-x-0 border-t border-dashed"
                                        />
                                    );
                                })}

                                {/* Background busy blocks */}
                                {dayOverlay.map((block, bIdx) => {
                                    const start = parseMinutes(block.start_time);
                                    const end = parseMinutes(block.end_time);
                                    if (start === null || end === null || end <= start) return null;
                                    const clampedStart = Math.max(START_HOUR * 60, Math.min(END_HOUR * 60, start));
                                    const clampedEnd = Math.max(clampedStart + 20, Math.min(END_HOUR * 60, end));
                                    const topPct = ((clampedStart - START_HOUR * 60) / TOTAL_MINUTES) * 100;
                                    const heightPct = ((clampedEnd - clampedStart) / TOTAL_MINUTES) * 100;

                                    const kindStyles =
                                        block.kind === "faculty"
                                            ? "border-amber-500/40 bg-amber-500/12 text-amber-800 dark:text-amber-300"
                                            : block.kind === "room"
                                              ? "border-slate-500/40 bg-slate-500/12 text-slate-700 dark:text-slate-300"
                                              : "border-sky-500/35 bg-sky-500/10 text-sky-800 dark:text-sky-300";

                                    return (
                                        <div
                                            key={`${block.kind}-${block.class_id}-${bIdx}`}
                                            style={{ top: `${topPct}%`, height: `${heightPct}%` }}
                                            title={`${block.kind.toUpperCase()} busy: ${block.subject_code} Sec ${block.section} (${formatShortTime(block.start_time)}–${formatShortTime(block.end_time)})${block.room_name ? ` · ${block.room_name}` : ""}`}
                                            className={cn(
                                                "pointer-events-auto absolute inset-x-0.5 z-10 overflow-hidden rounded border border-dashed px-1 py-0.5 text-[9px] leading-tight",
                                                kindStyles,
                                            )}
                                        >
                                            <div className="truncate font-semibold">{block.subject_code}</div>
                                            {!compact && <div className="truncate opacity-80">Sec {block.section}</div>}
                                        </div>
                                    );
                                })}

                                {/* Proposed / current class blocks */}
                                {dayProposed.map(({ s, idx }) => {
                                    const start = parseMinutes(s.start_time);
                                    const end = parseMinutes(s.end_time);
                                    if (start === null || end === null || end <= start) return null;
                                    const clampedStart = Math.max(START_HOUR * 60, Math.min(END_HOUR * 60, start));
                                    const clampedEnd = Math.max(clampedStart + 25, Math.min(END_HOUR * 60, end));
                                    const topPct = ((clampedStart - START_HOUR * 60) / TOTAL_MINUTES) * 100;
                                    const heightPct = ((clampedEnd - clampedStart) / TOTAL_MINUTES) * 100;
                                    const hasConflict = Boolean(s.has_conflict) || conflictedIndices.has(idx);
                                    const isLab = s.component_type === "lab";
                                    const isActive = activeScheduleIndex === idx;
                                    const resolvedRoom =
                                        s.room_name ?? (s.room_id && roomsById ? roomsById.get(Number(s.room_id))?.label : null);

                                    const blockTone = hasConflict
                                        ? "border-destructive bg-destructive/15 text-destructive ring-1 ring-destructive/40"
                                        : isLab
                                          ? "border-emerald-600/60 bg-emerald-600/15 text-emerald-900 dark:border-emerald-400/50 dark:bg-emerald-500/20 dark:text-emerald-200"
                                          : "border-primary/70 bg-primary/15 text-primary dark:bg-primary/25";

                                    const Tag = onBlockClick ? "button" : "div";

                                    return (
                                        <Tag
                                            key={`prop-${idx}`}
                                            type={onBlockClick ? "button" : undefined}
                                            onClick={onBlockClick ? () => onBlockClick(idx, s) : undefined}
                                            style={{ top: `${topPct}%`, height: `${heightPct}%` }}
                                            title={`${s.label ?? (isLab ? "Lab" : "Lecture")} · ${formatShortTime(s.start_time)}–${formatShortTime(s.end_time)}${resolvedRoom ? ` · ${resolvedRoom}` : ""}`}
                                            className={cn(
                                                "absolute inset-x-1 z-20 flex flex-col justify-between overflow-hidden rounded-md border px-1.5 py-0.5 text-left text-[10px] leading-tight shadow-2xs transition",
                                                blockTone,
                                                onBlockClick && "cursor-pointer hover:brightness-95",
                                                isActive && "ring-2 ring-primary ring-offset-1",
                                            )}
                                        >
                                            <div className="flex items-center justify-between gap-0.5">
                                                <span className="truncate font-bold">
                                                    {s.label ?? (isLab ? "Lab" : "Lec")}
                                                </span>
                                                {hasConflict && <AlertTriangle className="size-2.5 shrink-0" aria-hidden="true" />}
                                            </div>
                                            <div className="truncate text-[9px] tabular-nums opacity-85">
                                                {formatShortTime(s.start_time)}–{formatShortTime(s.end_time)}
                                            </div>
                                            {!compact && resolvedRoom && (
                                                <div className="truncate text-[9px] font-medium opacity-90">{resolvedRoom}</div>
                                            )}
                                        </Tag>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Legend */}
            <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                <span className="inline-flex items-center gap-1">
                    <span className="border-primary/70 bg-primary/20 size-2.5 rounded-xs border" />
                    Lecture
                </span>
                <span className="inline-flex items-center gap-1">
                    <span className="size-2.5 rounded-xs border border-emerald-600/60 bg-emerald-600/20" />
                    Lab
                </span>
                <span className="inline-flex items-center gap-1">
                    <span className="border-destructive bg-destructive/20 size-2.5 rounded-xs border" />
                    Conflict
                </span>
                <span className="inline-flex items-center gap-1">
                    <span className="size-2.5 rounded-xs border border-dashed border-amber-500/60 bg-amber-500/15" />
                    Faculty busy
                </span>
                <span className="inline-flex items-center gap-1">
                    <span className="size-2.5 rounded-xs border border-dashed border-slate-500/60 bg-slate-500/15" />
                    Room busy
                </span>
            </div>
        </div>
    );
}
