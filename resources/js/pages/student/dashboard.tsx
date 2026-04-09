import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import { OnboardingExperience, type OnboardingFeatureData } from "@/components/onboarding-experience";
import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { HoverCard, HoverCardContent, HoverCardTrigger } from "@/components/ui/hover-card";
import { Progress } from "@/components/ui/progress";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { type User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    AlertCircle,
    ArrowRight,
    Bell,
    BookOpen,
    Calendar,
    CalendarDays,
    CheckCircle2,
    Clock,
    CreditCard,
    Eye,
    EyeOff,
    GraduationCap,
    LayoutGrid,
    MapPin,
    Sparkles,
    TrendingUp,
    Trophy,
    Zap,
} from "lucide-react";
import { useMemo, useState } from "react";
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

// --- Types ---

interface Branding {
    currency: string;
}

interface GradeInfo {
    prelim: number | null;
    midterm: number | null;
    finals: number | null;
    average: number | null;
}

interface ClassInfo {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    faculty_name: string;
    schedule: string;
    room: string;
    grades: GradeInfo;
}

interface AnnouncementInfo {
    id: number;
    title: string;
    content: string;
    date: string;
    type: "info" | "warning" | "important";
}

interface StudentDashboardProps {
    user: User;
    student_data: {
        student_id: number | string;
        student_name: string;
        course: string | null;
        academic_year: number;
        semester: number;
        school_year: string;
        enrolled_classes: ClassInfo[];
        announcements: AnnouncementInfo[];
        total_units: number;
        tuition_balance: number;
        clearance_status: boolean;
    };
    id_card: {
        card_data: IdCardData;
        photo_url: string | null;
        qr_code: string;
        is_valid: boolean;
    } | null;
}

// --- Helpers ---

const timeToFloat = (timeStr: string) => {
    const [time, period] = timeStr.trim().split(/\s+/);
    let [hours, minutes] = time.split(":").map(Number);

    if (period?.toUpperCase() === "PM" && hours !== 12) hours += 12;
    if (period?.toUpperCase() === "AM" && hours === 12) hours = 0;

    return hours + minutes / 60;
};

const parseSchedule = (scheduleStr: string) => {
    if (!scheduleStr || scheduleStr === "TBA") return [];

    // Regex to capture "Day/Day Time - Time" segments
    // Matches: "Mon/Wed 10:00 AM - 11:30 AM" or "Fri 1:00 PM - 2:00 PM"
    const regex = /([A-Za-z\/\s]+)\s+(\d{1,2}:\d{2}\s*[AP]M)\s*-\s*(\d{1,2}:\d{2}\s*[AP]M)/gi;
    const segments: { days: string[]; start: number; end: number; timeString: string }[] = [];

    let match;
    while ((match = regex.exec(scheduleStr)) !== null) {
        const daysRaw = match[1].trim();
        const startStr = match[2];
        const endStr = match[3];

        const days: string[] = [];
        const dayMap: Record<string, string> = {
            M: "Mon",
            MON: "Mon",
            MONDAY: "Mon",
            T: "Tue",
            TUE: "Tue",
            TUESDAY: "Tue",
            W: "Wed",
            WED: "Wed",
            WEDNESDAY: "Wed",
            TH: "Thu",
            THU: "Thu",
            THURSDAY: "Thu",
            F: "Fri",
            FRI: "Fri",
            FRIDAY: "Fri",
            S: "Sat",
            SAT: "Sat",
            SATURDAY: "Sat",
            SU: "Sun",
            SUN: "Sun",
            SUNDAY: "Sun",
        };

        const parts = daysRaw.split(/[\/\s,]+/).filter(Boolean);

        parts.forEach((part) => {
            const key = part.toUpperCase();
            if (dayMap[key]) days.push(dayMap[key]);
            else if (key.startsWith("TH")) days.push("Thu");
        });

        if (days.length > 0) {
            segments.push({
                days,
                start: timeToFloat(startStr),
                end: timeToFloat(endStr),
                timeString: `${startStr} - ${endStr}`,
            });
        }
    }

    return segments;
};

// --- Components ---

