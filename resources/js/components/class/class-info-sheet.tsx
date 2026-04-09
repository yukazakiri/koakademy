import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { IconCalendar, IconClock, IconInfoCircle, IconMapPin, IconSchool, IconUserCheck, IconUsers } from "@tabler/icons-react";

interface ClassInfoSheetProps {
    classData: {
        subject_code: string;
        subject_title: string;
        section: string;
        school_year: string;
        semester: string;
        room: string;
        classification: string;
        students_count: number;
        start_date?: string | null;
    };
    teacher: {
        name: string;
        email?: string | null;
    };
    schedule: Array<{
        day: string;
        start: string;
        end: string;
        room: string;
    }>;
}

export function ClassInfoSheet({ classData, teacher, schedule }: ClassInfoSheetProps) {
    // Generate a consistent accent color based on subject code
    const getAccentColor = (str: string) => {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const hue = Math.abs(hash) % 360;
        return `hsl(${hue}, 70%, 50%)`;
    };

    const accentColor = getAccentColor(classData.subject_code);

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground rounded-full">
                    <IconInfoCircle className="size-5" />
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="border-border/50 w-full border-l p-0 sm:max-w-md">
                <ScrollArea className="h-full">
                    {/* Visual Header */}
                    <div className="bg-muted/30 relative overflow-hidden px-6 pt-12 pb-6">
                        <div
                            className="absolute -top-10 -right-10 h-40 w-40 rounded-full opacity-20 blur-3xl"
                            style={{ backgroundColor: accentColor }}
                        />
                        <div
                            className="absolute bottom-0 -left-10 h-32 w-32 rounded-full opacity-20 blur-3xl"
                            style={{ backgroundColor: accentColor }}
                        />

                        <div className="relative z-10 space-y-4">
                            <Badge variant="outline" className="bg-background/50 border-primary/20 text-primary backdrop-blur">
                                {classData.classification === "shs" ? "Senior High" : "College"}
                            </Badge>
                            <div>
                                <h2 className="text-2xl leading-tight font-bold tracking-tight">{classData.subject_title}</h2>
                                <p className="text-muted-foreground mt-1 font-medium">
                                    {classData.subject_code} • {classData.section}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="secondary" className="bg-background/50 backdrop-blur">
                                    {classData.semester}
                                </Badge>
                                <Badge variant="secondary" className="bg-background/50 backdrop-blur">
                                    {classData.school_year}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-8 p-6">
                        {/* Key Stats Grid */}
                        <section className="space-y-3">
                            <h3 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Details</h3>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="border-border/50 bg-card rounded-xl border p-3 shadow-sm">
                                    <div className="mb-2 flex items-center gap-3">
                                        <div className="flex size-8 items-center justify-center rounded-full bg-blue-500/10 text-blue-600">
                                            <IconUsers className="size-4" />
                                        </div>
                                        <span className="text-muted-foreground text-xs font-medium">Students</span>
                                    </div>
                                    <p className="text-lg font-semibold">{classData.students_count}</p>
                                    <p className="text-muted-foreground text-xs">Enrolled</p>
                                </div>

                                <div className="border-border/50 bg-card rounded-xl border p-3 shadow-sm">
                                    <div className="mb-2 flex items-center gap-3">
                                        <div className="flex size-8 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
                                            <IconUserCheck className="size-4" />
                                        </div>
                                        <span className="text-muted-foreground text-xs font-medium">Teacher</span>
                                    </div>
                                    <p className="truncate text-sm font-semibold" title={teacher.name}>
                                        {teacher.name}
                                    </p>
                                    <p className="text-muted-foreground text-xs">Instructor</p>
                                </div>

                                <div className="border-border/50 bg-card rounded-xl border p-3 shadow-sm">
                                    <div className="mb-2 flex items-center gap-3">
                                        <div className="flex size-8 items-center justify-center rounded-full bg-purple-500/10 text-purple-600">
                                            <IconMapPin className="size-4" />
                                        </div>
                                        <span className="text-muted-foreground text-xs font-medium">Room</span>
                                    </div>
                                    <p className="text-lg font-semibold">{classData.room || "TBA"}</p>
                                    <p className="text-muted-foreground text-xs">Default</p>
                                </div>

                                <div className="border-border/50 bg-card rounded-xl border p-3 shadow-sm">
                                    <div className="mb-2 flex items-center gap-3">
                                        <div className="flex size-8 items-center justify-center rounded-full bg-amber-500/10 text-amber-600">
                                            <IconSchool className="size-4" />
                                        </div>
                                        <span className="text-muted-foreground text-xs font-medium">Type</span>
                                    </div>
                                    <p className="truncate text-sm font-semibold">Lecture</p>
                                    <p className="text-muted-foreground text-xs">Class Type</p>
                                </div>
                            </div>
                        </section>

                        <Separator />

                        {/* Schedule Timeline */}
                        <section className="space-y-4">
                            <h3 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Weekly Schedule</h3>
                            {schedule.length > 0 ? (
                                <div className="before:bg-border relative space-y-4 pl-4 before:absolute before:inset-y-0 before:left-0 before:w-px">
                                    {schedule.map((slot, i) => (
                                        <div key={i} className="relative">
                                            <div className="border-background bg-primary ring-border absolute top-1.5 -left-[21px] size-2.5 rounded-full border-2 ring-1" />
                                            <div className="border-border/50 bg-card rounded-lg border p-4 shadow-sm transition-all hover:shadow-md">
                                                <div className="mb-2 flex items-center justify-between">
                                                    <span className="text-primary font-semibold">{slot.day}</span>
                                                    <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                                                        {slot.room}
                                                    </Badge>
                                                </div>
                                                <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                                    <IconClock className="size-4" />
                                                    <span>
                                                        {slot.start} - {slot.end}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-muted-foreground flex flex-col items-center justify-center py-8 text-center">
                                    <IconCalendar className="mb-2 size-8 opacity-20" />
                                    <p className="text-sm">No schedule set for this class.</p>
                                </div>
                            )}
                        </section>
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}
