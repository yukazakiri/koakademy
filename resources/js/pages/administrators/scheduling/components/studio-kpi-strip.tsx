import { AlertTriangle, Building2, CalendarCheck2, ClockAlert, Flame, Users } from "lucide-react";
import type { ScheduleStudioController } from "../hooks/use-schedule-studio";
import { minutesToShortHour } from "../lib/time-geometry";

type StudioKpiStripProps = {
    studio: ScheduleStudioController;
};

export default function StudioKpiStrip({ studio }: StudioKpiStripProps) {
    const totalClasses = studio.classes.length;
    const totalSessions = studio.classes.reduce((sum, item) => sum + item.schedules.length, 0);
    const conflictCount = studio.conflicts.length;
    const needsTimes = studio.needsTimesCount;
    const util = studio.utilization;
    const avgUtil = util?.average_room_utilization_pct ?? 0;
    const overcrowded = util?.overcrowded_sessions_count ?? 0;
    const unassignedRooms = util?.unassigned_room_sessions_count ?? 0;
    const peak = util?.peak_hour;

    return (
        <section aria-label="Schedule overview metrics" className="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
            {/* 1. Term Coverage */}
            <button
                type="button"
                onClick={() => {
                    studio.setActiveLens("week");
                    studio.setRailOpen(true);
                    studio.setRailTab("all");
                }}
                className="bg-card border-border/70 hover:border-border group flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-left transition-colors active:scale-[0.99]"
            >
                <div className="min-w-0 space-y-0.5">
                    <p className="text-muted-foreground text-[11px] font-medium">Scheduled Coverage</p>
                    <div className="flex items-baseline gap-1.5">
                        <span className="text-lg font-semibold tracking-tight tabular-nums">{totalClasses}</span>
                        <span className="text-muted-foreground text-xs tabular-nums">classes · {totalSessions} blocks</span>
                    </div>
                </div>
                <span className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg">
                    <CalendarCheck2 className="size-4" aria-hidden="true" />
                </span>
            </button>

            {/* 2. Needs Times */}
            <button
                type="button"
                onClick={() => {
                    studio.setRailOpen(true);
                    studio.setRailTab("needs_times");
                }}
                className={`border-border/70 hover:border-border group flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-left transition-colors active:scale-[0.99] ${
                    needsTimes > 0 ? "bg-amber-500/[0.04]" : "bg-card"
                }`}
            >
                <div className="min-w-0 space-y-0.5">
                    <p className="text-muted-foreground text-[11px] font-medium">Needs Times / Hours</p>
                    <div className="flex items-baseline gap-1.5">
                        <span
                            className={`text-lg font-semibold tracking-tight tabular-nums ${
                                needsTimes > 0 ? "text-amber-700 dark:text-amber-300" : "text-emerald-700 dark:text-emerald-400"
                            }`}
                        >
                            {needsTimes}
                        </span>
                        <span className="text-muted-foreground truncate text-xs">
                            {needsTimes > 0 ? "drag from rail to place" : "all targets met"}
                        </span>
                    </div>
                </div>
                <span
                    className={`flex size-8 shrink-0 items-center justify-center rounded-lg ${
                        needsTimes > 0
                            ? "bg-amber-500/15 text-amber-700 dark:text-amber-300"
                            : "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400"
                    }`}
                >
                    <ClockAlert className="size-4" aria-hidden="true" />
                </span>
            </button>

            {/* 3. Active Conflicts */}
            <button
                type="button"
                onClick={() => {
                    studio.setWorkbenchOpen(true);
                    if (conflictCount > 0) {
                        studio.setSpotlightMode(true);
                    }
                }}
                className={`group flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-left transition-colors active:scale-[0.99] ${
                    conflictCount > 0
                        ? "border-red-500/40 bg-red-500/[0.05] hover:border-red-500/60"
                        : "bg-card border-border/70 hover:border-border"
                }`}
            >
                <div className="min-w-0 space-y-0.5">
                    <p className="text-muted-foreground text-[11px] font-medium">Active Conflicts</p>
                    <div className="flex items-baseline gap-1.5">
                        <span
                            className={`text-lg font-semibold tracking-tight tabular-nums ${
                                conflictCount > 0 ? "text-red-600 dark:text-red-400" : "text-emerald-700 dark:text-emerald-400"
                            }`}
                        >
                            {conflictCount}
                        </span>
                        <span className="text-muted-foreground truncate text-xs">
                            {conflictCount > 0 ? "open resolver & spotlight" : "zero clashes"}
                        </span>
                    </div>
                </div>
                <span
                    className={`flex size-8 shrink-0 items-center justify-center rounded-lg ${
                        conflictCount > 0
                            ? "bg-red-500/15 text-red-600 dark:text-red-400"
                            : "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400"
                    }`}
                >
                    <AlertTriangle className="size-4" aria-hidden="true" />
                </span>
            </button>

            {/* 4. Room Utilization & Capacity */}
            <button
                type="button"
                onClick={() => studio.setActiveLens(overcrowded > 0 ? "matrix" : "heatmap")}
                className="bg-card border-border/70 hover:border-border group flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-left transition-colors active:scale-[0.99]"
            >
                <div className="min-w-0 space-y-0.5">
                    <p className="text-muted-foreground text-[11px] font-medium">Room Utilization</p>
                    <div className="flex items-baseline gap-1.5">
                        <span className="text-lg font-semibold tracking-tight tabular-nums">{avgUtil}%</span>
                        <span className="text-muted-foreground truncate text-xs tabular-nums">
                            {overcrowded > 0 ? (
                                <span className="inline-flex items-center gap-0.5 font-medium text-amber-700 dark:text-amber-300">
                                    <Users className="size-3" /> {overcrowded} overcrowded
                                </span>
                            ) : unassignedRooms > 0 ? (
                                `${unassignedRooms} rooms unassigned`
                            ) : peak ? (
                                `Peak ${peak.day.slice(0, 3)} ${minutesToShortHour(peak.hour)}`
                            ) : (
                                `${util?.total_active_rooms ?? 0} rooms active`
                            )}
                        </span>
                    </div>
                </div>
                <span className="bg-sky-500/10 text-sky-700 dark:text-sky-300 flex size-8 shrink-0 items-center justify-center rounded-lg">
                    {overcrowded > 0 ? <Building2 className="size-4" aria-hidden="true" /> : <Flame className="size-4" aria-hidden="true" />}
                </span>
            </button>
        </section>
    );
}
