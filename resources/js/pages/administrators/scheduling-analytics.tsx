import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import type { User } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import {
    AlertTriangle,
    BookOpen,
    Building2,
    Calendar,
    Clock,
    GraduationCap,
    Grid3X3,
    LayoutGrid,
    List,
    Loader2,
    MapPin,
    RefreshCw,
    Search,
    Sparkles,
    User as UserIcon,
    Users,
} from "lucide-react";
import * as React from "react";
import { route } from "ziggy-js";

// Types
type ScheduleEntry = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    time_range: string;
    room: string | null;
    room_id?: number | null;
};

type ClassScheduleData = {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    grade_level: string | null;
    faculty_id?: string | null;
    faculty_name: string | null;
    room_name: string | null;
    courses: string;
    course_ids: number[];
    student_count: number;
    schedules: ScheduleEntry[];
};

type CourseOption = {
    id: number;
    code: string;
    title: string;
};

type RoomOption = {
    id: number;
    name: string;
    class_code: string | null;
};

type FacultyOption = {
    id: string;
    name: string;
    department: string | null;
};

type ScheduleConflict = {
    day: string;
    time: string;
    class_1: {
        subject_code: string;
        section: string;
        room: string | null;
        faculty: string | null;
    };
    class_2: {
        subject_code: string;
        section: string;
        room: string | null;
        faculty: string | null;
    };
    conflict_type: "room" | "faculty";
};

type ScheduleStats = {
    total_classes: number;
    total_students: number;
    classes_by_year_level: Record<string, number>;
    classes_by_course: Record<string, number>;
    schedule_conflicts: ScheduleConflict[];
};

type StudentSearchResult = {
    id: number;
    student_id: number;
    name: string;
    course_id: number | null;
    academic_year: number | null;
};

type StudentSchedule = {
    student: {
        id: number;
        student_id: number;
        name: string;
        course: string | null;
        academic_year: number | null;
    };
    schedule: Array<{
        id: number;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty_name: string | null;
        schedules: ScheduleEntry[];
    }>;
};

interface SchedulingAnalyticsProps {
    user: User;
    schedule_data: ClassScheduleData[];
    stats: ScheduleStats;
    filters: {
        available_courses: CourseOption[];
        available_year_levels: string[];
        available_sections: string[];
        available_rooms: RoomOption[];
        available_faculty: FacultyOption[];
        current_filters: {
            course: string | null;
            year_level: string | null;
            section: string | null;
        };
    };
}

// Constants
const DAYS_OF_WEEK = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

const DAYS_SHORT = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

// Time slots in 24-hour format for easier calculation
const TIME_SLOTS_24 = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];

const TIME_SLOTS = [
    "07:00 AM",
    "08:00 AM",
    "09:00 AM",
    "10:00 AM",
    "11:00 AM",
    "12:00 PM",
    "01:00 PM",
    "02:00 PM",
    "03:00 PM",
    "04:00 PM",
    "05:00 PM",
    "06:00 PM",
    "07:00 PM",
];

// Vibrant colors for a fun design
const COLORS = [
    {
        bg: "bg-rose-500",
        light: "bg-rose-100 dark:bg-rose-900/40",
        text: "text-rose-700 dark:text-rose-300",
        border: "border-rose-300 dark:border-rose-700",
    },
    {
        bg: "bg-orange-500",
        light: "bg-orange-100 dark:bg-orange-900/40",
        text: "text-orange-700 dark:text-orange-300",
        border: "border-orange-300 dark:border-orange-700",
    },
    {
        bg: "bg-amber-500",
        light: "bg-amber-100 dark:bg-amber-900/40",
        text: "text-amber-700 dark:text-amber-300",
        border: "border-amber-300 dark:border-amber-700",
    },
    {
        bg: "bg-lime-500",
        light: "bg-lime-100 dark:bg-lime-900/40",
        text: "text-lime-700 dark:text-lime-300",
        border: "border-lime-300 dark:border-lime-700",
    },
    {
        bg: "bg-emerald-500",
        light: "bg-emerald-100 dark:bg-emerald-900/40",
        text: "text-emerald-700 dark:text-emerald-300",
        border: "border-emerald-300 dark:border-emerald-700",
    },
    {
        bg: "bg-teal-500",
        light: "bg-teal-100 dark:bg-teal-900/40",
        text: "text-teal-700 dark:text-teal-300",
        border: "border-teal-300 dark:border-teal-700",
    },
    {
        bg: "bg-cyan-500",
        light: "bg-cyan-100 dark:bg-cyan-900/40",
        text: "text-cyan-700 dark:text-cyan-300",
        border: "border-cyan-300 dark:border-cyan-700",
    },
    {
        bg: "bg-sky-500",
        light: "bg-sky-100 dark:bg-sky-900/40",
        text: "text-sky-700 dark:text-sky-300",
        border: "border-sky-300 dark:border-sky-700",
    },
    {
        bg: "bg-blue-500",
        light: "bg-blue-100 dark:bg-blue-900/40",
        text: "text-blue-700 dark:text-blue-300",
        border: "border-blue-300 dark:border-blue-700",
    },
    {
        bg: "bg-indigo-500",
        light: "bg-indigo-100 dark:bg-indigo-900/40",
        text: "text-indigo-700 dark:text-indigo-300",
        border: "border-indigo-300 dark:border-indigo-700",
    },
    {
        bg: "bg-violet-500",
        light: "bg-violet-100 dark:bg-violet-900/40",
        text: "text-violet-700 dark:text-violet-300",
        border: "border-violet-300 dark:border-violet-700",
    },
    {
        bg: "bg-purple-500",
        light: "bg-purple-100 dark:bg-purple-900/40",
        text: "text-purple-700 dark:text-purple-300",
        border: "border-purple-300 dark:border-purple-700",
    },
    {
        bg: "bg-fuchsia-500",
        light: "bg-fuchsia-100 dark:bg-fuchsia-900/40",
        text: "text-fuchsia-700 dark:text-fuchsia-300",
        border: "border-fuchsia-300 dark:border-fuchsia-700",
    },
    {
        bg: "bg-pink-500",
        light: "bg-pink-100 dark:bg-pink-900/40",
        text: "text-pink-700 dark:text-pink-300",
        border: "border-pink-300 dark:border-pink-700",
    },
];

const getColorScheme = (str: string) => {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % COLORS.length;
    return COLORS[index];
};

// Helper to parse time string like "09:00" or "9:00 AM" to hour number
const parseTimeToHour = (timeStr: string): number => {
    if (!timeStr) return 0;
    // Handle 24-hour format "09:00:00" or "09:00"
    const parts = timeStr.split(":");
    let hour = parseInt(parts[0]);

    // Handle 12-hour format with AM/PM
    if (timeStr.toLowerCase().includes("pm") && hour !== 12) {
        hour += 12;
    } else if (timeStr.toLowerCase().includes("am") && hour === 12) {
        hour = 0;
    }

    return hour;
};

