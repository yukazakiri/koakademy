import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { HoverCard, HoverCardContent, HoverCardTrigger } from "@/components/ui/hover-card";
import { Separator } from "@/components/ui/separator";
import { Clock, ExternalLink, FileText, LayoutGrid, Printer, User as UserIcon } from "lucide-react";
import type { StudentDetail } from "../types";

const SCHEDULE_DAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"] as const;

interface AcademicScheduleDashboardProps {
    student: StudentDetail;
    hoveredSubject: string | null;
    setHoveredSubject: (subjectCode: string | null) => void;
    onOpenClass: (classId: number) => void;
    onPrintTor: () => void;
    onPrintSchedule: () => void;
}

export function AcademicScheduleDashboard({
    student,
    hoveredSubject,
    setHoveredSubject,
    onOpenClass,
    onPrintTor,
    onPrintSchedule,
}: AcademicScheduleDashboardProps) {
    return (
        <div className="space-y-6">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h3 className="text-xl font-bold tracking-tight">Academic Schedule</h3>
                    <p className="text-muted-foreground text-sm">Interactive weekly class matrix and subject load</p>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" className="gap-2" onClick={onPrintTor}>
                        <FileText className="h-4 w-4" />
                        Print TOR
                    </Button>
                    <Button variant="outline" size="sm" className="gap-2" onClick={onPrintSchedule}>
                        <Printer className="h-4 w-4" />
                        Print Schedule
                    </Button>
                </div>
            </div>

            <Card className="bg-background overflow-hidden border shadow-sm">
                <div className="flex h-[800px] flex-col lg:flex-row">
                    <div className="bg-muted/10 flex w-full flex-col border-r lg:w-80">
                        <div className="bg-muted/20 border-b p-4">
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-sm font-semibold">Enrolled Subjects</h4>
                                <Badge variant="secondary">{student.current_enrolled_classes.length}</Badge>
                            </div>
                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                <Clock className="h-3 w-3" />
                                <span>
                                    Total Units: {student.current_enrolled_classes.reduce((acc, enrolledClass) => acc + enrolledClass.units, 0)}
                                </span>
                            </div>
                        </div>
                        <div className="flex-1 space-y-2 overflow-y-auto p-3">
                            {student.current_enrolled_classes.map((enrolledClass, index) => (
                                <div
                                    key={`${enrolledClass.class_id}-${index}`}
                                    className={`group cursor-pointer rounded-lg border p-3 transition-all hover:shadow-md ${
                                        hoveredSubject === enrolledClass.subject_code
                                            ? "ring-primary bg-background ring-2"
                                            : "bg-card hover:bg-accent/50"
                                    } `}
                                    style={{ borderLeftColor: enrolledClass.color, borderLeftWidth: "4px" }}
                                    onMouseEnter={() => setHoveredSubject(enrolledClass.subject_code)}
                                    onMouseLeave={() => setHoveredSubject(null)}
                                    onClick={() => onOpenClass(enrolledClass.class_id)}
                                >
                                    <div className="mb-1 flex items-start justify-between">
                                        <div className="truncate text-sm font-bold" title={enrolledClass.subject_code}>
                                            {enrolledClass.subject_code}
                                        </div>
                                        <Badge variant="outline" className="bg-background/50 h-5 text-[10px]">
                                            {enrolledClass.section}
                                        </Badge>
                                    </div>
                                    <div className="text-muted-foreground mb-2 line-clamp-1 text-xs" title={enrolledClass.subject_title}>
                                        {enrolledClass.subject_title}
                                    </div>
                                    <div className="text-muted-foreground flex items-center gap-2 text-[10px]">
                                        <UserIcon className="h-3 w-3" />
                                        <span className="truncate">{enrolledClass.faculty.split(",")[0]}</span>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between">
                                        <Badge variant="secondary" className="h-5 text-[10px]">
                                            {enrolledClass.units} Units
                                        </Badge>
                                        <Button variant="ghost" size="icon" className="h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100">
                                            <ExternalLink className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {student.current_enrolled_classes.length === 0 && (
                                <div className="text-muted-foreground py-8 text-center text-sm">No subjects enrolled.</div>
                            )}
                        </div>
                    </div>

                    <div className="bg-background flex h-full flex-1 flex-col overflow-hidden">
                        <div className="bg-background/95 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-20 flex h-12 items-center border-b backdrop-blur">
                            <div className="text-muted-foreground bg-muted/5 flex h-full w-16 flex-shrink-0 items-center justify-center border-r text-center text-xs font-medium">
                                Time
                            </div>
                            <div className="grid h-full flex-1 grid-cols-7">
                                {SCHEDULE_DAYS.map((day) => (
                                    <div
                                        key={day}
                                        className="text-muted-foreground hover:bg-muted/10 flex h-full cursor-default items-center justify-center border-r text-sm font-semibold transition-colors last:border-r-0"
                                    >
                                        {day}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="relative flex-1 overflow-y-auto">
                            <div className="flex min-h-[800px]">
                                <div className="bg-muted/5 relative w-16 flex-shrink-0 border-r">
                                    {Array.from({ length: 15 }, (_, i) => i + 7).map((hour) => (
                                        <div
                                            key={hour}
                                            className="text-muted-foreground absolute w-full text-center text-[10px] font-medium"
                                            style={{ top: `${((hour - 7) / 14) * 100}%`, transform: "translateY(-50%)" }}
                                        >
                                            {hour > 12 ? hour - 12 : hour} {hour >= 12 ? "PM" : "AM"}
                                        </div>
                                    ))}
                                </div>

                                <div className="relative grid flex-1 grid-cols-7">
                                    <div className="pointer-events-none absolute inset-0 z-0">
                                        {Array.from({ length: 14 }, (_, i) => i + 7).map((hour) => (
                                            <div
                                                key={hour}
                                                className="border-muted/30 absolute w-full border-b border-dashed"
                                                style={{ top: `${((hour - 7) / 14) * 100}%` }}
                                            />
                                        ))}
                                    </div>

                                    {SCHEDULE_DAYS.map((day) => (
                                        <div key={day} className="group hover:bg-muted/5 relative h-full border-r transition-colors last:border-r-0">
                                            {student.current_enrolled_classes.map((enrolledClass) => {
                                                const isHighlighted = hoveredSubject === enrolledClass.subject_code;
                                                const isDimmed = hoveredSubject && hoveredSubject !== enrolledClass.subject_code;

                                                return enrolledClass.schedules
                                                    .filter((schedule) => {
                                                        const normalizedDay = schedule.day.toLowerCase();
                                                        return normalizedDay === day.toLowerCase() || normalizedDay.startsWith(day.toLowerCase());
                                                    })
                                                    .map((schedule, scheduleIndex) => {
                                                        const startH = parseInt(schedule.start_time.split(":")[0]);
                                                        const startM = parseInt(schedule.start_time.split(":")[1]);
                                                        const endH = parseInt(schedule.end_time.split(":")[0]);
                                                        const endM = parseInt(schedule.end_time.split(":")[1]);

                                                        const startDecimal = startH + startM / 60;
                                                        const endDecimal = endH + endM / 60;
                                                        const duration = endDecimal - startDecimal;

                                                        if (startDecimal < 7 || startDecimal >= 21) {
                                                            return null;
                                                        }

                                                        const top = ((startDecimal - 7) / 14) * 100;
                                                        const height = (duration / 14) * 100;

                                                        return (
                                                            <HoverCard key={`${day}-${enrolledClass.class_id}-${scheduleIndex}`}>
                                                                <HoverCardTrigger asChild>
                                                                    <div
                                                                        className={`absolute inset-x-0.5 flex cursor-pointer flex-col gap-0.5 overflow-hidden rounded-md border-l-4 p-1.5 transition-all duration-200 ${
                                                                            isHighlighted
                                                                                ? "ring-primary z-20 scale-[1.02] shadow-xl ring-2"
                                                                                : "z-10 shadow-sm hover:z-20 hover:scale-[1.01] hover:shadow-md"
                                                                        } ${isDimmed ? "opacity-30 grayscale" : "opacity-100"} `}
                                                                        style={{
                                                                            top: `${top}%`,
                                                                            height: `${height}%`,
                                                                            backgroundColor: `${enrolledClass.color}15`,
                                                                            borderLeftColor: enrolledClass.color,
                                                                            borderTop: `1px solid ${enrolledClass.color}30`,
                                                                            borderRight: `1px solid ${enrolledClass.color}30`,
                                                                            borderBottom: `1px solid ${enrolledClass.color}30`,
                                                                        }}
                                                                        onClick={() => onOpenClass(enrolledClass.class_id)}
                                                                    >
                                                                        <div
                                                                            className="truncate text-[10px] leading-tight font-bold"
                                                                            style={{ color: enrolledClass.color }}
                                                                        >
                                                                            {enrolledClass.subject_code}
                                                                        </div>
                                                                        <div className="text-foreground/80 flex items-center gap-1 truncate text-[9px] font-medium">
                                                                            <div className="bg-foreground/20 h-1.5 w-1.5 flex-shrink-0 rounded-full" />
                                                                            {schedule.room}
                                                                        </div>
                                                                        {duration > 1 && (
                                                                            <div className="text-muted-foreground mt-auto truncate text-[9px] opacity-80">
                                                                                {enrolledClass.faculty.split(" ")[0]}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </HoverCardTrigger>
                                                                <HoverCardContent
                                                                    className="w-80 overflow-hidden border-t-4 p-0 shadow-xl"
                                                                    style={{ borderTopColor: enrolledClass.color }}
                                                                    align="start"
                                                                    side="right"
                                                                >
                                                                    <div className="bg-background p-4">
                                                                        <div className="mb-2 flex items-start justify-between">
                                                                            <div>
                                                                                <h4 className="text-lg font-bold">{enrolledClass.subject_code}</h4>
                                                                                <p className="text-muted-foreground text-xs">
                                                                                    {enrolledClass.subject_title}
                                                                                </p>
                                                                            </div>
                                                                            <Badge
                                                                                variant="outline"
                                                                                style={{
                                                                                    borderColor: enrolledClass.color,
                                                                                    color: enrolledClass.color,
                                                                                    backgroundColor: `${enrolledClass.color}10`,
                                                                                }}
                                                                            >
                                                                                {enrolledClass.units} Units
                                                                            </Badge>
                                                                        </div>

                                                                        <Separator className="my-3" />

                                                                        <div className="space-y-3 text-sm">
                                                                            <div className="grid grid-cols-2 gap-3">
                                                                                <div>
                                                                                    <span className="text-muted-foreground mb-1 block text-xs tracking-wider uppercase">
                                                                                        Time
                                                                                    </span>
                                                                                    <div className="flex items-center gap-1.5 font-medium">
                                                                                        <Clock className="h-3.5 w-3.5" />
                                                                                        {schedule.start_time} - {schedule.end_time}
                                                                                    </div>
                                                                                </div>
                                                                                <div>
                                                                                    <span className="text-muted-foreground mb-1 block text-xs tracking-wider uppercase">
                                                                                        Room
                                                                                    </span>
                                                                                    <div className="flex items-center gap-1.5 font-medium">
                                                                                        <LayoutGrid className="h-3.5 w-3.5" />
                                                                                        {schedule.room}
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div>
                                                                                <span className="text-muted-foreground mb-1 block text-xs tracking-wider uppercase">
                                                                                    Faculty
                                                                                </span>
                                                                                <div className="bg-muted/30 flex items-center gap-1.5 rounded-md p-2 font-medium">
                                                                                    <Avatar className="h-6 w-6">
                                                                                        <AvatarFallback className="text-[10px]">
                                                                                            {enrolledClass.faculty.charAt(0)}
                                                                                        </AvatarFallback>
                                                                                    </Avatar>
                                                                                    {enrolledClass.faculty}
                                                                                </div>
                                                                            </div>

                                                                            <div className="pt-2">
                                                                                <Button
                                                                                    size="sm"
                                                                                    className="w-full gap-2"
                                                                                    variant="secondary"
                                                                                    onClick={() => onOpenClass(enrolledClass.class_id)}
                                                                                >
                                                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                                                    Manage Class
                                                                                </Button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </HoverCardContent>
                                                            </HoverCard>
                                                        );
                                                    });
                                            })}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    );
}
