import { AnnouncementsWidget } from "@/components/dashboard/announcements-widget";
import { CalendarWidget } from "@/components/dashboard/calendar-widget";
import { StatsGrid, Stat } from "@/components/dashboard/stats-grid";
import { TodaySchedule } from "@/components/dashboard/today-schedule";
import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import FacultyLayout from "@/components/faculty/faculty-layout";
import { OnboardingChecklistWidget } from "@/components/onboarding-checklist";
import { OnboardingProvider, type OnboardingChecklistItem } from "@/components/onboarding-context";
import { OnboardingTour, type TourStep } from "@/components/onboarding-tour";
import type { OnboardingFeatureData } from "@/components/onboarding-experience";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartAreaInteractive } from "@/components/chart-area-interactive";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import {
    ArrowRight,
    Bell,
    BookOpen,
    Calendar,
    CheckCircle2,
    Clock,
    Eye,
    EyeOff,
    MapPin,
    Sparkles,
    TrendingUp,
    Trophy,
    Users,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface AttendanceChartData {
    date: string;
    present: number;
    absent: number;
    late: number;
    excused: number;
}

interface ClassOption {
    id: number;
    label: string;
}

interface CalendarEvent {
    id: number;
    title: string;
    description: string | null;
    location: string | null;
    start_datetime: string;
    end_datetime: string | null;
    is_all_day: boolean;
    type: string;
    category: string;
    status: string;
    color: string;
}

type DashboardAnnouncement = {
    id?: number | string;
    title: string;
    content: string;
    date: string;
    type: "info" | "warning" | "important" | "update";
};

type DashboardTodayScheduleEntry = {
    id: number | string;
    class_id?: number | string;
    start_time: string;
    end_time: string;
    subject_code: string;
    subject_title: string;
    section: string;
    room: string;
    course_codes?: string;
    classification?: string;
};

interface DashboardClass {
    id: number | string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string;
    room: string;
    students_count: number;
    classification?: string;
}

interface DashboardProps {
    user: User;
    is_new_user: boolean;
    current_semester: string;
    current_school_year: string;
    faculty_data: {
        stats: Stat[];
        upcoming_classes: DashboardClass[];
        total_active_classes?: number;
        total_students?: number;
        pending_action_items?: number;
        announcements: DashboardAnnouncement[];
        today_schedule: {
            day: string;
            entries: DashboardTodayScheduleEntry[];
        };
        attendance_chart?: {
            chart_data: AttendanceChartData[];
            classes: ClassOption[];
        } | null;
        calendar_events?: CalendarEvent[];
    };
    id_card: {
        card_data: IdCardData;
        photo_url: string | null;
        qr_code: string;
        is_valid: boolean;
    } | null;
}

const classAccents = ["bg-blue-500", "bg-emerald-500", "bg-amber-500", "bg-rose-500", "bg-violet-500", "bg-cyan-500"];
const dashboardCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-md";
const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

function getSemesterLabel(semester: string): string {
    if (semester === "1") {
        return "1st Semester";
    }

    if (semester === "2") {
        return "2nd Semester";
    }

    return "Summer";
}

function getShortName(name: string): string {
    if (name.includes(",")) {
        return name.split(",")[1]?.trim() || name;
    }

    const prefixes = ["Dr.", "Mr.", "Mrs.", "Ms.", "Prof.", "Rev.", "Fr.", "Sr.", "St."];
    const parts = name.split(" ");
    
    if (parts.length > 1 && prefixes.includes(parts[0])) {
        return parts[1];
    }
    
    return parts[0] || name;
}

function getGreeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return "Good morning";
    }

    if (hour < 17) {
        return "Good afternoon";
    }

    return "Good evening";
}

function getFacultyGreetingCopy(greeting: string): { headline: string; subline: string; summary: string } {
    if (greeting === "Good morning") {
        return {
            headline: "Ready for today's classes",
            subline: "Morning teaching brief",
            summary: "Your schedule, class tools, and daily actions are ready.",
        };
    }

    if (greeting === "Good afternoon") {
        return {
            headline: "Keep the class flow moving",
            subline: "Afternoon faculty snapshot",
            summary: "Jump back into attendance, classes, and follow-ups.",
        };
    }

    return {
        headline: "Time to wrap with clarity",
        subline: "Evening faculty check-in",
        summary: "Review today, close out tasks, and get ready for the next class day.",
    };
}