// Calculate duration in hours
const calculateDuration = (startTime: string, endTime: string): number => {
    const startHour = parseTimeToHour(startTime);
    const endHour = parseTimeToHour(endTime);
    return Math.max(1, endHour - startHour);
};

// Format hour to display time
const formatHour = (hour: number): string => {
    if (hour === 0) return "12:00 AM";
    if (hour === 12) return "12:00 PM";
    if (hour > 12) return `${(hour - 12).toString().padStart(2, "0")}:00 PM`;
    return `${hour.toString().padStart(2, "0")}:00 AM`;
};

// Processed schedule item for the grid
type ProcessedScheduleItem = {
    classItem: ClassScheduleData;
    schedule: ScheduleEntry;
    startHour: number;
    duration: number;
    dayIndex: number;
};

// Components
function ClassDetailsDialog({
    classItem,
    open,
    onOpenChange,
}: {
    classItem: ClassScheduleData | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!classItem) return null;
    const colorScheme = getColorScheme(classItem.subject_code);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <div className="mb-2 flex items-center gap-3">
                        <div className={`rounded-xl p-2.5 ${colorScheme.bg} text-white shadow-lg`}>
                            <BookOpen className="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-xl">{classItem.subject_code}</DialogTitle>
                            <DialogDescription>{classItem.subject_title}</DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <Label className="text-muted-foreground text-xs tracking-wider uppercase">Section</Label>
                            <div className="flex items-center gap-2 font-medium">
                                <Users className="text-muted-foreground h-4 w-4" />
                                {classItem.section}
                            </div>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-muted-foreground text-xs tracking-wider uppercase">Course</Label>
                            <div className="flex items-center gap-2 font-medium">
                                <GraduationCap className="text-muted-foreground h-4 w-4" />
                                {classItem.courses || "N/A"}
                            </div>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-muted-foreground text-xs tracking-wider uppercase">Year Level</Label>
                            <div className="font-medium">{classItem.grade_level || "N/A"}</div>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-muted-foreground text-xs tracking-wider uppercase">Students</Label>
                            <div className="font-medium">{classItem.student_count} Enrolled</div>
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label className="text-muted-foreground text-xs tracking-wider uppercase">Faculty</Label>
                            <div className="flex items-center gap-2 font-medium">
                                <UserIcon className="text-muted-foreground h-4 w-4" />
                                {classItem.faculty_name || "TBA"}
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div className="space-y-3">
                        <Label className="text-base font-semibold">Schedule</Label>
                        {classItem.schedules.length > 0 ? (
                            <div className="grid gap-2">
                                {classItem.schedules.map((schedule, idx) => (
                                    <div
                                        key={idx}
                                        className={`flex items-center justify-between rounded-xl border-2 p-3 ${colorScheme.light} ${colorScheme.border}`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <Calendar className={`h-4 w-4 ${colorScheme.text}`} />
                                            <span className="font-medium">{schedule.day_of_week}</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="text-muted-foreground flex items-center gap-1.5 text-sm">
                                                <Clock className="h-3.5 w-3.5" />
                                                {schedule.time_range}
                                            </div>
                                            {schedule.room && (
                                                <Badge variant="outline" className="flex items-center gap-1">
                                                    <MapPin className="h-3 w-3" />
                                                    {schedule.room}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-muted-foreground text-sm italic">No schedule assigned yet.</div>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

// Enhanced Timetable View with proper time spanning
function TimetableView({ scheduleData, onClassClick }: { scheduleData: ClassScheduleData[]; onClassClick: (item: ClassScheduleData) => void }) {
    // Process schedules to calculate positions and spans
    const processedSchedules = React.useMemo(() => {
        const items: ProcessedScheduleItem[] = [];

        scheduleData.forEach((classItem) => {
            classItem.schedules.forEach((schedule) => {
                const dayIndex = DAYS_OF_WEEK.indexOf(schedule.day_of_week);
                if (dayIndex === -1) return;

                const startHour = parseTimeToHour(schedule.start_time);
                const duration = calculateDuration(schedule.start_time, schedule.end_time);

                items.push({
                    classItem,
                    schedule,
                    startHour,
                    duration,
                    dayIndex,
                });
            });
        });

        return items;
    }, [scheduleData]);

    // Create a map of which cells are occupied (to skip rendering)
    const occupiedCells = React.useMemo(() => {
        const occupied = new Set<string>();

        processedSchedules.forEach((item) => {
            for (let h = 0; h < item.duration; h++) {
                occupied.add(`${item.dayIndex}-${item.startHour + h}`);
            }
        });

        return occupied;
    }, [processedSchedules]);

    // Get items that start at a specific hour and day
    const getItemsAtCell = (dayIndex: number, hour: number): ProcessedScheduleItem[] => {
        return processedSchedules.filter((item) => item.dayIndex === dayIndex && item.startHour === hour);
    };

    // Check if a cell should be skipped (part of a spanning cell)
    const shouldSkipCell = (dayIndex: number, hour: number): boolean => {
        return processedSchedules.some((item) => item.dayIndex === dayIndex && item.startHour < hour && item.startHour + item.duration > hour);
    };

    return (
        <TooltipProvider>
            <ScrollArea className="h-[650px] w-full rounded-xl border-2 shadow-sm">
                <div className="min-w-[900px]">
                    <div className="from-primary/5 to-primary/10 grid grid-cols-[80px_repeat(6,1fr)] bg-gradient-to-r">
                        {/* Header */}
                        <div className="bg-background/95 sticky top-0 z-20 flex items-center justify-center border-r border-b-2 p-3 backdrop-blur">
                            <Clock className="text-muted-foreground h-4 w-4" />
                        </div>
                        {DAYS_OF_WEEK.map((day, idx) => (
                            <div
                                key={day}
                                className="bg-background/95 sticky top-0 z-20 border-r border-b-2 p-3 text-center font-semibold backdrop-blur last:border-r-0"
                            >
                                <div className="text-muted-foreground text-sm">{DAYS_SHORT[idx]}</div>
                                <div className="text-muted-foreground/70 text-xs">{day}</div>
                            </div>
                        ))}

                        {/* Time Rows */}
                        {TIME_SLOTS_24.map((hour) => (
                            <React.Fragment key={hour}>
                                {/* Time Label */}
                                <div className="text-muted-foreground bg-muted/30 flex items-start justify-center border-r border-b p-2 pt-3 text-center text-xs font-medium">
                                    {formatHour(hour)}
                                </div>

                                {/* Day Cells */}
                                {DAYS_OF_WEEK.map((_, dayIndex) => {
                                    // Skip if this cell is part of a spanning cell
                                    if (shouldSkipCell(dayIndex, hour)) {
                                        return null;
                                    }

                                    const items = getItemsAtCell(dayIndex, hour);
                                    const maxDuration = items.length > 0 ? Math.max(...items.map((i) => i.duration)) : 1;

                                    return (
                                        <div
                                            key={`${dayIndex}-${hour}`}
                                            className={`relative border-r border-b p-1 last:border-r-0 ${items.length === 0 ? "bg-background hover:bg-muted/20 transition-colors" : ""}`}
                                            style={{
                                                gridRow: items.length > 0 ? `span ${maxDuration}` : undefined,
                                                minHeight: "60px",
                                            }}
                                        >
                                            {items.length > 0 && (
                                                <div className="flex h-full flex-col gap-1">
                                                    {items.map((item, idx) => {
                                                        const colorScheme = getColorScheme(item.classItem.subject_code);
                                                        return (
                                                            <Tooltip key={`${item.classItem.id}-${idx}`}>
                                                                <TooltipTrigger asChild>
                                                                    <button
                                                                        onClick={() => onClassClick(item.classItem)}
                                                                        className={`h-full w-full min-h-[${item.duration * 60 - 8}px] rounded-xl border-2 p-2 text-left ${colorScheme.border} ${colorScheme.light} group relative overflow-hidden transition-all duration-200 hover:z-10 hover:scale-[1.02] hover:shadow-lg active:scale-[0.98]`}
                                                                    >
                                                                        {/* Decorative gradient */}
                                                                        <div
                                                                            className={`absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-0 transition-opacity group-hover:opacity-100`}
                                                                        />

                                                                        <div className="relative z-10">
                                                                            <div className={`text-sm font-bold ${colorScheme.text} truncate`}>
                                                                                {item.classItem.subject_code}
                                                                            </div>
                                                                            <div className={`text-xs ${colorScheme.text} truncate opacity-80`}>
                                                                                {item.classItem.section}
                                                                            </div>
                                                                            {item.duration >= 2 && (
                                                                                <>
                                                                                    <div className="text-muted-foreground mt-1 flex items-center gap-1 text-[10px]">
                                                                                        <Clock className="h-3 w-3" />
                                                                                        {item.schedule.time_range}
                                                                                    </div>
                                                                                    {item.schedule.room && (
                                                                                        <div className="text-muted-foreground mt-0.5 flex items-center gap-1 text-[10px]">
                                                                                            <MapPin className="h-3 w-3" />
                                                                                            {item.schedule.room}
                                                                                        </div>
                                                                                    )}
                                                                                </>
                                                                            )}
                                                                            {item.duration >= 3 && item.classItem.faculty_name && (
                                                                                <div className="text-muted-foreground mt-0.5 flex items-center gap-1 text-[10px]">
                                                                                    <UserIcon className="h-3 w-3" />
                                                                                    {item.classItem.faculty_name}
                                                                                </div>
                                                                            )}
                                                                        </div>

                                                                        {/* Duration indicator */}
                                                                        <div
                                                                            className={`absolute right-1 bottom-1 ${colorScheme.bg} rounded-full px-1.5 py-0.5 text-[9px] font-medium text-white`}
                                                                        >
                                                                            {item.duration}h
                                                                        </div>
                                                                    </button>
                                                                </TooltipTrigger>
                                                                <TooltipContent side="right" className="max-w-xs">
                                                                    <div className="space-y-1">
                                                                        <div className="font-bold">{item.classItem.subject_code}</div>
                                                                        <div className="text-sm">{item.classItem.subject_title}</div>
                                                                        <div className="text-muted-foreground text-xs">
                                                                            Section: {item.classItem.section}
                                                                        </div>
                                                                        <div className="text-muted-foreground text-xs">
                                                                            {item.schedule.time_range}
                                                                        </div>
                                                                        {item.schedule.room && (
                                                                            <div className="text-muted-foreground text-xs">
                                                                                Room: {item.schedule.room}
                                                                            </div>
                                                                        )}
                                                                        <div className="text-muted-foreground text-xs">
                                                                            Faculty: {item.classItem.faculty_name || "TBA"}
                                                                        </div>
                                                                    </div>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </React.Fragment>
                        ))}
                    </div>
                </div>
            </ScrollArea>
        </TooltipProvider>
    );
}

// NEW: Matrix View - A compact, colorful overview
function MatrixView({ scheduleData, onClassClick }: { scheduleData: ClassScheduleData[]; onClassClick: (item: ClassScheduleData) => void }) {
    // Process schedules into a matrix format
    const matrixData = React.useMemo(() => {
        const matrix: Record<string, Record<string, ProcessedScheduleItem[]>> = {};

        DAYS_OF_WEEK.forEach((day) => {
            matrix[day] = {};
            TIME_SLOTS_24.forEach((hour) => {
                matrix[day][hour] = [];
            });
        });

        scheduleData.forEach((classItem) => {
            classItem.schedules.forEach((schedule) => {
                const day = schedule.day_of_week;
                if (!matrix[day]) return;

                const startHour = parseTimeToHour(schedule.start_time);
                const endHour = parseTimeToHour(schedule.end_time);

                // Fill all hours this class occupies
                for (let h = startHour; h < endHour && h <= 19; h++) {
                    if (matrix[day][h]) {
                        matrix[day][h].push({
                            classItem,
                            schedule,
                            startHour,
                            duration: endHour - startHour,
                            dayIndex: DAYS_OF_WEEK.indexOf(day),
                        });
                    }
                }
            });
        });

        return matrix;
    }, [scheduleData]);

    // Count total classes per day for stats
    const dayStats = React.useMemo(() => {
        const stats: Record<string, number> = {};
        DAYS_OF_WEEK.forEach((day) => {
            const uniqueClasses = new Set<number>();
            Object.values(matrixData[day]).forEach((items) => {
                items.forEach((item) => uniqueClasses.add(item.classItem.id));
            });
            stats[day] = uniqueClasses.size;
        });
        return stats;
    }, [matrixData]);

    // Count total hours per day
    const hourStats = React.useMemo(() => {
        const stats: Record<number, number> = {};
        TIME_SLOTS_24.forEach((hour) => {
            let count = 0;
            DAYS_OF_WEEK.forEach((day) => {
                count += matrixData[day][hour].length;
            });
            stats[hour] = count;
        });
        return stats;
    }, [matrixData]);

    const maxHourCount = Math.max(...Object.values(hourStats), 1);

    return (
        <TooltipProvider>
            <div className="space-y-6">
                {/* Legend and Stats */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Sparkles className="text-primary h-4 w-4" />
                            <span className="text-sm font-medium">Matrix View</span>
                        </div>
                        <div className="text-muted-foreground flex items-center gap-2 text-xs">
                            <div className="flex items-center gap-1">
                                <div className="bg-muted h-3 w-3 rounded border" />
                                <span>Empty</span>
                            </div>
                            <div className="flex items-center gap-1">
                                <div className="bg-primary/20 border-primary/30 h-3 w-3 rounded border" />
                                <span>1 class</span>
                            </div>
                            <div className="flex items-center gap-1">
                                <div className="bg-primary/50 border-primary/60 h-3 w-3 rounded border" />
                                <span>2+ classes</span>
                            </div>
                        </div>
                    </div>
                    <div className="text-muted-foreground text-sm">Click any cell to see details</div>
                </div>

                {/* Matrix Grid */}
                <div className="from-background to-muted/20 overflow-hidden rounded-2xl border-2 bg-gradient-to-br shadow-lg">
                    <div className="overflow-x-auto">
                        <div className="min-w-[700px]">
                            {/* Header Row */}
                            <div className="bg-muted/50 grid grid-cols-[100px_repeat(6,1fr)]">
                                <div className="flex items-center justify-center gap-2 border-r border-b p-3 text-center text-sm font-medium">
                                    <Grid3X3 className="text-primary h-4 w-4" />
                                    <span>Time</span>
                                </div>
                                {DAYS_OF_WEEK.map((day, idx) => (
                                    <div key={day} className="border-r border-b p-3 text-center last:border-r-0">
                                        <div className="font-semibold">{DAYS_SHORT[idx]}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {dayStats[day]} class{dayStats[day] !== 1 ? "es" : ""}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Time Rows */}
                            {TIME_SLOTS_24.map((hour) => (
                                <div key={hour} className="grid grid-cols-[100px_repeat(6,1fr)]">
                                    {/* Time Label */}
                                    <div className="bg-muted/30 flex items-center justify-between border-r border-b p-2 px-3 text-center">
                                        <span className="text-muted-foreground text-xs font-medium">{formatHour(hour)}</span>
                                        {/* Hour activity indicator */}
                                        <div
                                            className="bg-primary/20 h-2 rounded-full transition-all"
                                            style={{ width: `${(hourStats[hour] / maxHourCount) * 30}px` }}
                                        />
                                    </div>

                                    {/* Day Cells */}
                                    {DAYS_OF_WEEK.map((day) => {
                                        const items = matrixData[day][hour];
                                        const hasMultiple = items.length > 1;
                                        const hasItems = items.length > 0;

                                        // Get unique classes (to avoid showing duplicates from multi-hour spans)
                                        const uniqueItems = items.filter(
                                            (item, idx, arr) => arr.findIndex((i) => i.classItem.id === item.classItem.id) === idx,
                                        );

                                        return (
                                            <div
                                                key={`${day}-${hour}`}
                                                className={`min-h-[50px] border-r border-b p-1 transition-all last:border-r-0 ${hasItems ? "cursor-pointer" : ""} ${hasMultiple ? "bg-orange-100/50 dark:bg-orange-900/20" : hasItems ? "bg-primary/5" : "hover:bg-muted/30"} `}
                                            >
                                                <div className="flex h-full flex-wrap gap-0.5">
                                                    {uniqueItems.slice(0, 4).map((item, idx) => {
                                                        const colorScheme = getColorScheme(item.classItem.subject_code);
                                                        const isStartHour = item.startHour === hour;

                                                        return (
                                                            <Tooltip key={`${item.classItem.id}-${idx}`}>
                                                                <TooltipTrigger asChild>
                                                                    <button
                                                                        onClick={() => onClassClick(item.classItem)}
                                                                        className={`min-w-[40px] flex-1 rounded-lg p-1 text-[10px] font-medium transition-all hover:scale-105 hover:shadow-md ${colorScheme.light} ${colorScheme.text} ${colorScheme.border} border ${isStartHour ? "ring-2 ring-offset-1" : "opacity-70"} `}
                                                                        style={{
                                                                            // @ts-expect-error CSS custom property
                                                                            "--tw-ring-color": isStartHour
                                                                                ? colorScheme.bg.replace("bg-", "rgb(var(--") + "))"
                                                                                : "transparent",
                                                                        }}
                                                                    >
                                                                        <div className="truncate">{item.classItem.subject_code}</div>
                                                                        {isStartHour && (
                                                                            <div className="truncate opacity-70">{item.classItem.section}</div>
                                                                        )}
                                                                    </button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <div className="space-y-1">
                                                                        <div className="font-bold">{item.classItem.subject_code}</div>
                                                                        <div className="text-xs">{item.classItem.subject_title}</div>
                                                                        <div className="text-xs opacity-80">
                                                                            {item.schedule.time_range} ({item.duration}h)
                                                                        </div>
                                                                        <div className="text-xs opacity-80">Section: {item.classItem.section}</div>
                                                                        {item.schedule.room && (
                                                                            <div className="text-xs opacity-80">Room: {item.schedule.room}</div>
                                                                        )}
                                                                    </div>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        );
                                                    })}
                                                    {uniqueItems.length > 4 && (
                                                        <div className="bg-muted text-muted-foreground flex min-w-[30px] flex-1 items-center justify-center rounded-lg p-1 text-[10px] font-bold">
                                                            +{uniqueItems.length - 4}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                    {DAYS_OF_WEEK.map((day, idx) => {
                        const count = dayStats[day];
                        const colorScheme = COLORS[idx % COLORS.length];
                        return (
                            <Card key={day} className={`${colorScheme.light} ${colorScheme.border} border-2`}>
                                <CardContent className="p-3 text-center">
                                    <div className={`text-2xl font-bold ${colorScheme.text}`}>{count}</div>
                                    <div className="text-muted-foreground text-xs">{DAYS_SHORT[idx]} Classes</div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </TooltipProvider>
    );
}

function ListView({ scheduleData, onClassClick }: { scheduleData: ClassScheduleData[]; onClassClick: (item: ClassScheduleData) => void }) {
    return (
        <div className="overflow-hidden rounded-xl border-2 shadow-sm">
            <Table>
                <TableHeader className="bg-muted/50">
                    <TableRow>
                        <TableHead>Subject</TableHead>
                        <TableHead>Section</TableHead>
                        <TableHead className="hidden md:table-cell">Course</TableHead>
                        <TableHead className="hidden md:table-cell">Schedule</TableHead>
                        <TableHead className="text-right">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {scheduleData.length === 0 ? (
                        <TableRow>
                            <TableCell colSpan={5} className="text-muted-foreground py-12 text-center">
                                <div className="flex flex-col items-center gap-2">
                                    <Search className="h-8 w-8 opacity-20" />
                                    <p>No classes found matching your filters</p>
                                </div>
                            </TableCell>
                        </TableRow>
                    ) : (
                        scheduleData.map((classItem) => {
                            const colorScheme = getColorScheme(classItem.subject_code);
                            return (
                                <TableRow key={classItem.id} className="group hover:bg-muted/30 transition-colors">
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <div className={`h-10 w-2 rounded-full ${colorScheme.bg}`} />
                                            <div>
                                                <div className="group-hover:text-primary font-medium transition-colors">{classItem.subject_code}</div>
                                                <div className="text-muted-foreground max-w-[200px] truncate text-xs">{classItem.subject_title}</div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary" className={`font-normal ${colorScheme.light} ${colorScheme.text}`}>
                                            {classItem.section}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground hidden text-sm md:table-cell">{classItem.courses || "-"}</TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        <div className="flex flex-col gap-1">
                                            {classItem.schedules.slice(0, 2).map((s, i) => {
                                                const duration = calculateDuration(s.start_time, s.end_time);
                                                return (
                                                    <div key={i} className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                                        <span className="text-foreground w-10 font-medium">{s.day_of_week.slice(0, 3)}</span>
                                                        <span>{s.time_range}</span>
                                                        <Badge variant="outline" className="h-4 px-1 text-[10px]">
                                                            {duration}h
                                                        </Badge>
                                                    </div>
                                                );
                                            })}
                                            {classItem.schedules.length > 2 && (
                                                <span className="text-muted-foreground text-[10px]">+ {classItem.schedules.length - 2} more</span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="ghost" size="sm" onClick={() => onClassClick(classItem)} className="hover:bg-primary/10">
                                            Details
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            );
                        })
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

// Room View Component
function RoomScheduleView({
    scheduleData,
    rooms,
    onClassClick,
}: {
    scheduleData: ClassScheduleData[];
    rooms: RoomOption[];
    onClassClick: (item: ClassScheduleData) => void;
}) {
    const [selectedRoom, setSelectedRoom] = React.useState<string>("all");
    const [viewMode, setViewMode] = React.useState<"timetable" | "matrix" | "list">("timetable");

    const filteredData = React.useMemo(() => {
        if (selectedRoom === "all") return [];

        const roomId = parseInt(selectedRoom);
        return scheduleData.filter((classItem) => classItem.schedules.some((s) => s.room_id === roomId));
    }, [scheduleData, selectedRoom]);

    const selectedRoomInfo = rooms.find((r) => String(r.id) === selectedRoom);

    return (
        <div className="space-y-4">
            <Card className="border-primary/20 from-primary/5 border-2 bg-gradient-to-r to-transparent">
                <CardHeader className="pb-4">
                    <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                        <div className="bg-primary text-primary-foreground rounded-2xl p-3 shadow-lg">
                            <Building2 className="h-6 w-6" />
                        </div>
                        <div className="flex-1">
                            <CardTitle className="text-xl">Room Schedule</CardTitle>
                            <CardDescription>View all classes scheduled in a specific room</CardDescription>
                        </div>
                        <Select value={selectedRoom} onValueChange={setSelectedRoom}>
                            <SelectTrigger className="w-[220px]">
                                <SelectValue placeholder="Select a room" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Select a room...</SelectItem>
                                {rooms.map((room) => (
                                    <SelectItem key={room.id} value={String(room.id)}>
                                        {room.name} {room.class_code && `(${room.class_code})`}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </CardHeader>
            </Card>

            {selectedRoom === "all" ? (
                <Card className="border-2 border-dashed">
                    <CardContent className="py-16">
                        <div className="flex flex-col items-center justify-center text-center">
                            <div className="bg-muted mb-4 rounded-full p-4">
                                <Building2 className="text-muted-foreground h-10 w-10" />
                            </div>
                            <h3 className="mb-2 text-xl font-semibold">Select a Room</h3>
                            <p className="text-muted-foreground max-w-md">
                                Choose a room from the dropdown above to view its weekly schedule with all classes
                            </p>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <Card className="border-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <MapPin className="text-primary h-5 w-5" />
                                {selectedRoomInfo?.name}
                                {selectedRoomInfo?.class_code && <Badge variant="outline">{selectedRoomInfo.class_code}</Badge>}
                            </CardTitle>
                            <CardDescription>
                                {filteredData.length} class{filteredData.length !== 1 ? "es" : ""} scheduled in this room
                            </CardDescription>
                        </div>
                        <ViewModeToggle value={viewMode} onChange={setViewMode} />
                    </CardHeader>
                    <CardContent>
                        {filteredData.length > 0 ? (
                            viewMode === "timetable" ? (
                                <TimetableView scheduleData={filteredData} onClassClick={onClassClick} />
                            ) : viewMode === "matrix" ? (
                                <MatrixView scheduleData={filteredData} onClassClick={onClassClick} />
                            ) : (
                                <ListView scheduleData={filteredData} onClassClick={onClassClick} />
                            )
                        ) : (
                            <div className="text-muted-foreground py-12 text-center">
                                <Calendar className="mx-auto mb-4 h-12 w-12 opacity-20" />
                                <p>No classes scheduled in this room for the current period.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

// Faculty View Component
function FacultyScheduleView({
    scheduleData,
    faculty,
    onClassClick,
}: {
    scheduleData: ClassScheduleData[];
    faculty: FacultyOption[];
    onClassClick: (item: ClassScheduleData) => void;
}) {
    const [selectedFaculty, setSelectedFaculty] = React.useState<string>("all");
    const [viewMode, setViewMode] = React.useState<"timetable" | "matrix" | "list">("timetable");

    const filteredData = React.useMemo(() => {
        if (selectedFaculty === "all") return [];
        return scheduleData.filter((classItem) => classItem.faculty_id === selectedFaculty);
    }, [scheduleData, selectedFaculty]);

    const selectedFacultyInfo = faculty.find((f) => f.id === selectedFaculty);

    return (
        <div className="space-y-4">
            <Card className="border-primary/20 from-primary/5 border-2 bg-gradient-to-r to-transparent">
                <CardHeader className="pb-4">
                    <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                        <div className="bg-primary text-primary-foreground rounded-2xl p-3 shadow-lg">
                            <UserIcon className="h-6 w-6" />
                        </div>
                        <div className="flex-1">
                            <CardTitle className="text-xl">Faculty Schedule</CardTitle>
                            <CardDescription>View all classes taught by a specific faculty member</CardDescription>
                        </div>
                        <Select value={selectedFaculty} onValueChange={setSelectedFaculty}>
                            <SelectTrigger className="w-[280px]">
                                <SelectValue placeholder="Select a faculty" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Select a faculty...</SelectItem>
                                {faculty.map((f) => (
                                    <SelectItem key={f.id} value={f.id}>
                                        {f.name} {f.department && `(${f.department})`}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </CardHeader>
            </Card>

            {selectedFaculty === "all" ? (
                <Card className="border-2 border-dashed">
                    <CardContent className="py-16">
                        <div className="flex flex-col items-center justify-center text-center">
                            <div className="bg-muted mb-4 rounded-full p-4">
                                <UserIcon className="text-muted-foreground h-10 w-10" />
                            </div>
                            <h3 className="mb-2 text-xl font-semibold">Select a Faculty Member</h3>
                            <p className="text-muted-foreground max-w-md">
                                Choose a faculty from the dropdown above to view their complete teaching schedule
                            </p>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                <Card className="border-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <UserIcon className="text-primary h-5 w-5" />
                                {selectedFacultyInfo?.name}
                                {selectedFacultyInfo?.department && <Badge variant="outline">{selectedFacultyInfo.department}</Badge>}
                            </CardTitle>
                            <CardDescription>
                                {filteredData.length} class{filteredData.length !== 1 ? "es" : ""} assigned to this faculty
                            </CardDescription>
                        </div>
                        <ViewModeToggle value={viewMode} onChange={setViewMode} />
                    </CardHeader>
                    <CardContent>
                        {filteredData.length > 0 ? (
                            viewMode === "timetable" ? (
                                <TimetableView scheduleData={filteredData} onClassClick={onClassClick} />
                            ) : viewMode === "matrix" ? (
                                <MatrixView scheduleData={filteredData} onClassClick={onClassClick} />
                            ) : (
                                <ListView scheduleData={filteredData} onClassClick={onClassClick} />
                            )
                        ) : (
                            <div className="text-muted-foreground py-12 text-center">
                                <Calendar className="mx-auto mb-4 h-12 w-12 opacity-20" />
                                <p>No classes assigned to this faculty for the current period.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

// View Mode Toggle Component
function ViewModeToggle({ value, onChange }: { value: "timetable" | "matrix" | "list"; onChange: (value: "timetable" | "matrix" | "list") => void }) {
    return (
        <div className="bg-muted flex rounded-xl p-1">
            <Button variant={value === "list" ? "secondary" : "ghost"} size="sm" onClick={() => onChange("list")} className="h-8 rounded-lg px-3">
                <List className="mr-1.5 h-4 w-4" /> List
            </Button>
            <Button
                variant={value === "timetable" ? "secondary" : "ghost"}
                size="sm"
                onClick={() => onChange("timetable")}
                className="h-8 rounded-lg px-3"
            >
                <LayoutGrid className="mr-1.5 h-4 w-4" /> Grid
            </Button>
            <Button variant={value === "matrix" ? "secondary" : "ghost"} size="sm" onClick={() => onChange("matrix")} className="h-8 rounded-lg px-3">
                <Grid3X3 className="mr-1.5 h-4 w-4" /> Matrix
            </Button>
        </div>
    );
}

// Student View Component
function StudentScheduleView() {
    const [searchQuery, setSearchQuery] = React.useState("");
    const [searchResults, setSearchResults] = React.useState<StudentSearchResult[]>([]);
    const [selectedStudent, setSelectedStudent] = React.useState<StudentSchedule | null>(null);
    const [isSearching, setIsSearching] = React.useState(false);
    const [isLoadingSchedule, setIsLoadingSchedule] = React.useState(false);
    const searchTimeoutRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleSearch = React.useCallback(async (query: string) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(route("administrators.scheduling-analytics.students.search", { query }));
            const data = await response.json();
            setSearchResults(data.students || []);
        } catch (error) {
            console.error("Error searching students:", error);
            setSearchResults([]);
        } finally {
            setIsSearching(false);
        }
    }, []);

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);

        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        searchTimeoutRef.current = setTimeout(() => {
            handleSearch(value);
        }, 300);
    };

    const handleSelectStudent = async (student: StudentSearchResult) => {
        setIsLoadingSchedule(true);
        setSearchQuery("");
        setSearchResults([]);

        try {
            const response = await fetch(route("administrators.scheduling-analytics.students.schedule", { studentId: student.id }));
            const data = await response.json();
            setSelectedStudent(data);
        } catch (error) {
            console.error("Error fetching student schedule:", error);
        } finally {
            setIsLoadingSchedule(false);
        }
    };

    return (
        <div className="space-y-4">
            <Card className="border-primary/20 from-primary/5 border-2 bg-gradient-to-r to-transparent">
                <CardHeader className="pb-4">
                    <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                        <div className="bg-primary text-primary-foreground rounded-2xl p-3 shadow-lg">
                            <GraduationCap className="h-6 w-6" />
                        </div>
                        <div className="flex-1">
                            <CardTitle className="text-xl">Student Schedule</CardTitle>
                            <CardDescription>Search for a student to view their class schedule</CardDescription>
                        </div>
                        <div className="relative w-full sm:w-[320px]">
                            <Search className="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                            <Input
                                placeholder="Search by name, ID, or LRN..."
                                className="h-11 pl-10"
                                value={searchQuery}
                                onChange={(e) => handleSearchChange(e.target.value)}
                            />
                            {isSearching && <Loader2 className="text-muted-foreground absolute top-3 right-3 h-4 w-4 animate-spin" />}

                            {/* Search Results Dropdown */}
                            {searchResults.length > 0 && (
                                <div className="bg-background absolute top-full right-0 left-0 z-50 mt-1 max-h-[300px] overflow-auto rounded-xl border-2 shadow-xl">
                                    {searchResults.map((student) => (
                                        <button
                                            key={student.id}
                                            className="hover:bg-primary/5 flex w-full items-center justify-between border-b px-4 py-3 text-left transition-colors last:border-b-0"
                                            onClick={() => handleSelectStudent(student)}
                                        >
                                            <div>
                                                <div className="font-medium">{student.name}</div>
                                                <div className="text-muted-foreground text-xs">ID: {student.student_id}</div>
                                            </div>
                                            <Badge variant="outline" className="text-xs">
                                                Year {student.academic_year || "N/A"}
                                            </Badge>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {isLoadingSchedule ? (
                <Card className="border-2">
                    <CardContent className="py-16">
                        <div className="flex flex-col items-center justify-center">
                            <Loader2 className="text-primary mb-4 h-10 w-10 animate-spin" />
                            <p className="text-muted-foreground">Loading student schedule...</p>
                        </div>
                    </CardContent>
                </Card>
            ) : selectedStudent ? (
                <Card className="border-2">
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <div className="bg-primary/10 rounded-xl p-2">
                                <GraduationCap className="text-primary h-6 w-6" />
                            </div>
                            <div>
                                <CardTitle>{selectedStudent.student.name}</CardTitle>
                                <CardDescription className="mt-1 flex items-center gap-3">
                                    <span>ID: {selectedStudent.student.student_id}</span>
                                    {selectedStudent.student.course && <Badge variant="outline">{selectedStudent.student.course}</Badge>}
                                    {selectedStudent.student.academic_year && (
                                        <Badge variant="secondary">Year {selectedStudent.student.academic_year}</Badge>
                                    )}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {selectedStudent.schedule.length > 0 ? (
                            <div className="space-y-4">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Subject</TableHead>
                                            <TableHead>Section</TableHead>
                                            <TableHead>Faculty</TableHead>
                                            <TableHead>Schedule</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {selectedStudent.schedule.map((classItem) => {
                                            const colorScheme = getColorScheme(classItem.subject_code);
                                            return (
                                                <TableRow key={classItem.id} className="hover:bg-muted/30">
                                                    <TableCell>
                                                        <div className="flex items-center gap-3">
                                                            <div className={`h-10 w-2 rounded-full ${colorScheme.bg}`} />
                                                            <div>
                                                                <div className="font-medium">{classItem.subject_code}</div>
                                                                <div className="text-muted-foreground max-w-[200px] truncate text-xs">
                                                                    {classItem.subject_title}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="secondary" className={`${colorScheme.light} ${colorScheme.text}`}>
                                                            {classItem.section}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">{classItem.faculty_name || "TBA"}</TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-col gap-1">
                                                            {classItem.schedules.map((s, i) => {
                                                                const duration = calculateDuration(s.start_time, s.end_time);
                                                                return (
                                                                    <div key={i} className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                                                        <span className="text-foreground w-10 font-medium">
                                                                            {s.day_of_week.slice(0, 3)}
                                                                        </span>
                                                                        <span>{s.time_range}</span>
                                                                        <Badge variant="outline" className="h-4 px-1 text-[10px]">
                                                                            {duration}h
                                                                        </Badge>
                                                                        {s.room && (
                                                                            <Badge variant="outline" className="h-4 px-1 text-[10px]">
                                                                                {s.room}
                                                                            </Badge>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="text-muted-foreground py-12 text-center">
                                <Calendar className="mx-auto mb-4 h-12 w-12 opacity-20" />
                                <p>This student is not enrolled in any classes for the current period.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <Card className="border-2 border-dashed">
                    <CardContent className="py-16">
                        <div className="flex flex-col items-center justify-center text-center">
                            <div className="bg-muted mb-4 rounded-full p-4">
                                <GraduationCap className="text-muted-foreground h-10 w-10" />
                            </div>
                            <h3 className="mb-2 text-xl font-semibold">Search for a Student</h3>
                            <p className="text-muted-foreground max-w-md">Use the search box above to find a student by name, ID, or LRN</p>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

// Course/Section View Component (enhanced from original)
function CourseScheduleView({
    scheduleData,
    filters,
    stats,
    onClassClick,
}: {
    scheduleData: ClassScheduleData[];
    filters: SchedulingAnalyticsProps["filters"];
    stats: ScheduleStats;
    onClassClick: (item: ClassScheduleData) => void;
}) {
    const [courseFilter, setCourseFilter] = React.useState<string>("all");
    const [yearFilter, setYearFilter] = React.useState<string>("all");
    const [sectionFilter, setSectionFilter] = React.useState<string>("all");
    const [search, setSearch] = React.useState("");
    const [viewMode, setViewMode] = React.useState<"timetable" | "matrix" | "list">("list");

    const filteredData = React.useMemo(() => {
        let data = scheduleData;

        if (search) {
            const lower = search.toLowerCase();
            data = data.filter(
                (item) =>
                    item.subject_code.toLowerCase().includes(lower) ||
                    item.subject_title.toLowerCase().includes(lower) ||
                    item.section.toLowerCase().includes(lower) ||
                    item.faculty_name?.toLowerCase().includes(lower) ||
                    item.courses?.toLowerCase().includes(lower),
            );
        }

        if (courseFilter !== "all") {
            const filterId = parseInt(courseFilter);
            data = data.filter((item) => item.course_ids && Array.isArray(item.course_ids) && item.course_ids.includes(filterId));
        }

        if (yearFilter !== "all") {
            data = data.filter((item) => item.grade_level === yearFilter);
        }

        if (sectionFilter !== "all") {
            data = data.filter((item) => item.section === sectionFilter);
        }

        return data;
    }, [scheduleData, search, courseFilter, yearFilter, sectionFilter]);

    const computedStats = React.useMemo(() => {
        const relevantConflicts = stats.schedule_conflicts.filter((c) => {
            const isRelevant = (c1: { subject_code: string; section: string }) =>
                filteredData.some((d) => d.subject_code === c1.subject_code && d.section === c1.section);

            return isRelevant(c.class_1) || isRelevant(c.class_2);
        });

        return {
            total_classes: filteredData.length,
            total_students: filteredData.reduce((acc, curr) => acc + curr.student_count, 0),
            conflicts: relevantConflicts,
        };
    }, [filteredData, stats.schedule_conflicts]);

    const clearFilters = () => {
        setCourseFilter("all");
        setYearFilter("all");
        setSectionFilter("all");
        setSearch("");
    };

    const hasActiveFilters = courseFilter !== "all" || yearFilter !== "all" || sectionFilter !== "all" || search !== "";

    return (
        <div className="space-y-4">
            {/* Filter Bar */}
            <Card className="border-primary/20 from-primary/5 border-2 bg-gradient-to-r to-transparent">
                <CardContent className="space-y-4 p-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                            <Input
                                placeholder="Search subject, faculty..."
                                className="h-11 pl-10"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <Select value={courseFilter} onValueChange={setCourseFilter}>
                            <SelectTrigger className="h-11">
                                <div className="text-muted-foreground flex items-center gap-2">
                                    <BookOpen className="h-4 w-4" />
                                    <span className="text-foreground">
                                        {courseFilter === "all"
                                            ? "All Courses"
                                            : filters.available_courses.find((c) => String(c.id) === courseFilter)?.code}
                                    </span>
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Courses</SelectItem>
                                {filters.available_courses.map((course) => (
                                    <SelectItem key={course.id} value={String(course.id)}>
                                        {course.code} - {course.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={yearFilter} onValueChange={setYearFilter}>
                            <SelectTrigger className="h-11">
                                <div className="text-muted-foreground flex items-center gap-2">
                                    <GraduationCap className="h-4 w-4" />
                                    <span className="text-foreground">{yearFilter === "all" ? "All Years" : yearFilter}</span>
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Years</SelectItem>
                                {filters.available_year_levels.map((lvl) => (
                                    <SelectItem key={lvl} value={lvl}>
                                        {lvl}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={sectionFilter} onValueChange={setSectionFilter}>
                            <SelectTrigger className="h-11">
                                <div className="text-muted-foreground flex items-center gap-2">
                                    <Users className="h-4 w-4" />
                                    <span className="text-foreground">{sectionFilter === "all" ? "All Sections" : sectionFilter}</span>
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Sections</SelectItem>
                                {filters.available_sections.map((sec) => (
                                    <SelectItem key={sec} value={sec}>
                                        {sec}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {hasActiveFilters && (
                        <div className="flex items-center justify-between pt-2">
                            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                Showing <span className="text-foreground font-bold">{filteredData.length}</span> of {scheduleData.length} classes
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                                className="text-muted-foreground hover:text-destructive h-auto p-0 px-2"
                            >
                                Clear Filters
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Stats Cards */}
            <div className="grid gap-4 md:grid-cols-3">
                <Card className="hover:border-primary/30 border-2 transition-colors">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Filtered Classes</CardTitle>
                        <div className="bg-primary/10 rounded-lg p-2">
                            <BookOpen className="text-primary h-4 w-4" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">{computedStats.total_classes}</div>
                        <p className="text-muted-foreground text-xs">From {stats.total_classes} total</p>
                    </CardContent>
                </Card>
                <Card className="hover:border-primary/30 border-2 transition-colors">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Students Affected</CardTitle>
                        <div className="rounded-lg bg-emerald-500/10 p-2">
                            <Users className="h-4 w-4 text-emerald-600" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">{computedStats.total_students}</div>
                        <p className="text-muted-foreground text-xs">Enrolled in selected classes</p>
                    </CardContent>
                </Card>
                <Card
                    className={`border-2 transition-colors ${computedStats.conflicts.length > 0 ? "border-destructive/30 bg-destructive/5" : "hover:border-primary/30"}`}
                >
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Conflicts</CardTitle>
                        <div className={`rounded-lg p-2 ${computedStats.conflicts.length > 0 ? "bg-destructive/20" : "bg-muted"}`}>
                            <AlertTriangle
                                className={`h-4 w-4 ${computedStats.conflicts.length > 0 ? "text-destructive" : "text-muted-foreground"}`}
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">{computedStats.conflicts.length}</div>
                        <p className="text-muted-foreground text-xs">
                            {computedStats.conflicts.length > 0 ? "Requires attention" : "No conflicts found"}
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Schedule View */}
            <Card className="border-2">
                <CardHeader className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <CardTitle>Class Schedule</CardTitle>
                        <CardDescription>View and manage class schedules by course and section</CardDescription>
                    </div>
                    <ViewModeToggle value={viewMode} onChange={setViewMode} />
                </CardHeader>
                <CardContent>
                    {viewMode === "list" ? (
                        <ListView scheduleData={filteredData} onClassClick={onClassClick} />
                    ) : viewMode === "matrix" ? (
                        <MatrixView scheduleData={filteredData} onClassClick={onClassClick} />
                    ) : (
                        <TimetableView scheduleData={filteredData} onClassClick={onClassClick} />
                    )}
                </CardContent>
            </Card>

            {/* Conflicts Section */}
            {computedStats.conflicts.length > 0 && (
                <Card className="border-destructive/30 border-2">
                    <CardHeader>
                        <CardTitle className="text-destructive flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5" />
                            Schedule Conflicts
                        </CardTitle>
                        <CardDescription>Overlapping schedules that require attention</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4">
                            {computedStats.conflicts.map((conflict, i) => (
                                <div key={i} className="border-destructive/20 bg-destructive/5 flex items-start gap-4 rounded-xl border-2 p-4">
                                    <div className="bg-destructive/20 rounded-lg p-2">
                                        <AlertTriangle className="text-destructive h-5 w-5" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="mb-2 flex items-center gap-2">
                                            <h4 className="text-destructive font-semibold">
                                                {conflict.conflict_type === "room" ? "Room Double Booked" : "Faculty Overlap"}
                                            </h4>
                                            <Badge variant="outline">
                                                {conflict.day} - {conflict.time}
                                            </Badge>
                                        </div>
                                        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="bg-background rounded-xl border-2 p-3 text-sm">
                                                <div className="mb-1 font-semibold">{conflict.class_1.subject_code}</div>
                                                <div className="text-muted-foreground">Section: {conflict.class_1.section}</div>
                                                <div className="text-muted-foreground">Room: {conflict.class_1.room || "TBA"}</div>
                                                <div className="text-muted-foreground">Faculty: {conflict.class_1.faculty || "TBA"}</div>
                                            </div>
                                            <div className="bg-background rounded-xl border-2 p-3 text-sm">
                                                <div className="mb-1 font-semibold">{conflict.class_2.subject_code}</div>
                                                <div className="text-muted-foreground">Section: {conflict.class_2.section}</div>
                                                <div className="text-muted-foreground">Room: {conflict.class_2.room || "TBA"}</div>
                                                <div className="text-muted-foreground">Faculty: {conflict.class_2.faculty || "TBA"}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

export default function SchedulingAnalytics({ user, schedule_data, stats, filters }: SchedulingAnalyticsProps) {
    const [selectedClass, setSelectedClass] = React.useState<ClassScheduleData | null>(null);
    const [activeTab, setActiveTab] = React.useState("course");

    return (
        <AdminLayout user={user} title="Scheduling Analytics">
            <Head title="Scheduling Analytics" />

            <div className="space-y-6">
                {/* Header Section */}
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="flex items-center gap-3 text-3xl font-bold tracking-tight">
                            <Sparkles className="text-primary h-8 w-8" />
                            Scheduling Analytics
                        </h1>
                        <p className="text-muted-foreground mt-1">View and analyze academic schedules by room, faculty, course, or student.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.reload({ only: ["schedule_data", "stats", "filters"] })}
                            className="hover:border-primary/30 border-2"
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Sync Data
                        </Button>
                    </div>
                </div>

                {/* Main Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                    <TabsList className="grid h-12 w-full max-w-2xl grid-cols-4 p-1">
                        <TabsTrigger
                            value="room"
                            className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground flex items-center gap-2"
                        >
                            <Building2 className="h-4 w-4" />
                            <span className="hidden sm:inline">By Room</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="faculty"
                            className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground flex items-center gap-2"
                        >
                            <UserIcon className="h-4 w-4" />
                            <span className="hidden sm:inline">By Faculty</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="course"
                            className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground flex items-center gap-2"
                        >
                            <BookOpen className="h-4 w-4" />
                            <span className="hidden sm:inline">By Course</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="student"
                            className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground flex items-center gap-2"
                        >
                            <GraduationCap className="h-4 w-4" />
                            <span className="hidden sm:inline">By Student</span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="room">
                        <RoomScheduleView scheduleData={schedule_data} rooms={filters.available_rooms} onClassClick={setSelectedClass} />
                    </TabsContent>

                    <TabsContent value="faculty">
                        <FacultyScheduleView scheduleData={schedule_data} faculty={filters.available_faculty} onClassClick={setSelectedClass} />
                    </TabsContent>

                    <TabsContent value="course">
                        <CourseScheduleView scheduleData={schedule_data} filters={filters} stats={stats} onClassClick={setSelectedClass} />
                    </TabsContent>

                    <TabsContent value="student">
                        <StudentScheduleView />
                    </TabsContent>
                </Tabs>
            </div>

            <ClassDetailsDialog classItem={selectedClass} open={!!selectedClass} onOpenChange={(open) => !open && setSelectedClass(null)} />
        </AdminLayout>
    );
}