const WeeklySchedule = ({ classes }: { classes: ClassInfo[] }) => {
    const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const currentDayIndex = new Date().getDay() - 1;
    const currentDayName = days[currentDayIndex] || "Mon";

    const [selectedDay, setSelectedDay] = useState(currentDayName);

    // Process data to map subjects to days (for Matrix)
    const matrixClasses = useMemo(() => {
        return classes.map((cls) => {
            const schedules = parseSchedule(cls.schedule);
            const scheduleMap: Record<string, string[]> = {};

            days.forEach((day) => (scheduleMap[day] = []));

            schedules.forEach((sched) => {
                sched.days.forEach((day) => {
                    if (scheduleMap[day]) {
                        scheduleMap[day].push(sched.timeString);
                    }
                });
            });

            return { ...cls, scheduleMap };
        });
    }, [classes]);

    // Process data for Mobile List View (Day specific)
    const mobileDailyClasses = useMemo(() => {
        const dailyItems: { cls: ClassInfo; time: string; start: number; end: number }[] = [];

        classes.forEach((cls) => {
            const schedules = parseSchedule(cls.schedule);
            schedules.forEach((sched) => {
                if (sched.days.includes(selectedDay)) {
                    dailyItems.push({
                        cls,
                        time: sched.timeString,
                        start: sched.start,
                        end: sched.end,
                    });
                }
            });
        });

        return dailyItems.sort((a, b) => a.start - b.start);
    }, [classes, selectedDay]);

    return (
        <div className="space-y-6">
            {/* Header Section */}
            <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h3 className="text-foreground flex items-center gap-2 text-xl font-bold tracking-tight">
                        <div className="bg-primary/10 rounded-xl p-2 shadow-sm">
                            <CalendarDays className="text-primary h-6 w-6" />
                        </div>
                        <span className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text text-transparent">My Class Matrix</span>
                    </h3>
                    <p className="text-muted-foreground ml-12 text-sm font-medium">Your weekly academic adventure</p>
                </div>

                {/* Desktop Legend */}
                <div className="hidden items-center gap-3 text-sm md:flex">
                    <div className="bg-background/60 flex items-center gap-2 rounded-full border px-4 py-1.5 shadow-sm backdrop-blur-sm">
                        <BookOpen className="text-primary h-4 w-4" />
                        <span className="text-foreground font-bold">{classes.length}</span>
                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Subjects</span>
                    </div>
                </div>
            </div>

            {/* --- MOBILE VIEW (Tabs + List) --- */}
            <div className="space-y-4 md:hidden">
                {/* Day Selector */}
                <ScrollArea className="w-full pb-2">
                    <div className="flex min-w-max space-x-2 px-1">
                        {days.map((day) => {
                            const isSelected = selectedDay === day;
                            const isToday = day === currentDayName;
                            return (
                                <button
                                    key={day}
                                    onClick={() => setSelectedDay(day)}
                                    className={`flex h-16 w-14 flex-col items-center justify-center rounded-2xl border transition-all duration-200 ${
                                        isSelected
                                            ? "bg-primary text-primary-foreground border-primary shadow-primary/25 scale-105 shadow-lg"
                                            : "bg-card text-muted-foreground border-border hover:bg-muted/50"
                                    } ${isToday && !isSelected ? "border-primary/50 bg-primary/5" : ""} `}
                                >
                                    <span className="text-[10px] font-bold tracking-wider uppercase opacity-80">{day}</span>
                                    {isToday && !isSelected && <div className="bg-primary mt-1 h-1 w-1 rounded-full" />}
                                </button>
                            );
                        })}
                    </div>
                    <ScrollBar orientation="horizontal" className="hidden" />
                </ScrollArea>

                {/* Daily Schedule List */}
                <div className="min-h-[300px] space-y-3">
                    <AnimatePresence mode="wait">
                        {mobileDailyClasses.length > 0 ? (
                            <div className="grid gap-3">
                                {mobileDailyClasses.map((item, idx) => (
                                    <motion.div
                                        key={`${item.cls.id}-${item.start}`}
                                        initial={{ opacity: 0, y: 10 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        exit={{ opacity: 0, y: -10 }}
                                        transition={{ delay: idx * 0.05 }}
                                    >
                                        <div className="bg-card relative flex overflow-hidden rounded-2xl border shadow-sm transition-shadow hover:shadow-md">
                                            {/* Color Strip */}
                                            <div
                                                className="bg-primary w-2"
                                                style={{ backgroundColor: `hsl(var(--chart-${(item.cls.id % 5) + 1}))` }}
                                            />

                                            <div className="flex-1 p-4">
                                                <div className="mb-2 flex items-start justify-between">
                                                    <div className="flex flex-col">
                                                        <span className="text-lg leading-tight font-bold">{item.cls.subject_code}</span>
                                                        <span className="text-muted-foreground line-clamp-1 text-xs">{item.cls.subject_title}</span>
                                                    </div>
                                                    <Badge variant="secondary" className="font-mono text-xs">
                                                        {item.time.split(" - ")[0]}
                                                    </Badge>
                                                </div>

                                                <div className="mt-3 grid grid-cols-2 gap-2">
                                                    <div className="text-muted-foreground bg-muted/30 flex items-center gap-1.5 rounded-lg p-1.5 text-xs">
                                                        <MapPin className="text-primary h-3.5 w-3.5" />
                                                        <span className="truncate font-medium">{item.cls.room}</span>
                                                    </div>
                                                    <div className="text-muted-foreground bg-muted/30 flex items-center gap-1.5 rounded-lg p-1.5 text-xs">
                                                        <User className="text-primary h-3.5 w-3.5" />
                                                        <span className="truncate font-medium">{item.cls.faculty_name.split(" ").pop()}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </motion.div>
                                ))}
                            </div>
                        ) : (
                            <motion.div
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                className="bg-muted/10 flex h-64 flex-col items-center justify-center rounded-3xl border-2 border-dashed p-8 text-center"
                            >
                                <div className="bg-muted/30 mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                                    <Sparkles className="text-muted-foreground/50 h-8 w-8" />
                                </div>
                                <h4 className="text-foreground font-semibold">Free Day!</h4>
                                <p className="text-muted-foreground mt-1 text-sm">No classes scheduled for {selectedDay}.</p>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </div>

            {/* --- DESKTOP VIEW (Matrix) --- */}
            <div className="border-border/50 shadow-primary/5 bg-background/50 relative hidden overflow-hidden rounded-3xl border shadow-2xl backdrop-blur-xl md:block">
                {/* Decorative background elements */}
                <div className="bg-primary/10 pointer-events-none absolute -top-24 -right-24 h-64 w-64 animate-pulse rounded-full blur-3xl" />
                <div className="bg-primary/5 pointer-events-none absolute top-1/2 left-1/4 h-96 w-96 rounded-full blur-3xl" />

                {/* Scroll container */}
                <ScrollArea className="w-full">
                    <div className="min-w-[800px]">
                        {" "}
                        {/* Scaled down min-width */}
                        <Table className="border-separate border-spacing-0">
                            <TableHeader>
                                <TableRow className="border-none hover:bg-transparent">
                                    <TableHead className="bg-background/95 border-border/50 sticky left-0 z-40 h-auto w-[240px] border-r border-b p-0 align-bottom backdrop-blur-xl">
                                        <div className="flex items-center gap-3 p-4">
                                            <div className="from-primary to-primary/80 shadow-primary/20 flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br shadow-lg">
                                                <BookOpen className="text-primary-foreground h-4 w-4" />
                                            </div>
                                            <div>
                                                <div className="text-foreground text-sm font-bold">Subject</div>
                                            </div>
                                        </div>
                                    </TableHead>
                                    {days.map((day, i) => {
                                        const isToday = i === currentDayIndex;
                                        return (
                                            <TableHead
                                                key={day}
                                                className={`border-border/50 h-auto border-b p-0 text-center align-bottom transition-colors ${isToday ? "bg-primary/5" : "bg-background/50"}`}
                                            >
                                                <div className="relative px-2 py-3">
                                                    {isToday && (
                                                        <div className="bg-primary absolute top-0 right-0 left-0 h-1 shadow-[0_0_10px_currentColor]" />
                                                    )}
                                                    <div
                                                        className={`inline-flex w-full flex-col items-center justify-center rounded-lg py-1.5 transition-all ${isToday ? "bg-primary/10 shadow-inner" : ""} `}
                                                    >
                                                        <span
                                                            className={`text-[10px] font-black tracking-widest uppercase ${isToday ? "text-primary" : "text-muted-foreground"}`}
                                                        >
                                                            {day}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableHead>
                                        );
                                    })}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {matrixClasses.length > 0 ? (
                                    matrixClasses.map((cls, idx) => (
                                        <TableRow key={cls.id} className="group border-none hover:bg-transparent">
                                            {/* Sticky Subject Card */}
                                            <TableCell className="bg-background/95 border-border/50 group-hover:bg-accent/5 sticky left-0 z-30 border-r border-b p-0 align-top backdrop-blur-xl transition-colors">
                                                <div className="relative h-full p-3">
                                                    {/* Left accent bar */}
                                                    <div
                                                        className="absolute top-3 bottom-3 left-0 w-1 rounded-r-full transition-all group-hover:w-1.5"
                                                        style={{ backgroundColor: `hsl(var(--chart-${(idx % 5) + 1}))` }}
                                                    />

                                                    <div className="relative z-10 flex flex-col gap-1.5 pl-3">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="text-foreground text-xs font-bold tracking-tight">
                                                                {cls.subject_code}
                                                            </span>
                                                            <Badge
                                                                variant="secondary"
                                                                className="border-border h-4 px-1 py-0 text-[8px] font-bold tracking-wider uppercase"
                                                            >
                                                                {cls.section}
                                                            </Badge>
                                                        </div>

                                                        <div
                                                            className="text-muted-foreground line-clamp-2 text-[10px] leading-snug font-medium"
                                                            title={cls.subject_title}
                                                        >
                                                            {cls.subject_title}
                                                        </div>

                                                        <div className="text-primary bg-primary/10 border-primary/20 flex w-fit items-center gap-1.5 rounded-md border px-1.5 py-0.5 text-[9px] font-semibold">
                                                            <MapPin className="h-2.5 w-2.5" />
                                                            {cls.room}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Day Cells */}
                                            {days.map((day, i) => {
                                                const isToday = i === currentDayIndex;
                                                return (
                                                    <TableCell
                                                        key={`${cls.id}-${day}`}
                                                        className={`border-border/50 relative h-[80px] min-w-[120px] border-b p-1.5 align-top transition-colors ${isToday ? "bg-primary/5" : ""}`}
                                                    >
                                                        {/* Empty State Pattern */}
                                                        {cls.scheduleMap[day].length === 0 && (
                                                            <div
                                                                className="pointer-events-none absolute inset-0 opacity-[0.03] transition-opacity group-hover:opacity-[0.05]"
                                                                style={{
                                                                    backgroundImage: "radial-gradient(circle, currentColor 1px, transparent 1px)",
                                                                    backgroundSize: "16px 16px",
                                                                }}
                                                            />
                                                        )}

                                                        <div className="flex h-full flex-col gap-1.5">
                                                            <AnimatePresence>
                                                                {cls.scheduleMap[day].length > 0
                                                                    ? cls.scheduleMap[day].map((time, timeIdx) => (
                                                                          <HoverCard key={timeIdx} openDelay={0} closeDelay={0}>
                                                                              <HoverCardTrigger asChild>
                                                                                  <motion.div
                                                                                      initial={{ opacity: 0, scale: 0.8, y: 5 }}
                                                                                      animate={{ opacity: 1, scale: 1, y: 0 }}
                                                                                      transition={{ delay: 0.05 * idx + 0.02 * timeIdx }}
                                                                                      whileHover={{ scale: 1.02, y: -2 }}
                                                                                      className="group/card relative cursor-help overflow-hidden rounded-lg border p-2 shadow-sm transition-all"
                                                                                      style={{
                                                                                          backgroundColor: `hsl(var(--chart-${(idx % 5) + 1}) / 0.1)`,
                                                                                          borderColor: `hsl(var(--chart-${(idx % 5) + 1}) / 0.3)`,
                                                                                      }}
                                                                                  >
                                                                                      <div className="flex flex-col items-center justify-center gap-0.5 text-center">
                                                                                          <div className="flex items-center gap-1">
                                                                                              <Clock
                                                                                                  className="h-2.5 w-2.5 opacity-70"
                                                                                                  style={{
                                                                                                      color: `hsl(var(--chart-${(idx % 5) + 1}))`,
                                                                                                  }}
                                                                                              />
                                                                                              <span className="text-foreground text-[10px] leading-none font-bold">
                                                                                                  {time.split(" - ")[0]}
                                                                                              </span>
                                                                                          </div>
                                                                                          <span className="text-muted-foreground text-[9px] leading-none font-medium opacity-80">
                                                                                              {time.split(" - ")[1]}
                                                                                          </span>
                                                                                      </div>
                                                                                  </motion.div>
                                                                              </HoverCardTrigger>
                                                                              <HoverCardContent
                                                                                  className="w-80 overflow-hidden border-none p-0 shadow-xl"
                                                                                  align="start"
                                                                              >
                                                                                  <div className="relative">
                                                                                      <div
                                                                                          className="absolute inset-0 opacity-20"
                                                                                          style={{
                                                                                              backgroundColor: `hsl(var(--chart-${(idx % 5) + 1}))`,
                                                                                          }}
                                                                                      />
                                                                                      <div className="relative z-10 p-4">
                                                                                          <div className="mb-3 flex items-start justify-between gap-4">
                                                                                              <div>
                                                                                                  <h4 className="text-lg leading-tight font-bold">
                                                                                                      {cls.subject_title}
                                                                                                  </h4>
                                                                                                  <div className="text-muted-foreground mt-1 font-mono text-xs">
                                                                                                      {cls.subject_code}
                                                                                                  </div>
                                                                                              </div>
                                                                                              <div className="bg-background text-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-bold shadow-sm">
                                                                                                  {cls.subject_code.charAt(0)}
                                                                                              </div>
                                                                                          </div>

                                                                                          <div className="bg-background/50 space-y-2 rounded-lg p-3 backdrop-blur-sm">
                                                                                              <div className="flex items-center justify-between text-sm">
                                                                                                  <span className="text-muted-foreground flex items-center gap-2">
                                                                                                      <Clock className="h-4 w-4" /> Time
                                                                                                  </span>
                                                                                                  <span className="font-semibold">{time}</span>
                                                                                              </div>
                                                                                              <Separator className="bg-foreground/5" />
                                                                                              <div className="flex items-center justify-between text-sm">
                                                                                                  <span className="text-muted-foreground flex items-center gap-2">
                                                                                                      <MapPin className="h-4 w-4" /> Room
                                                                                                  </span>
                                                                                                  <span className="font-semibold">{cls.room}</span>
                                                                                              </div>
                                                                                              <Separator className="bg-foreground/5" />
                                                                                              <div className="flex items-center justify-between text-sm">
                                                                                                  <span className="text-muted-foreground flex items-center gap-2">
                                                                                                      <User className="h-4 w-4" /> Instructor
                                                                                                  </span>
                                                                                                  <span className="font-semibold">
                                                                                                      {cls.faculty_name}
                                                                                                  </span>
                                                                                              </div>
                                                                                          </div>
                                                                                      </div>
                                                                                  </div>
                                                                              </HoverCardContent>
                                                                          </HoverCard>
                                                                      ))
                                                                    : null}
                                                            </AnimatePresence>
                                                        </div>
                                                    </TableCell>
                                                );
                                            })}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-48 text-center">
                                            <div className="text-muted-foreground/40 flex flex-col items-center justify-center gap-4">
                                                <div className="bg-muted/30 border-muted flex h-16 w-16 items-center justify-center rounded-3xl border-2 border-dashed">
                                                    <Calendar className="h-8 w-8" />
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-foreground/70 font-semibold">No Classes Found</p>
                                                    <p className="text-xs">Your schedule for this week is clear.</p>
                                                </div>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </ScrollArea>
            </div>
        </div>
    );
};

const StatCard = ({
    icon: Icon,
    label,
    value,
    subValue,
    color,
    delay = 0,
    isPrivate = false,
}: {
    icon: any;
    label: string;
    value: string | number;
    subValue?: string;
    color: string;
    delay?: number;
    isPrivate?: boolean;
}) => {
    const [revealed, setRevealed] = useState(!isPrivate);

    return (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay, duration: 0.4 }}>
            <Card className="group relative h-full overflow-hidden border-none shadow-sm transition-all duration-300 hover:shadow-md">
                <div className={`absolute top-0 right-0 p-3 opacity-10 transition-opacity group-hover:opacity-20 ${color}`}>
                    <Icon className="-mt-4 -mr-4 h-16 w-16 rotate-12 transform" />
                </div>
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div className={`bg-opacity-10 rounded-xl p-3 ${color.replace("text-", "bg-")} ${color}`}>
                            <Icon className="h-6 w-6" />
                        </div>
                        {isPrivate && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-muted-foreground hover:text-foreground h-8 w-8"
                                onClick={() => setRevealed(!revealed)}
                            >
                                {revealed ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </Button>
                        )}
                    </div>
                    <div>
                        <p className="text-muted-foreground mb-1 text-sm font-medium">{label}</p>
                        <div className="flex items-end gap-2">
                            <h3 className="text-2xl font-bold tracking-tight">{revealed ? value : "••••••"}</h3>
                        </div>
                        {subValue && <p className="text-muted-foreground mt-2 flex items-center gap-1 text-xs">{subValue}</p>}
                    </div>
                </CardContent>
            </Card>
        </motion.div>
    );
};

const CourseTicket = ({ cls, index }: { cls: ClassInfo; index: number }) => {
    return (
        <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: index * 0.1 }} className="group relative">
            <div className="bg-card border-border relative flex transform flex-col overflow-hidden rounded-2xl border shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg md:flex-row">
                {/* Left Decorative Strip */}
                <div className="bg-primary absolute top-0 bottom-0 left-0 w-2 md:w-3" />

                {/* Main Content */}
                <div className="flex-1 p-5 pl-7 md:pl-8">
                    <div className="mb-2 flex items-start justify-between">
                        <Badge variant="outline" className="bg-muted/50 font-mono text-xs">
                            {cls.subject_code}
                        </Badge>
                        <span className="text-muted-foreground text-xs font-medium">Section {cls.section}</span>
                    </div>
                    <h3 className="group-hover:text-primary mb-3 text-lg leading-tight font-bold transition-colors">{cls.subject_title}</h3>
                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                        <div className="bg-muted flex h-6 w-6 items-center justify-center rounded-full">
                            <span className="text-xs font-bold">{cls.faculty_name.charAt(0)}</span>
                        </div>
                        <span>{cls.faculty_name}</span>
                    </div>
                </div>

                {/* Right Info Section (Ticket Stub style) */}
                <div className="bg-muted/50 border-border relative flex min-w-[160px] flex-col justify-center gap-3 border-t border-dashed p-5 md:border-t-0 md:border-l">
                    {/* Punchout circles for ticket effect */}
                    <div className="bg-background absolute -top-3 hidden h-6 w-6 rounded-full md:-top-3 md:-left-3 md:block" />
                    <div className="bg-background absolute -bottom-3 hidden h-6 w-6 rounded-full md:-bottom-3 md:-left-3 md:block" />

                    <div className="flex items-center gap-3">
                        <Clock className="text-primary h-4 w-4" />
                        <div className="flex flex-col">
                            <span className="text-muted-foreground text-[10px] font-bold uppercase">Time</span>
                            <span className="max-w-[120px] truncate text-xs font-medium" title={cls.schedule}>
                                {cls.schedule}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <MapPin className="text-destructive h-4 w-4" />
                        <div className="flex flex-col">
                            <span className="text-muted-foreground text-[10px] font-bold uppercase">Room</span>
                            <span className="text-xs font-medium">{cls.room}</span>
                        </div>
                    </div>
                </div>
            </div>
        </motion.div>
    );
};

export default function StudentDashboard({ user, student_data, id_card }: StudentDashboardProps) {
    const { props } = usePage<{
        branding?: Branding;
        onboarding?: {
            forceOnLogin?: boolean;
            features?: OnboardingFeatureData[];
            dismissEndpoint?: string;
        };
    }>();
    const currency = props.branding?.currency || "PHP";
    const currencySymbol = currency === "USD" ? "$" : "₱";

    const shouldForceOnboarding = props.onboarding?.forceOnLogin ?? false;
    const onboardingFeatures = props.onboarding?.features ?? [];
    const dismissEndpoint = props.onboarding?.dismissEndpoint;
    const hasOnboardingFeatures = onboardingFeatures.length > 0;
    const onboardingEnabled = shouldForceOnboarding || hasOnboardingFeatures;

    const [activeTab, setActiveTab] = useState("overview");
    const [qrCode, setQrCode] = useState(id_card?.qr_code ?? "");
    const [isRefreshingQr, setIsRefreshingQr] = useState(false);

    // Handle QR code refresh
    const handleRefreshQr = async () => {
        setIsRefreshingQr(true);
        try {
            const response = await fetch("/student/id-card/refresh", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
                },
            });
            if (response.ok) {
                const data = await response.json();
                setQrCode(data.qr_code);
            }
        } catch (error) {
            console.error("Failed to refresh QR code:", error);
        } finally {
            setIsRefreshingQr(false);
        }
    };

    // Handle expand to full ID card view
    const handleExpandIdCard = () => {
        router.visit("/student/id-card/view");
    };

    // Greeting Logic
    const getGreeting = () => {
        const hour = new Date().getHours();
        if (hour < 5) return "Burning the Midnight Oil";
        if (hour < 12) return "Good Morning";
        if (hour < 18) return "Good Afternoon";
        return "Good Evening";
    };

    // Calculate GWA
    const gwa = useMemo(() => {
        const classesWithGrades = student_data.enrolled_classes.filter((c) => c.grades.average);
        if (classesWithGrades.length === 0) return null;
        const total = classesWithGrades.reduce((acc, curr) => acc + (curr.grades.average || 0), 0);
        return (total / classesWithGrades.length).toFixed(2);
    }, [student_data.enrolled_classes]);

    // Prepare Chart Data
    const chartData = useMemo(() => {
        return student_data.enrolled_classes.map((cls) => ({
            name: cls.subject_code,
            grade: cls.grades.average ? (cls.grades.average > 3.0 ? 5.0 : cls.grades.average) : 0, // Invert or normalize logic might be needed depending on grading system. Assuming 1.0 is high, 5.0 is fail.
            // Let's assume standard PH grading: 1.0 (High) to 5.0 (Fail).
            // For charting 'success', we might want to flip it visually or just show the value.
            // Let's just show the raw value but color code it.
            fullGrade: cls.grades,
        }));
    }, [student_data.enrolled_classes]);

    // Custom Tooltip for Chart
    const CustomTooltip = ({ active, payload, label }: any) => {
        if (active && payload && payload.length) {
            const data = payload[0].payload;
            return (
                <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-800 dark:bg-slate-900">
                    <p className="mb-2 text-sm font-bold">{label}</p>
                    <div className="space-y-1 text-xs">
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">Prelim:</span>
                            <span className="font-mono">{data.fullGrade.prelim || "-"}</span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">Midterm:</span>
                            <span className="font-mono">{data.fullGrade.midterm || "-"}</span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">Finals:</span>
                            <span className="font-mono">{data.fullGrade.finals || "-"}</span>
                        </div>
                        <Separator className="my-1" />
                        <div className="flex justify-between gap-4 font-bold">
                            <span>Average:</span>
                            <span className={data.grade > 3.0 ? "text-red-500" : "text-green-500"}>{data.grade || "N/A"}</span>
                        </div>
                    </div>
                </div>
            );
        }
        return null;
    };

    return (
        <StudentLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Student Hub" />
            <OnboardingExperience
                variant="student"
                userId={user.id}
                enabled={onboardingEnabled}
                force={!hasOnboardingFeatures && shouldForceOnboarding}
                features={hasOnboardingFeatures ? onboardingFeatures : undefined}
                onDismiss={(featureKey) => {
                    if (!dismissEndpoint) return;
                    router.post(dismissEndpoint, { feature_key: featureKey }, { preserveScroll: true });
                }}
            />

            <div className="mx-auto w-full max-w-7xl space-y-8 p-4 md:p-6">
                {/* Hero Section */}
                <section className="relative">
                    {/* Background Mesh (Optional CSS art) */}
                    <div className="pointer-events-none absolute top-0 right-0 -z-10 h-[300px] w-[300px] rounded-full bg-blue-500/10 opacity-50 blur-3xl" />
                    <div className="pointer-events-none absolute bottom-0 left-0 -z-10 h-[200px] w-[200px] rounded-full bg-purple-500/10 opacity-50 blur-3xl" />

                    <div className="flex flex-col items-start justify-between gap-6 md:flex-row md:items-end">
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.5 }}
                            className="space-y-2"
                        >
                            <div className="bg-muted text-muted-foreground mb-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium">
                                <Sparkles className="text-primary h-3 w-3" />
                                <span>
                                    {student_data.semester === 1 ? "1st" : "2nd"} Semester, {student_data.school_year}
                                </span>
                            </div>
                            <h1 className="text-foreground text-4xl font-extrabold tracking-tight md:text-5xl">
                                {getGreeting()}, <br />
                                <span className="text-primary">
                                    {student_data.student_name.split(",")[1] || student_data.student_name.split(" ")[0]}
                                </span>
                            </h1>
                            <p className="text-muted-foreground max-w-xl text-lg">
                                Ready to conquer your <span className="text-foreground font-semibold">{student_data.course}</span> journey? You're
                                enrolled in {student_data.enrolled_classes.length} subjects this term.
                            </p>
                        </motion.div>

                        {/* Semester Progress Mock */}
                        <motion.div
                            initial={{ opacity: 0, scale: 0.9 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ delay: 0.2 }}
                            className="bg-card border-border hidden w-full min-w-[240px] rounded-2xl border p-4 shadow-sm md:block md:w-auto"
                        >
                            <div className="mb-2 flex items-center justify-between">
                                <span className="text-muted-foreground text-xs font-bold uppercase">Semester Progress</span>
                                <span className="text-primary text-xs font-bold">65%</span>
                            </div>
                            <Progress value={65} className="mb-3 h-2" />
                            <p className="text-muted-foreground text-center text-xs">Week 12 of 18 • Finals approaching</p>
                        </motion.div>
                    </div>
                </section>

                {/* Action Grid */}
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
                    {/* LEFT: Main Dashboard Content (8 cols) */}
                    <div className="space-y-8 lg:col-span-8">
                        {/* Quick Stats */}
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <StatCard
                                icon={Trophy}
                                label="GWA"
                                value={gwa || "N/A"}
                                subValue={gwa ? "Keep it up!" : "No grades yet"}
                                color="text-amber-500"
                                delay={0.1}
                            />
                            <StatCard
                                icon={BookOpen}
                                label="Units"
                                value={student_data.total_units}
                                subValue="Enrolled"
                                color="text-blue-500"
                                delay={0.2}
                            />
                            <StatCard
                                icon={CheckCircle2}
                                label="Clearance"
                                value={student_data.clearance_status ? "Cleared" : "Pending"}
                                subValue={student_data.clearance_status ? "All good" : "Requirements needed"}
                                color={student_data.clearance_status ? "text-emerald-500" : "text-rose-500"}
                                delay={0.3}
                            />
                            <StatCard
                                icon={CreditCard}
                                label="Balance"
                                value={`${currencySymbol}${student_data.tuition_balance.toLocaleString()}`}
                                subValue="Tuition"
                                color="text-purple-500"
                                delay={0.4}
                                isPrivate={true}
                            />
                        </div>

                        {/* Content Tabs */}
                        <Tabs defaultValue="overview" className="w-full" onValueChange={setActiveTab}>
                            <div className="-mx-4 mb-6 overflow-x-auto px-4 pb-2 md:mx-0 md:px-0 md:pb-0">
                                <TabsList className="bg-muted flex h-12 w-full rounded-xl p-1 md:inline-flex md:w-auto">
                                    <TabsTrigger
                                        value="overview"
                                        className="data-[state=active]:bg-card h-10 flex-1 rounded-lg px-3 data-[state=active]:shadow-sm md:flex-none md:px-6"
                                    >
                                        <LayoutGrid className="mr-2 h-4 w-4" />
                                        Overview
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="schedule"
                                        className="data-[state=active]:bg-card h-10 flex-1 rounded-lg px-3 data-[state=active]:shadow-sm md:flex-none md:px-6"
                                    >
                                        <Calendar className="mr-2 h-4 w-4" />
                                        Schedule
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="performance"
                                        className="data-[state=active]:bg-card h-10 flex-1 rounded-lg px-3 data-[state=active]:shadow-sm md:flex-none md:px-6"
                                    >
                                        <Trophy className="mr-2 h-4 w-4" />
                                        Grades
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            {/* TAB: OVERVIEW (Course Tickets) */}
                            <TabsContent value="overview" className="mt-0 space-y-6">
                                <div className="flex items-center justify-between">
                                    <h3 className="flex items-center gap-2 text-lg font-bold">
                                        <BookOpen className="h-5 w-5 text-blue-500" />
                                        Current Courses
                                    </h3>
                                    <Badge variant="secondary">{student_data.enrolled_classes.length} Subjects</Badge>
                                </div>

                                <div className="grid gap-4">
                                    {student_data.enrolled_classes.length > 0 ? (
                                        student_data.enrolled_classes.map((cls, idx) => <CourseTicket key={cls.id} cls={cls} index={idx} />)
                                    ) : (
                                        <Card className="flex flex-col items-center justify-center border-2 border-dashed bg-slate-50 py-12 text-center dark:bg-slate-900/50">
                                            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                                <BookOpen className="h-8 w-8 text-slate-400" />
                                            </div>
                                            <h3 className="text-lg font-semibold">No Enrollments Yet</h3>
                                            <p className="text-muted-foreground">You are not officially enrolled in any classes for this term.</p>
                                        </Card>
                                    )}
                                </div>
                            </TabsContent>

                            {/* TAB: SCHEDULE (Timeline) */}
                            <TabsContent value="schedule" className="mt-0">
                                <WeeklySchedule classes={student_data.enrolled_classes} />
                            </TabsContent>

                            {/* TAB: PERFORMANCE (Charts) */}
                            <TabsContent value="performance" className="mt-0 space-y-6">
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                    {/* Grade Chart */}
                                    <Card className="shadow-sm md:col-span-2">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <TrendingUp className="text-primary h-5 w-5" />
                                                Performance Overview
                                            </CardTitle>
                                            <p className="text-muted-foreground text-sm">Visualizing your academic progress</p>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="mt-2 h-[300px] w-full">
                                                <ResponsiveContainer width="100%" height="100%">
                                                    <BarChart data={chartData} margin={{ top: 20, right: 10, left: 0, bottom: 5 }}>
                                                        <CartesianGrid
                                                            strokeDasharray="3 3"
                                                            vertical={false}
                                                            stroke="hsl(var(--border))"
                                                            opacity={0.5}
                                                        />
                                                        <XAxis
                                                            dataKey="name"
                                                            axisLine={false}
                                                            tickLine={false}
                                                            tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 12 }}
                                                            dy={10}
                                                        />
                                                        <YAxis
                                                            axisLine={false}
                                                            tickLine={false}
                                                            tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 12 }}
                                                            domain={[0, 5]}
                                                            reversed
                                                        />
                                                        <Tooltip
                                                            cursor={{ fill: "hsl(var(--muted)/0.3)" }}
                                                            content={({ active, payload, label }) => {
                                                                if (active && payload && payload.length) {
                                                                    const data = payload[0].payload;
                                                                    return (
                                                                        <div className="bg-popover text-popover-foreground border-border rounded-lg border p-3 shadow-lg">
                                                                            <p className="mb-2 text-sm font-bold">{label}</p>
                                                                            <div className="space-y-1 text-xs">
                                                                                <div className="flex justify-between gap-4">
                                                                                    <span className="text-muted-foreground">Average:</span>
                                                                                    <span className="text-primary font-mono font-bold">
                                                                                        {data.grade || "N/A"}
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                }
                                                                return null;
                                                            }}
                                                        />
                                                        <Bar dataKey="grade" radius={[4, 4, 4, 4]} barSize={40}>
                                                            {chartData.map((entry, index) => (
                                                                <Cell key={`cell-${index}`} fill={`var(--chart-${(index % 5) + 1})`} />
                                                            ))}
                                                        </Bar>
                                                    </BarChart>
                                                </ResponsiveContainer>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    {/* Summary Cards */}
                                    <div className="space-y-4">
                                        <Card className="bg-primary/5 border-primary/20">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">General Weighted Avg</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-primary text-3xl font-bold">{gwa || "—"}</div>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {gwa ? "Keep up the good work!" : "Not enough data yet"}
                                                </p>
                                            </CardContent>
                                        </Card>

                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Units Taken</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-3xl font-bold">{student_data.total_units}</div>
                                                <Progress value={(student_data.total_units / 25) * 100} className="mt-2 h-2" />
                                                <p className="text-muted-foreground mt-1 text-xs">Total load for this semester</p>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>

                                {/* Detailed Report Cards */}
                                <div>
                                    <h4 className="text-muted-foreground mb-4 text-sm font-semibold tracking-wider uppercase">Detailed Grades</h4>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {student_data.enrolled_classes.map((cls, idx) => (
                                            <Card key={cls.id} className="transition-shadow hover:shadow-md">
                                                <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
                                                    <div className="space-y-1">
                                                        <CardTitle className="text-base">{cls.subject_title}</CardTitle>
                                                        <p className="text-muted-foreground font-mono text-xs">{cls.subject_code}</p>
                                                    </div>
                                                    <div
                                                        className={`font-mono text-xl font-bold ${
                                                            cls.grades.average && cls.grades.average <= 3.0
                                                                ? "text-primary"
                                                                : cls.grades.average
                                                                  ? "text-destructive"
                                                                  : "text-muted-foreground"
                                                        }`}
                                                    >
                                                        {cls.grades.average || "—"}
                                                    </div>
                                                </CardHeader>
                                                <CardContent>
                                                    <div className="grid grid-cols-3 gap-2 text-center">
                                                        <div className="bg-muted/50 rounded-lg p-2">
                                                            <p className="text-muted-foreground text-[10px] font-semibold uppercase">Prelim</p>
                                                            <p className="mt-1 font-mono text-sm font-medium">{cls.grades.prelim || "—"}</p>
                                                        </div>
                                                        <div className="bg-muted/50 rounded-lg p-2">
                                                            <p className="text-muted-foreground text-[10px] font-semibold uppercase">Midterm</p>
                                                            <p className="mt-1 font-mono text-sm font-medium">{cls.grades.midterm || "—"}</p>
                                                        </div>
                                                        <div className="bg-muted/50 rounded-lg p-2">
                                                            <p className="text-muted-foreground text-[10px] font-semibold uppercase">Finals</p>
                                                            <p className="mt-1 font-mono text-sm font-medium">{cls.grades.finals || "—"}</p>
                                                        </div>
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>

                    {/* RIGHT: Sidebar / Command Center (4 cols) */}
                    <div className="space-y-6 lg:col-span-4">
                        {/* Digital ID Card Widget */}
                        <motion.div initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.4 }}>
                            {id_card ? (
                                <DigitalIdCard
                                    cardData={id_card.card_data}
                                    photoUrl={id_card.photo_url}
                                    qrCode={qrCode}
                                    isValid={id_card.is_valid}
                                    isCompact={true}
                                    onRefresh={handleRefreshQr}
                                    onExpand={handleExpandIdCard}
                                    isRefreshing={isRefreshingQr}
                                />
                            ) : (
                                <Card className="bg-card text-card-foreground border-primary/20 relative overflow-hidden border shadow-xl">
                                    {/* Holographic effect */}
                                    <div className="from-primary/5 pointer-events-none absolute inset-0 bg-gradient-to-br to-transparent opacity-50" />
                                    <div className="bg-primary/10 absolute -right-10 -bottom-10 h-40 w-40 rounded-full blur-3xl" />

                                    <CardContent className="relative z-10 p-6">
                                        <div className="mb-6 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="bg-primary h-2 w-2 animate-pulse rounded-full" />
                                                <span className="text-muted-foreground font-mono text-xs">ACTIVE STUDENT</span>
                                            </div>
                                            <GraduationCap className="text-muted-foreground/50" />
                                        </div>

                                        <div className="space-y-4">
                                            <div className="bg-muted/50 border-primary/20 mb-4 h-20 w-20 overflow-hidden rounded-2xl border-2">
                                                {/* Avatar Fallback */}
                                                {user.avatar ? (
                                                    <img src={user.avatar} alt="Profile" className="h-full w-full object-cover" />
                                                ) : (
                                                    <div className="bg-muted text-muted-foreground flex h-full w-full items-center justify-center text-2xl font-bold">
                                                        {student_data.student_name.charAt(0)}
                                                    </div>
                                                )}
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground mb-1 text-xs tracking-widest uppercase">Student Name</p>
                                                <h3 className="text-foreground text-xl font-bold tracking-wide">{student_data.student_name}</h3>
                                            </div>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p className="text-muted-foreground mb-1 text-xs tracking-widest uppercase">ID Number</p>
                                                    <p className="text-foreground font-mono">{student_data.student_id}</p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground mb-1 text-xs tracking-widest uppercase">Program</p>
                                                    <p className="text-foreground truncate" title={student_data.course || ""}>
                                                        {student_data.course?.split(" ")[0] || "N/A"}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="border-border mt-6 flex items-center justify-between border-t pt-4">
                                            <Link
                                                href="/student/profile"
                                                className="text-primary hover:text-primary/80 flex items-center gap-1 text-xs transition-opacity"
                                            >
                                                View Full Profile <ArrowRight className="h-3 w-3" />
                                            </Link>
                                            <Sparkles className="text-primary h-4 w-4" />
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </motion.div>

                        {/* Next Class / Focus Widget */}
                        <Card className="bg-card text-card-foreground border-primary/20 border shadow-lg">
                            <CardHeader className="pb-2">
                                <CardTitle className="text-foreground flex items-center gap-2 text-base">
                                    <Zap className="text-primary h-4 w-4" />
                                    Up Next
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {student_data.enrolled_classes.length > 0 ? (
                                    <div>
                                        <h3 className="text-foreground mb-1 text-xl font-bold">{student_data.enrolled_classes[0].subject_title}</h3>
                                        <p className="text-muted-foreground mb-4 text-sm">{student_data.enrolled_classes[0].schedule}</p>
                                        <Button size="sm" variant="default" className="w-full font-semibold shadow-none">
                                            View Details
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="py-4 text-center">
                                        <p className="text-muted-foreground text-sm">No classes scheduled.</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Announcements Feed */}
                        <Card className="h-fit">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="flex items-center gap-2 text-base font-bold">
                                    <Bell className="h-4 w-4 text-slate-500" />
                                    Notice Board
                                </CardTitle>
                                <Button variant="ghost" size="sm" asChild className="h-8 text-xs">
                                    <Link href="/student/announcements">View All</Link>
                                </Button>
                            </CardHeader>
                            <CardContent className="p-0">
                                <ScrollArea className="h-[350px]">
                                    <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {student_data.announcements.length > 0 ? (
                                            student_data.announcements.map((ann) => (
                                                <div key={ann.id} className="p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                                    <div className="flex items-start gap-3">
                                                        <div
                                                            className={`mt-1 h-2 min-w-[8px] rounded-full ${
                                                                ann.type === "important"
                                                                    ? "bg-red-500"
                                                                    : ann.type === "warning"
                                                                      ? "bg-amber-500"
                                                                      : "bg-blue-500"
                                                            }`}
                                                        />
                                                        <div>
                                                            <div className="mb-1 flex items-center justify-between">
                                                                <h4 className="text-sm font-semibold">{ann.title}</h4>
                                                            </div>
                                                            <p className="text-muted-foreground mb-2 line-clamp-2 text-xs">{ann.content}</p>
                                                            <span className="text-[10px] font-medium text-slate-400 uppercase">{ann.date}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))
                                        ) : (
                                            <div className="p-8 text-center">
                                                <p className="text-muted-foreground text-sm">No new announcements.</p>
                                            </div>
                                        )}
                                    </div>
                                </ScrollArea>
                            </CardContent>
                        </Card>

                        {/* Quick Links */}
                        <div className="grid grid-cols-2 gap-3">
                            <Button
                                variant="outline"
                                className="flex h-auto flex-col items-center justify-center gap-1 py-3 transition-all hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-slate-800"
                                asChild
                            >
                                <Link href="/student/profile">
                                    <User className="mb-1 h-5 w-5" />
                                    <span className="text-xs">Profile</span>
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="flex h-auto flex-col items-center justify-center gap-1 py-3 transition-all hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-slate-800"
                            >
                                <Link href="#">
                                    <Calendar className="mb-1 h-5 w-5" />
                                    <span className="text-xs">Calendar</span>
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="flex h-auto flex-col items-center justify-center gap-1 py-3 transition-all hover:border-purple-200 hover:bg-purple-50 hover:text-purple-600 dark:hover:bg-slate-800"
                            >
                                <Link href="#">
                                    <CreditCard className="mb-1 h-5 w-5" />
                                    <span className="text-xs">Payments</span>
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="flex h-auto flex-col items-center justify-center gap-1 py-3 transition-all hover:border-amber-200 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-slate-800"
                            >
                                <Link href="#">
                                    <AlertCircle className="mb-1 h-5 w-5" />
                                    <span className="text-xs">Help</span>
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </StudentLayout>
    );
}

// Helper icon component to avoid collision with Lucide 'User'
function User(props: any) {
    return (
        <svg
            {...props}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </svg>
    );
}
