import { WeeklySchedule, type WeeklyScheduleEvent } from "@/components/administrators/classes/weekly-schedule";
import { Button } from "@/components/ui/button";
import { Pencil } from "lucide-react";
import { getScheduleEntriesForDay, parseTimeToMinutes, scheduleDayOrder } from "./schedule-utils";
import type { ClassShowData } from "./types";

export function ScheduleOverview({ classItem, canUpdate, onManage }: { classItem: ClassShowData; canUpdate: boolean; onManage: () => void }) {
    const online = classItem.delivery_mode === "online";
    const events: WeeklyScheduleEvent[] = scheduleDayOrder.flatMap((day) =>
        getScheduleEntriesForDay(classItem.schedule, day).map((entry, index) => ({
            id: `${day}-${index}`,
            day,
            start: entry.start_time,
            end: entry.end_time,
            title: "",
            location: (entry.delivery_mode ?? classItem.delivery_mode) === "online" ? undefined : entry.room?.name || "Room not assigned",
            online: (entry.delivery_mode ?? classItem.delivery_mode) === "online",
            conflict: entry.has_conflict,
        })),
    );
    const hours = events.reduce((total, event) => {
        const duration = parseTimeToMinutes(event.end) - parseTimeToMinutes(event.start);
        return total + (Number.isFinite(duration) && duration > 0 ? duration / 60 : 0);
    }, 0);
    const conflicts = events.filter((event) => event.conflict).length;
    const count = classItem.students_count;
    const capacity = classItem.maximum_slots;
    const overCapacity = capacity > 0 && count > capacity;

    return (
        <div className="min-w-0 space-y-6">
            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                <div className="min-w-0 space-y-1">
                    <dt className="text-muted-foreground">Faculty</dt>
                    <dd className="font-medium break-words">{classItem.faculty?.name || "Not assigned"}</dd>
                    {classItem.faculty?.email && <dd className="text-muted-foreground break-all">{classItem.faculty.email}</dd>}
                </div>
                <div className="space-y-1">
                    <dt className="text-muted-foreground">Enrollment</dt>
                    <dd className="font-medium tabular-nums">
                        {capacity > 0 ? `${count} of ${capacity} places filled` : `${count} students enrolled`}
                    </dd>
                    {overCapacity && <dd className="text-destructive">{count - capacity} above capacity</dd>}
                </div>
            </dl>

            <section aria-labelledby="weekly-schedule-title" className="bg-card min-w-0 overflow-hidden rounded-xl border">
                <div className="flex flex-wrap items-start justify-between gap-4 p-4 sm:p-6">
                    <div className="space-y-1">
                        <h2 id="weekly-schedule-title" className="text-base font-semibold">
                            Weekly schedule
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            {events.length
                                ? `${events.length} ${events.length === 1 ? "session" : "sessions"} · ${Number(hours.toFixed(2))} ${hours === 1 ? "hour" : "hours"} per week`
                                : "Add sessions to set the weekly schedule."}
                        </p>
                        {conflicts > 0 && (
                            <p role="status" className="text-destructive text-sm">
                                {conflicts} {conflicts === 1 ? "session has a conflict" : "sessions have conflicts"}. Review the affected times.
                            </p>
                        )}
                    </div>
                    {canUpdate && (
                        <Button variant="outline" onClick={onManage} className="h-10 px-3">
                            <Pencil className="size-4" aria-hidden="true" />
                            {events.length ? "Edit schedule" : "Add session"}
                        </Button>
                    )}
                </div>
                <div className="border-t">
                    <WeeklySchedule
                        events={events}
                        emptyMessage={
                            online
                                ? "Online sessions will appear here once scheduled."
                                : "Session days, times, and rooms will appear here once scheduled."
                        }
                    />
                </div>
            </section>
        </div>
    );
}
