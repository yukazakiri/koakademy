import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Link } from "@inertiajs/react";
import { IconAlertTriangle, IconCalendar, IconClock, IconMapPin } from "@tabler/icons-react";
import type { ScheduleEvent } from "../hooks/use-class-schedule";

interface ClassesStatusProps {
    nextUp: { event: ScheduleEvent; start: Date; in: string } | null;
    stats: {
        scheduledCount: number;
        unscheduledCount: number;
        conflictCount: number;
        nearCapacityCount: number;
    };
    baseUrl?: string;
}

export function ClassesStatus({ nextUp, stats, baseUrl = "/classes" }: ClassesStatusProps) {
    // If there's nothing urgent (no next class, no conflicts/unscheduled), show nothing or a minimal "All good" state?
    // The user wants "enjoyable" and "minimal".
    // Let's show the Next Up card if it exists.
    // And show alerts for conflicts/unscheduled if they exist.

    const hasIssues = stats.conflictCount > 0 || stats.unscheduledCount > 0;

    if (!nextUp && !hasIssues) {
        return null; // Minimal: don't show anything if there's nothing pressing.
    }

    return (
        <div className="grid gap-4 md:grid-cols-1 lg:grid-cols-2">
            {nextUp && (
                <div className="from-primary via-primary/90 to-primary/80 relative overflow-hidden rounded-xl bg-gradient-to-br p-1 shadow-lg lg:col-span-2">
                    <div className="pointer-events-none absolute top-0 right-0 -mt-10 -mr-10 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
                    <div className="bg-background/5 flex flex-col items-center justify-between gap-4 rounded-lg p-4 backdrop-blur-sm sm:flex-row sm:p-6">
                        <div className="flex-1 space-y-1 text-center sm:text-left">
                            <div className="mb-2 flex items-center justify-center gap-2 sm:justify-start">
                                <Badge variant="secondary" className="border-0 bg-white/20 text-white shadow-none hover:bg-white/30">
                                    Next Class in {nextUp.in}
                                </Badge>
                                {nextUp.event.roomLabel && (
                                    <span className="flex items-center gap-1 text-xs text-white/90">
                                        <IconMapPin className="h-3 w-3" />
                                        {nextUp.event.roomLabel}
                                    </span>
                                )}
                            </div>
                            <h3 className="text-xl font-bold tracking-tight text-white sm:text-2xl">{nextUp.event.classItem.subject_title}</h3>
                            <p className="font-medium text-white/80">
                                {nextUp.event.classItem.subject_code} • Section {nextUp.event.classItem.section}
                            </p>
                            <div className="flex items-center justify-center gap-2 text-sm text-white/70 sm:justify-start">
                                <IconClock className="h-4 w-4" />
                                <span>
                                    {nextUp.event.startLabel} - {nextUp.event.endLabel}
                                </span>
                            </div>
                        </div>

                        <div className="flex shrink-0 gap-3">
                            <Button asChild variant="secondary" className="font-semibold whitespace-nowrap shadow-sm">
                                <Link href={`${baseUrl}/${nextUp.event.classItem.id}`}>View Class</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {stats.conflictCount > 0 && (
                <div className="flex items-center gap-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900/30 dark:bg-red-900/10 dark:text-red-200">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/20">
                        <IconAlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p className="font-semibold">Schedule Conflicts Detected</p>
                        <p className="text-sm opacity-90">
                            You have {stats.conflictCount} class{stats.conflictCount === 1 ? "" : "es"} with conflicting schedules.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        className="ml-auto border-red-200 font-medium hover:bg-red-100 hover:text-red-900 dark:border-red-800 dark:hover:bg-red-900/30"
                        asChild
                    >
                        <Link href="?view=board&conflicts=true" preserveState>
                            Review
                        </Link>
                    </Button>
                </div>
            )}

            {stats.unscheduledCount > 0 && (
                <div className="flex items-center gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/30 dark:bg-amber-900/10 dark:text-amber-200">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/20">
                        <IconCalendar className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p className="font-semibold">Unscheduled Classes</p>
                        <p className="text-sm opacity-90">
                            {stats.unscheduledCount} class{stats.unscheduledCount === 1 ? "" : "es"} still need a schedule.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        className="ml-auto border-amber-200 font-medium hover:bg-amber-100 hover:text-amber-900 dark:border-amber-800 dark:hover:bg-amber-900/30"
                        asChild
                    >
                        <Link href="?view=board&unscheduled=true" preserveState>
                            Schedule
                        </Link>
                    </Button>
                </div>
            )}
        </div>
    );
}
