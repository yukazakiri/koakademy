import { Badge } from "@/components/ui/badge";
import { AlertTriangle, Building2, Flame, Monitor } from "lucide-react";
import { useMemo } from "react";
import type { HeatmapCell } from "../../components/scheduling-types";
import type { ScheduleStudioController } from "../hooks/use-schedule-studio";
import { minutesToShortHour } from "../lib/time-geometry";

type OccupancyHeatmapViewProps = {
    studio: ScheduleStudioController;
};

const HOURS = Array.from({ length: 14 }, (_, i) => i + 7); // 7 AM .. 8 PM

function heatTileClass(cell: HeatmapCell | undefined): string {
    if (!cell || cell.active_sessions === 0) {
        return "bg-muted/25 border-border/40 text-muted-foreground/60 hover:bg-muted/50";
    }
    if (cell.conflict_sessions > 0) {
        return "bg-red-500/15 border-red-500/45 text-red-900 hover:bg-red-500/25 dark:bg-red-500/25 dark:text-red-100";
    }
    const pct = cell.room_utilization_pct;
    if (pct >= 80) {
        return "bg-amber-500/25 border-amber-500/45 text-amber-950 hover:bg-amber-500/35 dark:bg-amber-500/30 dark:text-amber-100";
    }
    if (pct >= 50) {
        return "bg-sky-500/20 border-sky-500/35 text-sky-950 hover:bg-sky-500/30 dark:bg-sky-500/25 dark:text-sky-100";
    }
    if (pct >= 20) {
        return "bg-emerald-500/15 border-emerald-500/30 text-emerald-950 hover:bg-emerald-500/25 dark:bg-emerald-500/20 dark:text-emerald-100";
    }
    return "bg-emerald-500/[0.07] border-emerald-500/20 text-emerald-900 hover:bg-emerald-500/15 dark:text-emerald-200";
}

