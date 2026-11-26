import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";
import { show } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { AlertTriangle, BookOpen, Building2, Calendar, Clock, GraduationCap, MapPin, Monitor, UserCheck, Users } from "lucide-react";
import type { ClassRow } from "../columns";
import { deliveryModeLabel, deliveryModeShortLabel, isOnlineDeliveryMode } from "../lib/class-defaults";
import type { IndexScheduleBlock } from "../workspace/types";
import { ClassEnrollment, classProgramLabel, semesterLabel } from "./class-list-details";
import { ClassRowActions, type ClassActionHandlers } from "./class-row-actions";

const DAY_ABBREV: Record<string, string> = {
    Monday: "M",
    Tuesday: "T",
    Wednesday: "W",
    Thursday: "Th",
    Friday: "F",
    Saturday: "S",
    Sunday: "Su",
};

function formatCompactScheduleChips(schedules: IndexScheduleBlock[] | undefined) {
    if (!schedules?.length) return [];

    const grouped = new Map<
        string,
        {
            key: string;
            days: string[];
            timeRange: string;
            component: string;
            hasConflict: boolean;
            isOvercrowded: boolean;
        }
    >();

    for (const block of schedules) {
        const start = block.start_time ? block.start_time.slice(0, 5) : "--:--";
        const end = block.end_time ? block.end_time.slice(0, 5) : "--:--";
        const component = block.component_type === "lab" ? "Lab" : "Lec";
        const key = `${start}-${end}|${component}`;
        const dayCode = DAY_ABBREV[block.day_of_week] ?? block.day_of_week.slice(0, 2);

        const existing = grouped.get(key);
        if (existing) {
            if (!existing.days.includes(dayCode)) {
                existing.days.push(dayCode);
            }
            existing.hasConflict = existing.hasConflict || block.has_conflict;
            existing.isOvercrowded = existing.isOvercrowded || block.is_overcrowded;
        } else {
            grouped.set(key, {
                key,
                days: [dayCode],
                timeRange: `${start}–${end}`,
                component,
                hasConflict: block.has_conflict,
                isOvercrowded: block.is_overcrowded,
            });
        }
    }

    return Array.from(grouped.values()).map((item) => ({
        key: item.key,
        label: `${item.days.join("")} ${item.timeRange} ${item.component}`,
        hasConflict: item.hasConflict,
        isOvercrowded: item.isOvercrowded,
    }));
}