// Faculty onboarding checklist items
const facultyChecklist: OnboardingChecklistItem[] = [
    {
        id: "profile-complete",
        label: "Complete your profile",
        description: "Add your photo and verify your faculty information.",
        actionRoute: "/faculty/profile",
        actionLabel: "Go to Profile",
        isCompleted: false,
    },
    {
        id: "first-class",
        label: "Open your first class",
        description: "Access your class roster and explore available tools.",
        actionRoute: "/faculty/classes",
        actionLabel: "View Classes",
        isCompleted: false,
    },
    {
        id: "take-attendance",
        label: "Take attendance",
        description: "Record student attendance for one of your classes.",
        actionRoute: "/faculty/classes",
        actionLabel: "Start",
        isCompleted: false,
    },
    {
        id: "review-insights",
        label: "Review class insights",
        description: "Check performance trends and exportable reports.",
        actionRoute: "/faculty/classes",
        actionLabel: "Explore",
        isCompleted: false,
    },
];

// Interactive walkthrough steps
const facultyTourSteps: TourStep[] = [
    {
        id: "faculty-welcome",
        target: '[data-tour="welcome-header"]',
        title: "Your Command Center",
        description: "Everything you need for today is in one focused view — classes, alerts, and quick actions.",
        placement: "bottom",
    },
    {
        id: "faculty-stats",
        target: '[data-tour="stats-grid"]',
        title: "Quick Stats",
        description: "See your teaching load, student count, and key metrics at a glance.",
        placement: "bottom",
    },
    {
        id: "faculty-schedule",
        target: '[data-tour="today-schedule"]',
        title: "Today's Schedule",
        description: "Never miss a class. Your daily schedule is always visible right here.",
        placement: "right",
    },
    {
        id: "faculty-classes",
        target: '[data-tour="my-classes"]',
        title: "Your Classes",
        description: "This is where the magic happens. Open any class to manage students, grades, and attendance — all in one place.",
        placement: "top",
        ahaMoment: "Open a class and take attendance in under 60 seconds. That is your fastest path to value!",
    },
    {
        id: "faculty-id-card",
        target: '[data-tour="id-card"]',
        title: "Digital ID Card",
        description: "Your faculty ID is always accessible. Tap to expand or refresh your QR code instantly.",
        placement: "left",
    },
];

function ClassListCard({ classItem, index }: { classItem: DashboardClass; index: number }) {
    const accent = classAccents[index % classAccents.length];
    const isShs = (classItem.classification ?? "").toLowerCase() === "shs";

    return (
        <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05, duration: 0.35 }}
        >
            <Card className={`${dashboardCardClass} group overflow-hidden hover:-translate-y-0.5`}>
                <CardContent className="grid gap-4 p-4 md:grid-cols-[1fr_230px] md:items-center">
                    <div className="flex gap-3">
                        <div className={`${accent} mt-1 h-12 w-1 shrink-0 rounded-full transition-all duration-200 group-hover:h-14`} />
                        <div className="min-w-0 space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline" className="font-mono text-[11px]">
                                    {classItem.subject_code}
                                </Badge>
                                <span className="text-muted-foreground text-xs">Section {classItem.section}</span>
                                {isShs && (
                                    <Badge variant="secondary" className="h-5 px-1.5 text-[10px] font-medium">
                                        SHS
                                    </Badge>
                                )}
                            </div>
                            <h3 className="text-foreground text-base leading-tight font-semibold">{classItem.subject_title}</h3>
                            <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                <span className="flex items-center gap-1.5">
                                    <Users className="h-3.5 w-3.5" />
                                    {classItem.students_count} students
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <MapPin className="h-3.5 w-3.5" />
                                    {classItem.room}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 sm:pl-4">
                        <Button asChild size="sm" variant="outline" className="rounded-lg">
                            <Link href={`/faculty/classes/${classItem.id}?tab=attendance`} prefetch>
                                <Calendar className="mr-1.5 h-3.5 w-3.5" />
                                Attendance
                            </Link>
                        </Button>
                        <Button asChild size="sm" className="rounded-lg">
                            <Link href={`/faculty/classes/${classItem.id}`}>
                                Open
                                <ArrowRight className="ml-1 h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </motion.div>
    );
}

