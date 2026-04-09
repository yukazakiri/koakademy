import { getTimetableColorTokens } from "@/components/dashboard/timetable-colors";
import { WeeklyScheduleDay } from "@/components/dashboard/weekly-timetable";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { IconClock, IconGridDots } from "@tabler/icons-react";
import { useMemo } from "react";
import { DiffStatus } from "../hooks/use-schedule-diff";
import { DAYS } from "../hooks/use-schedule-filter";

interface AgendaViewProps {
    schedule: WeeklyScheduleDay[];
    statusById: Map<string, DiffStatus>;
    conflicts: Set<string>;
}

export function AgendaView({ schedule, statusById, conflicts }: AgendaViewProps) {
    const normalized = useMemo(() => {
        const map = new Map(schedule.map((day) => [day.day.toLowerCase(), day.entries ?? []]));
        return DAYS.map((day) => ({
            day,
            entries: (map.get(day.toLowerCase()) ?? []).slice(),
        }));
    }, [schedule]);

    const hasAny = normalized.some((d) => d.entries.length);

    if (!hasAny) {
        return (
            <div className="text-muted-foreground bg-muted/5 rounded-xl border border-dashed p-12 text-center text-sm">
                No schedule entries match your filters.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {normalized.map((day) => {
                if (!day.entries.length) return null;

                return (
                    <div key={day.day} className="space-y-3">
                        <div className="bg-background/95 sticky top-0 z-20 flex items-center gap-3 border-b py-2 backdrop-blur">
                            <h3 className="text-lg font-semibold">{day.day}</h3>
                            <Badge variant="secondary" className="h-5 rounded-full px-2 text-[10px]">
                                {day.entries.length} Classes
                            </Badge>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {day.entries.map((entry) => {
                                const id = String(entry.id);
                                const status = conflicts.has(id) ? "conflict" : (statusById.get(id) ?? "unchanged");
                                const colors = getTimetableColorTokens(`${entry.subject_code}-${entry.section}-${entry.room}`);

                                return (
                                    <Link
                                        key={entry.id}
                                        href={entry.class_id ? `/classes/${entry.class_id}` : "/classes"}
                                        className={cn(
                                            "group bg-card hover:border-primary/20 relative flex flex-col gap-3 rounded-2xl border p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md active:scale-[0.98] sm:p-5",
                                            status === "added" && "border-emerald-500/20 bg-emerald-500/5 ring-1 ring-emerald-500/50",
                                            status === "changed" && "border-amber-500/20 bg-amber-500/5 ring-1 ring-amber-500/50",
                                            status === "conflict" && "border-rose-500/20 bg-rose-500/5 ring-1 ring-rose-500/60",
                                        )}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex flex-col gap-0.5">
                                                <span className="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                                    {entry.subject_code}
                                                </span>
                                                <h4 className="text-foreground group-hover:text-primary line-clamp-2 text-lg leading-tight font-bold transition-colors">
                                                    {entry.subject_title}
                                                </h4>
                                            </div>

                                            {status !== "unchanged" && (
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        "h-5 border-transparent px-1.5 text-[10px]",
                                                        status === "added" && "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
                                                        status === "changed" && "bg-amber-500/15 text-amber-800 dark:text-amber-300",
                                                        status === "conflict" && "bg-rose-500/15 text-rose-700 dark:text-rose-300",
                                                    )}
                                                >
                                                    {status === "added" ? "New" : status === "changed" ? "Moved" : "Conflict"}
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-3 text-sm">
                                            <div className="bg-muted/40 flex items-center gap-1.5 rounded-md px-2 py-1">
                                                <IconClock className="size-3.5 opacity-70" />
                                                <span className="text-foreground/80 text-xs font-medium sm:text-sm">
                                                    {entry.start_time} – {entry.end_time}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1.5 px-1">
                                                <IconGridDots className="size-3.5 opacity-70" />
                                                <span className="text-xs sm:text-sm">{entry.room}</span>
                                            </div>
                                            <div className="flex items-center gap-1.5 px-1">
                                                <span className="bg-primary/20 size-1.5 rounded-full" />
                                                <span className="text-xs sm:text-sm">Sec {entry.section}</span>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