export default function OccupancyHeatmapView({ studio }: OccupancyHeatmapViewProps) {
    const util = studio.utilization;
    const days = studio.visibleDays;

    const cellMap = useMemo(() => {
        const map = new Map<string, HeatmapCell>();
        for (const cell of util?.heatmap ?? []) {
            map.set(`${cell.day}:${cell.hour}`, cell);
        }
        return map;
    }, [util?.heatmap]);

    return (
        <section
            aria-label="Campus occupancy heatmap"
            className="bg-card border-border/70 flex flex-1 flex-col overflow-hidden rounded-xl border"
        >
            {/* Header & Legend */}
            <div className="border-border/60 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                <div className="space-y-0.5">
                    <h2 className="flex items-center gap-1.5 text-sm font-semibold tracking-tight">
                        <Flame className="text-primary size-4" aria-hidden="true" />
                        Campus Occupancy Heatmap
                    </h2>
                    <p className="text-muted-foreground text-xs">
                        Click any time slot to jump straight into the Resource Matrix for that day.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2 text-[11px]">
                    <span className="inline-flex items-center gap-1">
                        <span className="bg-muted/40 border-border inline-block size-3 rounded border" /> Quiet
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-3 rounded border border-emerald-500/30 bg-emerald-500/15" /> Light
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-3 rounded border border-sky-500/35 bg-sky-500/20" /> Moderate (50%+)
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-3 rounded border border-amber-500/45 bg-amber-500/25" /> Peak (80%+)
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="inline-block size-3 rounded border border-red-500/50 bg-red-500/20" /> Conflict Hotspot
                    </span>
                </div>
            </div>

            {/* Summary badges */}
            {util && (
                <div className="bg-muted/25 border-border/60 flex flex-wrap items-center gap-2 border-b px-4 py-2 text-xs">
                    <Badge variant="outline" className="gap-1 tabular-nums">
                        <Building2 className="size-3" /> {util.total_active_rooms} active rooms
                    </Badge>
                    <Badge variant="outline" className="tabular-nums">
                        Avg room load: {util.average_room_utilization_pct}%
                    </Badge>
                    {util.peak_hour && (
                        <Badge variant="secondary" className="tabular-nums">
                            Peak: {util.peak_hour.day} {minutesToShortHour(util.peak_hour.hour)} ({util.peak_hour.active_sessions} sessions)
                        </Badge>
                    )}
                    {util.unassigned_room_sessions_count > 0 && (
                        <Badge variant="outline" className="border-amber-500/40 bg-amber-500/10 text-amber-800 tabular-nums dark:text-amber-300">
                            {util.unassigned_room_sessions_count} sessions without room
                        </Badge>
                    )}
                </div>
            )}

            {/* Matrix Grid */}
            <div className="flex-1 overflow-auto p-3.5">
                <div className="min-w-[680px]">
                    {/* Day Columns Header */}
                    <div
                        className="mb-1.5 grid gap-1.5"
                        style={{ gridTemplateColumns: `72px repeat(${days.length}, minmax(0, 1fr))` }}
                    >
                        <div className="text-muted-foreground px-1 py-1 text-[11px] font-medium">Hour</div>
                        {days.map((day) => (
                            <button
                                key={day}
                                type="button"
                                onClick={() => studio.jumpToDayAndHour(day, "matrix")}
                                className="hover:bg-muted/60 rounded-md px-2 py-1 text-center text-xs font-semibold tracking-tight transition-colors"
                            >
                                {day.slice(0, 3)}
                                <span className="text-muted-foreground hidden font-normal xl:inline">{day.slice(3)}</span>
                            </button>
                        ))}
                    </div>

                    {/* Hour Rows */}
                    <div className="space-y-1.5">
                        {HOURS.map((hour) => (
                            <div
                                key={hour}
                                className="grid items-stretch gap-1.5"
                                style={{ gridTemplateColumns: `72px repeat(${days.length}, minmax(0, 1fr))` }}
                            >
                                <div className="text-muted-foreground flex items-center justify-end pr-2 text-[11px] font-medium tabular-nums">
                                    {minutesToShortHour(hour)}
                                </div>

                                {days.map((day) => {
                                    const cell = cellMap.get(`${day}:${hour}`);
                                    const sessions = cell?.active_sessions ?? 0;
                                    const occupied = cell?.occupied_rooms ?? 0;
                                    const free = cell?.free_rooms ?? (util?.total_active_rooms ?? 0);
                                    const online = cell?.online_sessions ?? 0;
                                    const conflictCount = cell?.conflict_sessions ?? 0;
                                    const utilPct = cell?.room_utilization_pct ?? 0;

                                    return (
                                        <button
                                            key={`${day}:${hour}`}
                                            type="button"
                                            onClick={() => studio.jumpToDayAndHour(day, "matrix")}
                                            title={`${day} ${minutesToShortHour(hour)}: ${sessions} sessions · ${occupied} rooms busy · ${free} free${online > 0 ? ` · ${online} online` : ""}${conflictCount > 0 ? ` · ${conflictCount} in conflict` : ""}`}
                                            className={`group flex min-h-[46px] flex-col justify-between rounded-lg border px-2 py-1.5 text-left transition-colors active:scale-[0.98] ${heatTileClass(cell)}`}
                                        >
                                            <div className="flex items-center justify-between gap-1">
                                                <span className="text-xs font-semibold tabular-nums">
                                                    {sessions > 0 ? `${utilPct}%` : "—"}
                                                </span>
                                                <div className="flex items-center gap-1">
                                                    {conflictCount > 0 && (
                                                        <AlertTriangle className="size-3 text-red-600 dark:text-red-300" aria-label="Conflict hotspot" />
                                                    )}
                                                    {online > 0 && (
                                                        <Monitor className="size-3 opacity-70" aria-label={`${online} online`} />
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between text-[10px] opacity-80 tabular-nums">
                                                <span>{sessions > 0 ? `${sessions} cls` : "Free"}</span>
                                                {sessions > 0 && <span>{free} open</span>}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