function MobileQuickActions() {
    const actions = [
        { label: "Grades", href: "/faculty/grades", icon: TrendingUp, color: "bg-blue-500" },
        { label: "Classes", href: "/faculty/classes", icon: BookOpen, color: "bg-emerald-500" },
        { label: "Schedule", href: "/faculty/schedule", icon: Calendar, color: "bg-amber-500" },
        { label: "Attendance", href: "/faculty/attendance", icon: CheckCircle2, color: "bg-violet-500" },
    ];

    return (
        <section className="px-0.5 md:hidden">
            <div className="grid grid-cols-4 gap-2.5">
                {actions.map((action) => {
                    const Icon = action.icon;

                    return (
                        <Link key={action.href} href={action.href} className="group flex min-w-0 flex-col items-center gap-1.5">
                            <div
                                className={cn(
                                    "flex h-11 w-11 items-center justify-center rounded-xl shadow-sm transition-all duration-200 group-active:scale-90",
                                    "bg-card border-border/40 border group-hover:border-primary/40 group-hover:bg-primary/5",
                                )}
                            >
                                <Icon className="text-primary h-5 w-5" strokeWidth={1.6} />
                            </div>
                            <span className="text-foreground/70 group-hover:text-foreground max-w-full truncate text-[10px] font-bold tracking-tight transition-colors">
                                {action.label}
                            </span>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

function MobileMetricCard({
    icon: Icon,
    label,
    value,
    detail,
    tone,
    privateValue = false,
}: {
    icon: typeof Trophy;
    label: string;
    value: string | number;
    detail: string;
    tone: string;
    privateValue?: boolean;
}) {
    const [revealed, setRevealed] = useState(!privateValue);
    const iconTone = tone.split(" ").find((className) => className.startsWith("text-")) ?? "text-primary";
    const bgTone = tone.split(" ").find((className) => className.startsWith("bg-")) ?? "bg-primary/10";

    return (
        <motion.div
            whileHover={{ y: -2 }}
            className="group"
        >
            <Card className="border-border/30 bg-card/80 relative overflow-hidden rounded-xl shadow-sm transition-all duration-200 hover:border-border/60 hover:shadow-md">
                {/* Gradient background accent */}
                <div className={cn("absolute -right-4 -top-4 h-16 w-16 rounded-full opacity-20 blur-xl transition-all duration-300 group-hover:opacity-30", bgTone)} />
                
                <CardContent className="relative p-3">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-1.5">
                                <p className="text-foreground/60 truncate text-[9px] font-bold tracking-wider uppercase">{label}</p>
                                {privateValue && (
                                    <button
                                        type="button"
                                        onClick={() => setRevealed((current) => !current)}
                                        className="text-muted-foreground/60 hover:text-foreground transition-colors"
                                        aria-label={revealed ? "Hide balance" : "Show balance"}
                                    >
                                        {revealed ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                                    </button>
                                )}
                            </div>
                            <motion.p
                                key={revealed ? "revealed" : "hidden"}
                                initial={{ opacity: 0, scale: 0.95 }}
                                animate={{ opacity: 1, scale: 1 }}
                                transition={{ duration: 0.2 }}
                                className="text-foreground mt-1 truncate text-base leading-none font-bold tracking-tight"
                            >
                                {revealed ? value : "••••••"}
                            </motion.p>
                        </div>
                        <motion.div
                            whileHover={{ scale: 1.1, rotate: 5 }}
                            transition={{ type: "spring", stiffness: 400, damping: 15 }}
                            className={cn("flex h-8 w-8 shrink-0 items-center justify-center rounded-lg shadow-sm", bgTone)}
                        >
                            <Icon className={cn("h-4 w-4", iconTone)} strokeWidth={2.2} />
                        </motion.div>
                    </div>
                    <p className="text-foreground/50 mt-1.5 line-clamp-1 text-[10px] font-medium">{detail}</p>
                </CardContent>
            </Card>
        </motion.div>
    );
}

function TeacherFocusGrid({
    facultyData,
    totalStudents,
    urgentAnnouncements,
}: {
    facultyData: DashboardProps["faculty_data"];
    totalStudents: number;
    urgentAnnouncements: number;
}) {
    const nextClass = facultyData.today_schedule.entries[0];

    return (
        <section className="grid gap-4 lg:grid-cols-3">
            <Card className={`${dashboardCardClass} group overflow-hidden`}>
                <CardContent className="flex h-full flex-col gap-4 p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Next Class</p>
                            {nextClass ? (
                                <div className="mt-2 space-y-1">
                                    <h2 className="text-foreground truncate text-lg font-semibold">{nextClass.subject_title}</h2>
                                    <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                        <span className="flex items-center gap-1.5">
                                            <Clock className="size-3.5" />
                                            {nextClass.start_time} - {nextClass.end_time}
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="size-3.5" />
                                            {nextClass.room}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-muted-foreground mt-2 text-sm">No classes scheduled today.</p>
                            )}
                        </div>
                        <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                            <Calendar className="size-5" />
                        </div>
                    </div>
                    <div className="mt-auto flex items-center gap-2">
                        {nextClass?.class_id ? (
                            <Button asChild size="sm" className="rounded-lg">
                                <Link href={`/faculty/classes/${nextClass.class_id}`}>Open Class</Link>
                            </Button>
                        ) : null}
                        <Button asChild size="sm" variant="outline" className="rounded-lg">
                            <Link href="/faculty/schedule">View Schedule</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card className={`${dashboardCardClass} group overflow-hidden`}>
                <CardContent className="flex h-full flex-col gap-4 p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Needs Attention</p>
                            <h2 className="text-foreground mt-2 text-lg font-semibold tabular-nums">
                                {(facultyData.pending_action_items ?? urgentAnnouncements) > 0
                                    ? `${facultyData.pending_action_items ?? urgentAnnouncements} open item${(facultyData.pending_action_items ?? urgentAnnouncements) === 1 ? "" : "s"}`
                                    : "All caught up"}
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">Review pending classwork, tasks, and notices in one place.</p>
                        </div>
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                            <Bell className="size-5" />
                        </div>
                    </div>
                    <Button asChild size="sm" variant="outline" className="mt-auto w-fit rounded-lg">
                        <Link href="/faculty/action-center">Open Action Center</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card className={`${dashboardCardClass} group overflow-hidden`}>
                <CardContent className="flex h-full flex-col gap-4 p-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">My Classes</p>
                            <h2 className="text-foreground mt-2 text-lg font-semibold tabular-nums">
                                {facultyData.total_active_classes ?? facultyData.upcoming_classes.length} classes
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm tabular-nums">
                                {facultyData.total_students ?? totalStudents} students this semester.
                            </p>
                        </div>
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500">
                            <BookOpen className="size-5" />
                        </div>
                    </div>
                    <Button asChild size="sm" variant="outline" className="mt-auto w-fit rounded-lg">
                        <Link href="/faculty/classes">Open Classes</Link>
                    </Button>
                </CardContent>
            </Card>
        </section>
    );
}

function MobileFacultyDashboard({ greeting, faculty_data, currentSemester, currentSchoolYear, user, id_card, qrCode, refreshCode, isRefreshing }: { greeting: string; faculty_data: DashboardProps['faculty_data']; currentSemester: string; currentSchoolYear: string; user: User; id_card: DashboardProps['id_card']; qrCode: string; refreshCode: () => void; isRefreshing: boolean }) {
    const totalStudents = faculty_data.total_students ?? faculty_data.upcoming_classes.reduce((sum, c) => sum + c.students_count, 0);
    const hasClasses = faculty_data.upcoming_classes.length > 0;
    const nextClass = faculty_data.today_schedule.entries[0];
    const greetingCopy = getFacultyGreetingCopy(greeting);

    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: "easeOut" }}
            className="mx-auto flex w-full max-w-md flex-col md:hidden"
        >
            {/* 1. Greeting Card */}
            <div className="bg-primary/10 relative h-auto w-full overflow-hidden px-4 pt-5 pb-7">
                <div className="bg-primary/20 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                <div className="bg-primary/10 absolute -bottom-12 -left-12 h-40 w-40 rounded-full blur-2xl" />

                <div className="relative flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1 pr-2">
                        <p className="text-foreground/60 truncate text-[10px] font-bold tracking-wider uppercase">
                            {greetingCopy.subline}
                        </p>
                        <p className="sr-only">
                            {getSemesterLabel(currentSemester)} • {currentSchoolYear}
                        </p>
                        <h1 className="text-foreground mt-0.5 text-xl leading-tight font-bold tracking-tight">
                            {greetingCopy.headline},{" "}
                            <span className="from-primary to-primary/60 block truncate bg-gradient-to-r bg-clip-text text-transparent">
                                {getShortName(user.name)}
                            </span>
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                className="border-border/60 bg-background/65 text-foreground/70 rounded-full px-2 py-0.5 text-[9px] font-bold whitespace-nowrap"
                            >
                                {getSemesterLabel(currentSemester)}
                            </Badge>
                            <span className="text-foreground/45 text-[10px] font-semibold">{currentSchoolYear}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="relative z-20 -mt-4 space-y-3 px-3.5 pb-32">
                {/* 2. KOA Official ID */}
                {id_card && (
                    <section>
                        <DigitalIdCard
                            cardData={id_card.card_data}
                            photoUrl={id_card.photo_url}
                            qrCode={qrCode}
                            isValid={id_card.is_valid}
                            isCompact
                            onRefresh={() => {
                                refreshCode();
                            }}
                            isRefreshing={isRefreshing}
                        />
                    </section>
                )}

                {/* 3. Quick Action Buttons */}
                <MobileQuickActions />

                {/* 4. Next Class */}
                <section>
                    <Card className={`${dashboardCardClass} group relative overflow-hidden`}>
                        <CardContent className="relative flex items-center gap-3 p-3.5 pr-12">
                            <Calendar className="text-primary pointer-events-none absolute top-3.5 right-3.5 h-8 w-8 opacity-20 transition-all duration-200 group-hover:scale-105 group-hover:opacity-30" />
                            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                <Clock className="size-5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Next Class</p>
                                {nextClass ? (
                                    <div className="mt-1 min-w-0">
                                        <h2 className="text-foreground truncate text-sm leading-tight font-semibold">{nextClass.subject_title}</h2>
                                        <div className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px]">
                                            <span>{nextClass.start_time} - {nextClass.end_time}</span>
                                            <span>•</span>
                                            <span className="truncate">{nextClass.room}</span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground mt-1 text-sm">No classes scheduled today.</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {/* 5. Stat Cards */}
                <section className="grid grid-cols-2 gap-2.5">
                    <MobileMetricCard
                        icon={BookOpen}
                        label="Classes"
                        value={faculty_data.upcoming_classes.length}
                        detail="Teaching this semester"
                        tone="bg-blue-500/10 text-blue-500"
                    />
                    <MobileMetricCard
                        icon={Users}
                        label="Students"
                        value={totalStudents}
                        detail="Total enrolled"
                        tone="bg-emerald-500/10 text-emerald-500"
                    />
                    <MobileMetricCard
                        icon={Calendar}
                        label="Today"
                        value={faculty_data.today_schedule.entries.length}
                        detail="Classes scheduled"
                        tone="bg-amber-500/10 text-amber-500"
                    />
                    <MobileMetricCard
                        icon={Bell}
                        label="Announcements"
                        value={faculty_data.announcements.length}
                        detail="Total notices"
                        tone="bg-violet-500/10 text-violet-500"
                    />
                </section>

                {/* 6. Today's Schedule */}
                <section>
                    <div className="flex items-center justify-between mb-2">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Today's Schedule</p>
                    </div>
                    {faculty_data.today_schedule.entries.length > 0 ? (
                        <div className="space-y-2">
                            {faculty_data.today_schedule.entries.map((entry, index) => (
                                <Card key={index} className={dashboardCardClass}>
                                    <CardContent className="flex items-center gap-3 p-3">
                                        <div className={`${classAccents[index % classAccents.length]} h-10 w-1.5 rounded-full shrink-0`} />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-foreground truncate text-sm font-semibold">{entry.subject_title}</p>
                                            <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs">
                                                <span>{entry.start_time} - {entry.end_time}</span>
                                                <span>•</span>
                                                <span>{entry.room}</span>
                                            </div>
                                        </div>
                                        <Badge variant="outline" className="shrink-0 text-[10px]">
                                            {entry.subject_code}
                                        </Badge>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <Card className={dashboardPanelClass}>
                            <CardContent className="text-center p-6">
                                <p className="text-muted-foreground text-sm">No classes scheduled today</p>
                            </CardContent>
                        </Card>
                    )}
                </section>

                {/* 7. Current Semester Classes */}
                <section>
                    <div className="flex items-center justify-between mb-2">
                        <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Current Semester Classes</p>
                    </div>
                    {hasClasses ? (
                        <div className="space-y-2">
                            {faculty_data.upcoming_classes.slice(0, 3).map((classItem, index) => (
                                <Card key={classItem.id} className={dashboardCardClass}>
                                    <CardContent className="flex items-center gap-3 p-3">
                                        <div className={`${classAccents[index % classAccents.length]} h-10 w-1.5 rounded-full shrink-0`} />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-foreground truncate text-sm font-semibold">{classItem.subject_title}</p>
                                            <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs">
                                                <span>{classItem.section}</span>
                                                <span>•</span>
                                                <span>{classItem.students_count} students</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <Card className={dashboardPanelClass}>
                            <CardContent className="text-center p-6">
                                <p className="text-muted-foreground text-sm">No classes assigned this semester</p>
                            </CardContent>
                        </Card>
                    )}
                </section>
            </div>
        </motion.div>
    );
}

function DashboardContent({ user, is_new_user, faculty_data, id_card, current_semester, current_school_year }: DashboardProps) {
    const { props } = usePage<{
        onboarding?: {
            forceOnLogin?: boolean;
            features?: OnboardingFeatureData[];
        };
    }>();
    const shouldForceOnboarding = (props.onboarding?.forceOnLogin ?? false) && is_new_user;
    const onboardingFeatures = props.onboarding?.features ?? [];
    const hasOnboardingFeatures = onboardingFeatures.length > 0;
    const onboardingEnabled = hasOnboardingFeatures || shouldForceOnboarding;

    const [qrCode, setQrCode] = useState(id_card?.qr_code ?? "");
    const [isRefreshingQr, setIsRefreshingQr] = useState(false);

    const handleRefreshQr = async () => {
        setIsRefreshingQr(true);
        try {
            const response = await fetch("/faculty/id-card/refresh", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
                },
            });
            if (!response.ok) {
                throw new Error(`QR refresh failed with status ${response.status}`);
            }

            const data = await response.json();
            setQrCode(data.qr_code);
        } catch (error) {
            console.error("Failed to refresh QR code:", error);
            toast.error("Could not refresh your QR code. Please try again.");
        } finally {
            setIsRefreshingQr(false);
        }
    };

    const handleExpandIdCard = () => {
        router.visit("/faculty/id-card/view");
    };

    const mappedAnnouncements = faculty_data.announcements.map((a) => ({
        title: a.title,
        content: a.content,
        date: a.date,
        type: a.type === "update" ? "info" : a.type,
    }));

    const hasClasses = faculty_data.upcoming_classes.length > 0;
    const greeting = getGreeting();
    const greetingCopy = getFacultyGreetingCopy(greeting);
    const totalStudents = faculty_data.upcoming_classes.reduce((sum, c) => sum + c.students_count, 0);
    const urgentAnnouncements = faculty_data.announcements.filter(a => a.type === "important" || a.type === "warning").length;

    return (
        <>
            <Head title="Faculty Dashboard" />
            {onboardingEnabled && <OnboardingTour steps={facultyTourSteps} />}

            {/* Mobile Dashboard */}
            <MobileFacultyDashboard
                greeting={greeting}
                faculty_data={faculty_data}
                currentSemester={current_semester}
                currentSchoolYear={current_school_year}
                user={user}
                id_card={id_card}
                qrCode={qrCode}
                refreshCode={handleRefreshQr}
                isRefreshing={isRefreshingQr}
            />

            {/* Desktop Dashboard */}
            <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: "easeOut" }}
                className="mx-auto hidden w-full max-w-7xl flex-col gap-4 p-4 pb-16 md:flex md:gap-6 md:p-6"
            >
                {/* Header */}
                <section data-tour="welcome-header">
                    <Card className={`${dashboardPanelClass} relative overflow-hidden`}>
                        {/* Decorative Glass Elements */}
                        <div className="bg-primary/5 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                        <div className="bg-primary/10 absolute -bottom-12 -left-12 h-48 w-48 rounded-full blur-2xl" />

                        <CardContent className="relative z-10 p-5 md:p-6">
                            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div className="max-w-2xl space-y-3">
                                    <Badge
                                        variant="outline"
                                        className="border-border/60 bg-background/60 w-fit rounded-full px-3 py-1 text-xs"
                                    >
                                        <Sparkles className="text-primary mr-1.5 h-3 w-3" />
                                        {getSemesterLabel(current_semester)} • {current_school_year}
                                    </Badge>
                                    <div>
                                        <h1 className="text-foreground text-3xl leading-tight font-bold tracking-tight">
                                            {greetingCopy.headline},{" "}
                                            <span className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text text-transparent">
                                                {getShortName(user.name)}
                                            </span>
                                        </h1>
                                        <p className="text-muted-foreground mt-1 max-w-xl text-sm leading-relaxed">
                                            {greetingCopy.summary}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button asChild size="sm" className="rounded-lg">
                                        <Link href="/faculty/action-center">Review Today</Link>
                                    </Button>
                                    <Button asChild size="sm" variant="outline" className="rounded-lg">
                                        <Link href="/faculty/classes">My Classes</Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {/* Mobile Quick Actions (already hidden on desktop) */}
                <MobileQuickActions />

                {/* Onboarding Checklist Widget */}
                {onboardingEnabled && (
                    <OnboardingChecklistWidget />
                )}

                <TeacherFocusGrid facultyData={faculty_data} totalStudents={totalStudents} urgentAnnouncements={urgentAnnouncements} />

                {/* Stats */}
                <div data-tour="stats-grid">
                    <StatsGrid stats={faculty_data.stats} />
                </div>

                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Main Column */}
                    <div className="flex flex-col gap-6 lg:col-span-8">
                        {/* Today's Schedule */}
                        <div data-tour="today-schedule">
                            <TodaySchedule schedule={faculty_data.today_schedule} />
                        </div>

                        {/* My Classes */}
                        <div data-tour="my-classes" className="space-y-4">
                            <div className="flex items-end justify-between gap-3">
                                <div>
                                    <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Your Classes</p>
                                    <h3 className="mt-1 text-lg font-semibold">Current Semester</h3>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="secondary" className="rounded-full">
                                        {faculty_data.upcoming_classes.length} classes
                                    </Badge>
                                    <Button asChild size="sm" variant="outline">
                                        <Link href="/faculty/classes">View All</Link>
                                    </Button>
                                </div>
                            </div>

                            {hasClasses ? (
                                <div className="grid gap-3">
                                    {faculty_data.upcoming_classes.map((classItem, index) => (
                                        <ClassListCard key={classItem.id} classItem={classItem} index={index} />
                                    ))}
                                </div>
                            ) : (
                                <Card className={`${dashboardPanelClass} border-dashed`}>
                                    <CardContent className="flex flex-col items-center justify-center p-10 text-center">
                                        <BookOpen className="text-muted-foreground h-9 w-9" />
                                        <h3 className="mt-3 font-semibold">No classes assigned</h3>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Once assigned, your classes will appear here.
                                        </p>
                                        <Button asChild variant="outline" size="sm" className="mt-3">
                                            <Link href="/faculty/classes">Manage Classes</Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        {/* Attendance Chart */}
                        <ChartAreaInteractive
                            chartData={faculty_data.attendance_chart?.chart_data ?? []}
                            classes={faculty_data.attendance_chart?.classes ?? []}
                        />
                    </div>

                    {/* Sidebar Column */}
                    <div className="flex flex-col gap-6 lg:col-span-4">
                        {id_card && (
                            <motion.div
                                data-tour="id-card"
                                initial={{ opacity: 0, x: 20 }}
                                animate={{ opacity: 1, x: 0 }}
                                transition={{ delay: 0.2 }}
                            >
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
                            </motion.div>
                        )}

                        <CalendarWidget events={faculty_data.calendar_events ?? []} />
                        <AnnouncementsWidget announcements={mappedAnnouncements} />
                    </div>
                </div>
            </motion.div>
        </>
    );
}

export default function FacultyDashboard(props: DashboardProps) {
    const { user, is_new_user } = props;
    const { props: pageProps } = usePage<{
        onboarding?: {
            forceOnLogin?: boolean;
            features?: OnboardingFeatureData[];
        };
    }>();

    const shouldForceOnboarding = (pageProps.onboarding?.forceOnLogin ?? false) && is_new_user;
    const onboardingFeatures = pageProps.onboarding?.features ?? [];
    const hasOnboardingFeatures = onboardingFeatures.length > 0;
    const onboardingEnabled = hasOnboardingFeatures || shouldForceOnboarding;

    return (
        <FacultyLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            {onboardingEnabled ? (
                <OnboardingProvider
                    variant="faculty"
                    userId={user.id}
                    checklist={facultyChecklist}
                    totalSteps={4}
                    enabled={onboardingEnabled}
                >
                    <DashboardContent {...props} />
                </OnboardingProvider>
            ) : (
                <DashboardContent {...props} />
            )}
        </FacultyLayout>
    );
}