function formatHours(value: number): string {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

export function ClassCard({
    classRow,
    selected,
    onSelect,
    ...actions
}: ClassActionHandlers & {
    classRow: ClassRow;
    selected: boolean;
    onSelect: (selected: boolean) => void;
}) {
    const isCollege = classRow.classification !== "shs";
    const delivery = classRow.delivery_summary ?? classRow.delivery_mode;
    const isOnline = isOnlineDeliveryMode(delivery);
    const isUnassignedFaculty = !classRow.faculty || classRow.faculty === "TBA";
    const scheduleHealth = classRow.schedule_health ?? null;
    const isRoomOvercrowded = Boolean(scheduleHealth?.is_room_overcrowded);
    const hasConflict = Boolean(scheduleHealth?.has_conflict);
    const waitlistCount = classRow.waitlist_count ?? 0;
    const scheduleChips = formatCompactScheduleChips(classRow.schedules);
    const showHoursBadge = scheduleHealth !== null && (scheduleHealth.required_hours > 0 || scheduleHealth.scheduled_hours > 0);

    return (
        <article
            className={cn(
                "group bg-card text-card-foreground relative flex min-w-0 flex-col overflow-hidden rounded-2xl border shadow-2xs transition-all duration-200 hover:shadow-sm",
                selected && "border-primary ring-primary/30 bg-primary/[0.02] ring-1",
            )}
        >
            {/* Visual Top Accent Banner */}
            <div className="from-primary/10 via-primary/5 to-muted/20 relative border-b bg-gradient-to-br p-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <label className="flex size-6 shrink-0 cursor-pointer items-center justify-center">
                            <Checkbox
                                checked={selected}
                                onCheckedChange={(value) => onSelect(Boolean(value))}
                                aria-label={`Select ${classRow.record_title}`}
                                className="rounded-[5px]"
                            />
                        </label>

                        <Badge variant="outline" className="bg-background/80 font-mono text-xs font-semibold backdrop-blur">
                            Section {classRow.section}
                        </Badge>
                    </div>

                    <div className="flex items-center gap-1.5">
                        <Badge variant="secondary" className="gap-1 text-[11px] font-medium">
                            {isCollege ? <BookOpen className="size-3" /> : <GraduationCap className="size-3" />}
                            {isCollege ? "College" : "SHS"}
                        </Badge>

                        <Badge
                            variant="outline"
                            className={cn(
                                "gap-1 text-[11px] font-medium",
                                isOnline && "border-indigo-500/30 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300",
                                !isOnline && "border-blue-500/30 bg-blue-50/50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300",
                            )}
                        >
                            {isOnline ? <Monitor className="size-3" /> : <Building2 className="size-3" />}
                            {deliveryModeLabel(delivery)}
                        </Badge>

                        {classRow.deleted_at ? (
                            <Badge variant="destructive" className="text-[11px]">
                                Archived
                            </Badge>
                        ) : null}
                    </div>
                </div>

                <div className="mt-3 space-y-1">
                    <Link
                        href={show.url(classRow.id)}
                        className="hover:text-primary focus-visible:outline-ring text-foreground block truncate rounded-sm text-lg font-bold tracking-tight transition-colors focus-visible:outline-2"
                    >
                        {classRow.record_title}
                    </Link>

                    <p className="text-muted-foreground truncate text-xs font-medium">
                        {classRow.subject_title || classRow.subject_code} · {classProgramLabel(classRow)}
                    </p>
                </div>
            </div>

            {/* Card Body Information */}
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="grid grid-cols-2 gap-2.5 text-xs">
                    {/* Faculty */}
                    <div className="bg-muted/20 rounded-xl border p-2.5">
                        <span className="text-muted-foreground flex items-center gap-1 font-medium">
                            <UserCheck className="text-primary size-3 shrink-0" />
                            Faculty
                        </span>
                        <p className={cn("mt-1 truncate font-semibold", isUnassignedFaculty && "text-muted-foreground font-normal")}>
                            {isUnassignedFaculty ? "Unassigned" : classRow.faculty}
                        </p>
                    </div>

                    {/* Room */}
                    <div
                        className={cn(
                            "bg-muted/20 rounded-xl border p-2.5",
                            isRoomOvercrowded && "border-rose-500/45 bg-rose-500/5",
                        )}
                        title={
                            isRoomOvercrowded
                                ? `Enrolled students (${classRow.students_count}) exceed room capacity (${classRow.room_capacity ?? "—"})`
                                : undefined
                        }
                    >
                        <span
                            className={cn(
                                "text-muted-foreground flex items-center justify-between gap-1 font-medium",
                                isRoomOvercrowded && "text-rose-700 dark:text-rose-300",
                            )}
                        >
                            <span className="flex items-center gap-1">
                                <MapPin className={cn("size-3 shrink-0", isRoomOvercrowded ? "text-rose-500" : "text-primary")} />
                                Location
                            </span>
                            {isRoomOvercrowded ? <AlertTriangle className="size-3 shrink-0 text-rose-500" /> : null}
                        </span>
                        <p className="text-foreground mt-1 truncate font-semibold">
                            {isOnline ? (
                                <span className="font-medium text-indigo-600 dark:text-indigo-400">{deliveryModeShortLabel(delivery)}</span>
                            ) : classRow.room ? (
                                <span className="inline-flex items-baseline gap-1">
                                    <span className="truncate">{classRow.room}</span>
                                    {classRow.room_capacity ? (
                                        <span
                                            className={cn(
                                                "shrink-0 text-[11px] font-medium tabular-nums",
                                                isRoomOvercrowded
                                                    ? "text-rose-600 dark:text-rose-400"
                                                    : "text-muted-foreground",
                                            )}
                                        >
                                            ({classRow.room_capacity} cap)
                                        </span>
                                    ) : null}
                                </span>
                            ) : (
                                <span className="text-muted-foreground font-normal">Unassigned</span>
                            )}
                        </p>
                    </div>

                    {/* Term */}
                    <div className="bg-muted/20 col-span-2 rounded-xl border p-2.5">
                        <span className="text-muted-foreground flex items-center gap-1 font-medium">
                            <Calendar className="text-primary size-3 shrink-0" />
                            Academic Term
                        </span>
                        <p className="text-foreground mt-0.5 truncate font-semibold">
                            {classRow.school_year} · {semesterLabel(classRow.semester)}
                        </p>
                    </div>

                    {/* Schedule & Health Strip */}
                    <div className="bg-muted/20 col-span-2 space-y-2 rounded-xl border p-2.5">
                        <div className="flex flex-wrap items-center justify-between gap-1.5">
                            <span className="text-muted-foreground flex items-center gap-1 font-medium">
                                <Clock className="text-primary size-3 shrink-0" />
                                Schedule &amp; Health
                            </span>
                            <div className="flex flex-wrap items-center gap-1">
                                {showHoursBadge ? (
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            "h-4 px-1.5 font-mono text-[10px] font-semibold tabular-nums",
                                            scheduleHealth.is_complete
                                                ? "border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                : "border-amber-500/35 bg-amber-500/10 text-amber-700 dark:text-amber-300",
                                        )}
                                    >
                                        {formatHours(scheduleHealth.scheduled_hours)}/{formatHours(scheduleHealth.required_hours)}h
                                    </Badge>
                                ) : null}
                                {hasConflict ? (
                                    <Badge
                                        variant="outline"
                                        title={scheduleHealth?.conflict_summary ?? "Schedule conflict detected"}
                                        className="h-4 gap-0.5 border-rose-500/45 bg-rose-500/15 px-1.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300"
                                    >
                                        <AlertTriangle className="size-2.5" />
                                        Clash
                                    </Badge>
                                ) : null}
                                {waitlistCount > 0 ? (
                                    <Badge
                                        variant="outline"
                                        className="h-4 gap-0.5 border-violet-500/35 bg-violet-500/10 px-1.5 text-[10px] font-semibold text-violet-700 tabular-nums dark:text-violet-300"
                                    >
                                        <Users className="size-2.5" />+{waitlistCount} WL
                                    </Badge>
                                ) : null}
                            </div>
                        </div>

                        {scheduleChips.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                                {scheduleChips.map((chip) => (
                                    <span
                                        key={chip.key}
                                        className={cn(
                                            "inline-flex items-center rounded-md border px-1.5 py-0.5 font-mono text-[10px] font-medium",
                                            chip.hasConflict || chip.isOvercrowded
                                                ? "border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                                                : "bg-background text-foreground border-border/80",
                                        )}
                                    >
                                        {chip.label}
                                    </span>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-[11px]">Unscheduled</p>
                        )}
                    </div>
                </div>

                {/* Enrollment Capacity & Actions */}
                <div className="mt-auto space-y-3.5 pt-1">
                    <ClassEnrollment classRow={classRow} />
                    <div className="border-t pt-3">
                        <ClassRowActions classRow={classRow} {...actions} />
                    </div>
                </div>
            </div>
        </article>
    );
}
