import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { cn } from "@/lib/utils";
import { Calendar, ChevronDown } from "lucide-react";
import { DAYS, END_HOUR, getMinutesFromTime, SCHEDULE_COLORS, START_HOUR, TOTAL_MINUTES, type SubjectEnrollment } from "./types";

interface SchedulePreviewCardProps {
    subjectsEnrolled: SubjectEnrollment[];
}

export function SchedulePreviewCard({ subjectsEnrolled }: SchedulePreviewCardProps) {
    if (!subjectsEnrolled.some((s) => s.class_id)) {
        return null;
    }

    return (
        <Collapsible defaultOpen>
            <Card>
                <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="bg-primary text-primary-foreground flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">
                                <Calendar className="h-4 w-4" />
                            </div>
                            <div>
                                <CardTitle className="text-lg">Class Schedule</CardTitle>
                                <CardDescription>Schedule matrix of enrolled subjects</CardDescription>
                            </div>
                        </div>
                        <CollapsibleTrigger asChild>
                            <Button variant="ghost" size="sm" className="w-9 p-0">
                                <ChevronDown className="h-4 w-4" />
                                <span className="sr-only">Toggle</span>
                            </Button>
                        </CollapsibleTrigger>
                    </div>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <div className="flex h-[600px] min-w-[800px]">
                                <div className="bg-muted/30 w-16 flex-none border-r">
                                    <div className="bg-muted/50 h-8 border-b" />
                                    {Array.from({ length: END_HOUR - START_HOUR }).map((_, i) => (
                                        <div key={i} className="text-muted-foreground relative h-[calc(100%/(14))] border-b p-1 text-right text-[10px]">
                                            <span className="relative -top-2">
                                                {(START_HOUR + i) % 12 || 12} {START_HOUR + i >= 12 ? "PM" : "AM"}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                <div className="grid h-full flex-1 grid-cols-6 divide-x">
                                    {DAYS.map((day) => (
                                        <div key={day} className="relative flex h-full min-w-[100px] flex-col">
                                            <div className="bg-muted/50 sticky top-0 z-10 flex h-8 flex-none items-center justify-center border-b text-xs font-medium">
                                                {day.slice(0, 3)}
                                            </div>
                                            <div className="relative flex-1">
                                                {Array.from({ length: END_HOUR - START_HOUR }).map((_, i) => (
                                                    <div key={i} className="border-muted/50 h-[calc(100%/(14))] border-b border-dashed" />
                                                ))}
                                                {subjectsEnrolled
                                                    .filter((s) => s.class_id)
                                                    .map((subject, index) => {
                                                        const section = subject.section_options.find((opt) => opt.id === subject.class_id);
                                                        if (!section?.schedules) return null;

                                                        return section.schedules
                                                            .filter((sched) => sched.day.toLowerCase() === day.toLowerCase())
                                                            .map((sched, schedIndex) => {
                                                                const startMin = getMinutesFromTime(sched.start_time) - START_HOUR * 60;
                                                                const endMin = getMinutesFromTime(sched.end_time) - START_HOUR * 60;
                                                                const top = (startMin / TOTAL_MINUTES) * 100;
                                                                const height = ((endMin - startMin) / TOTAL_MINUTES) * 100;

                                                                return (
                                                                    <div
                                                                        key={`${subject.id}-${schedIndex}`}
                                                                        className={cn(
                                                                            "absolute left-[3%] w-[94%] cursor-help overflow-hidden rounded border p-1 text-[10px] leading-tight shadow-sm transition-all hover:z-20 hover:scale-[1.02]",
                                                                            SCHEDULE_COLORS[index % SCHEDULE_COLORS.length],
                                                                        )}
                                                                        style={{ top: `${top}%`, height: `${height}%` }}
                                                                        title={`${subject.subject_code}\n${sched.start_time} - ${sched.end_time}\n${sched.room || "TBA"}`}
                                                                    >
                                                                        <div className="truncate font-bold">{subject.subject_code}</div>
                                                                        <div className="truncate opacity-90">{sched.room || "TBA"}</div>
                                                                        <div className="truncate text-[9px] opacity-75">{section.section}</div>
                                                                    </div>
                                                                );
                                                            });
                                                    })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}
