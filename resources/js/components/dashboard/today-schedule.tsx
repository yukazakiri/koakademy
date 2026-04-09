import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { IconArrowRight, IconClock, IconMapPin, IconMoodEmpty } from "@tabler/icons-react";

interface TodayScheduleEntry {
    id: number | string;
    start_time: string;
    end_time: string;
    subject_code: string;
    subject_title: string;
    section: string;
    room: string;
    course_codes?: string;
    classification?: string;
}

interface TodayScheduleProps {
    schedule: {
        day: string;
        entries: TodayScheduleEntry[];
    };
}

export function TodaySchedule({ schedule }: TodayScheduleProps) {
    const entries = schedule?.entries ?? [];
    const dayLabel = schedule?.day ?? "Today";

    // Logic to determine current/next class could go here
    // For now, we'll assume the first item is the "next" or "active" one for visual demo
    const activeEntryIndex = entries.length > 0 ? 0 : -1;

    return (
        <Card className="border-border/60 overflow-hidden shadow-sm">
            <CardHeader className="bg-muted/20 flex flex-col gap-2 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Today&apos;s Schedule</CardTitle>
                    <CardDescription>
                        You have {entries.length} classes scheduled for {dayLabel}.
                    </CardDescription>
                </div>
                <Badge variant="outline" className="bg-background rounded-full text-xs font-semibold">
                    {dayLabel}
                </Badge>
            </CardHeader>
            <CardContent className="p-0">
                {entries.length === 0 ? (
                    <div className="text-muted-foreground flex flex-col items-center justify-center gap-3 py-16 text-center">
                        <IconMoodEmpty className="text-primary/40 size-10" />
                        <div>
                            <p className="text-foreground text-sm font-medium">No classes scheduled</p>
                            <p className="text-xs">Enjoy your free time!</p>
                        </div>
                    </div>
                ) : (
                    <ScrollArea className="max-h-[420px]">
                        <div className="divide-border/60 divide-y">
                            {entries.map((entry, index) => {
                                const isActive = index === activeEntryIndex;
                                return (
                                    <div
                                        key={entry.id}
                                        className={`hover:bg-muted/40 flex flex-col gap-3 p-4 transition-colors sm:flex-row sm:items-center sm:justify-between ${isActive ? "bg-primary/5 border-l-primary border-l-2" : ""}`}
                                    >
                                        <div className="flex items-start gap-4">
                                            <div
                                                className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border text-sm font-bold ${isActive ? "border-primary/20 bg-primary text-primary-foreground shadow-sm" : "border-border bg-background text-muted-foreground"}`}
                                            >
                                                {entry.start_time.split(" ")[0]}
                                            </div>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <h4 className="text-foreground leading-none font-semibold">{entry.subject_title}</h4>
                                                    {isActive && (
                                                        <Badge variant="default" className="h-5 px-1.5 text-[10px]">
                                                            Next
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                    <span className="text-foreground/80 font-medium">{entry.subject_code}</span>
                                                    <span>•</span>
                                                    <span>Section {entry.section}</span>
                                                </div>
                                                <div className="text-muted-foreground flex items-center gap-3 pt-1 text-xs">
                                                    <span className="flex items-center gap-1">
                                                        <IconClock className="size-3" /> {entry.start_time} - {entry.end_time}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <IconMapPin className="size-3" /> {entry.room}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {isActive && (
                                            <Button size="sm" className="mt-2 w-full gap-1.5 sm:mt-0 sm:w-auto">
                                                Start Class <IconArrowRight className="size-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </ScrollArea>
                )}
            </CardContent>
        </Card>
    );
}
