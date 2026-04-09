import { WeeklyScheduleDay } from "@/components/dashboard/weekly-timetable";
import { Card, CardContent } from "@/components/ui/card";
import { IconCalendarEvent, IconClock, IconListDetails } from "@tabler/icons-react";
import { useMemo } from "react";
import { normalizeCurrentEntries } from "../hooks/use-schedule-diff";

interface ScheduleHeroProps {
    schedule: WeeklyScheduleDay[];
    nextClass: {
        subject_code: string;
        start_time: string;
        end_time: string;
        room: string;
        section: string;
    } | null;
}

export function ScheduleHero({ schedule, nextClass }: ScheduleHeroProps) {
    const metrics = useMemo(() => {
        const entries = normalizeCurrentEntries(schedule);
        const totalClasses = entries.length;

        const activeDays = new Set(entries.map((entry) => entry.day)).size;

        const durations = entries
            .map((entry) => {
                if (entry.start_minutes === null || entry.end_minutes === null) return 0;
                return Math.max(entry.end_minutes - entry.start_minutes, 0);
            })
            .reduce((acc, minutes) => acc + minutes, 0);

        return {
            totalClasses,
            activeDays,
            totalMinutes: durations,
        };
    }, [schedule]);

    const formatDurationHours = (minutes: number): string => {
        const hours = minutes / 60;
        if (hours < 1) {
            return `${minutes}m`;
        }
        const rounded = Math.round(hours * 10) / 10;
        return `${rounded}h`;
    };

    return (
        <Card className="border-border/60 shadow-sm">
            <CardContent className="p-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        title="Next class"
                        icon={<IconCalendarEvent className="size-4" />}
                        value={nextClass ? `${nextClass.subject_code} • Sec ${nextClass.section}` : "—"}
                        detail={nextClass ? `${nextClass.start_time} – ${nextClass.end_time} • ${nextClass.room}` : "No more classes today"}
                    />
                    <MetricCard
                        title="Teaching days"
                        icon={<IconCalendarEvent className="size-4" />}
                        value={`${metrics.activeDays}`}
                        detail="Days with at least one class"
                    />
                    <MetricCard
                        title="Total classes"
                        icon={<IconListDetails className="size-4" />}
                        value={`${metrics.totalClasses}`}
                        detail="Filtered by your search"
                    />
                    <MetricCard
                        title="Total hours"
                        icon={<IconClock className="size-4" />}
                        value={formatDurationHours(metrics.totalMinutes)}
                        detail="Based on scheduled times"
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function MetricCard({ title, value, detail, icon }: { title: string; value: string; detail: string; icon: React.ReactNode }) {
    return (
        <div className="border-border/60 bg-card hover:border-border/80 rounded-xl border p-4 shadow-sm transition-colors">
            <div className="flex items-center justify-between gap-3">
                <div className="space-y-1">
                    <div className="text-muted-foreground text-sm">{title}</div>
                    <div className="text-foreground max-w-[150px] truncate text-base font-semibold" title={value}>
                        {value}
                    </div>
                </div>
                <div className="bg-muted text-muted-foreground rounded-lg p-2">{icon}</div>
            </div>
            <div className="text-muted-foreground mt-2 truncate text-xs" title={detail}>
                {detail}
            </div>
        </div>
    );
}
