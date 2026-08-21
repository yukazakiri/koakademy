import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import { OnboardingChecklistWidget } from "@/components/onboarding-checklist";
import { OnboardingProvider, type OnboardingChecklistItem } from "@/components/onboarding-context";
import { OnboardingTour, type TourStep } from "@/components/onboarding-tour";
import { SemesterSelectorProps } from "@/components/semester-selector";
import { GradesPanel } from "@/components/student/grades-panel";
import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useGradingConfig } from "@/hooks/use-grading-config";
import { computeGwa, detectGradeScale, formatGwa, isPassingGrade } from "@/lib/gwa";
import { cn } from "@/lib/utils";
import { type User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import {
    ArrowRight,
    Bell,
    BookOpen,
    Calendar,
    CheckCircle2,
    CircleAlert,
    Clock,
    CreditCard,
    Eye,
    EyeOff,
    GraduationCap,
    HelpCircle,
    LayoutGrid,
    MapPin,
    Sparkles,
    TrendingUp,
    Trophy,
    UserRound,
    type LucideIcon,
} from "lucide-react";
import { useMemo, useState } from "react";

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
    units?: number;
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
    is_new_user: boolean;
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
    profile_completion_prompt: {
        total: number;
        completed: number;
        percentage: number;
        missing: Array<{ key: string; label: string; section: string; example?: string }>;
        link: string;
    } | null;
}

function ProfileCompletionPrompt({ prompt }: { prompt: NonNullable<StudentDashboardProps["profile_completion_prompt"]> }) {
    const missingLabels = prompt.missing.map((item) => item.label).join(", ");

    return (
        <section className="mx-auto w-full max-w-7xl px-4 pt-4 md:px-6 md:pt-6" aria-labelledby="profile-completion-title">
            <Card className="via-card to-card border-amber-500/25 bg-gradient-to-r from-amber-500/10 shadow-sm">
                <CardContent className="flex flex-col gap-4 p-4 sm:p-5 md:flex-row md:items-center md:justify-between">
                    <div className="flex min-w-0 gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300">
                            <CircleAlert className="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 id="profile-completion-title" className="text-sm font-semibold">
                                    Complete your student profile
                                </h2>
                                <Badge variant="secondary" className="rounded-full">
                                    {prompt.completed}/{prompt.total} complete
                                </Badge>
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                Your school needs a few details for services and reporting: {missingLabels}.
                            </p>
                        </div>
                    </div>
                    <Button asChild className="shrink-0">
                        <Link href={prompt.link}>
                            Complete profile
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </section>
    );
}

interface ScheduleSegment {
    days: string[];
    start: number;
    end: number;
    timeString: string;
}

const dayOrder = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

const classAccents = ["bg-blue-500", "bg-emerald-500", "bg-amber-500", "bg-rose-500", "bg-violet-500", "bg-cyan-500"];
const dashboardCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-md";
const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

const studentChecklist: OnboardingChecklistItem[] = [
    {
        id: "profile-complete",
        label: "Complete your profile",
        description: "Add your photo and verify your student information.",
        actionRoute: "/student/profile",
        actionLabel: "Go to Profile",
        isCompleted: false,
    },
    {
        id: "view-schedule",
        label: "View your class schedule",
        description: "Check your weekly schedule and room assignments.",
        actionRoute: "/student/schedule",
        actionLabel: "View Schedule",
        isCompleted: false,
    },
    {
        id: "check-grades",
        label: "Check your grades",
        description: "Review your academic performance and progress.",
        actionRoute: "/student/classes",
        actionLabel: "View Classes",
        isCompleted: false,
    },
    {
        id: "explore-id",
        label: "Get your digital ID",
        description: "Access your student ID card and QR code.",
        actionRoute: "/student/id-card/view",
        actionLabel: "View ID",
        isCompleted: false,
    },
];

const studentTourSteps: TourStep[] = [
    {
        id: "student-welcome",
        target: '[data-tour="welcome-header"]',
        title: "Your Hub",
        description: "Everything in one focused view — your greeting, program, and current term at a glance.",
        placement: "bottom",
    },
    {
        id: "student-stats",
        target: '[data-tour="stats-grid"]',
        title: "Quick Stats",
        description: "Your GWA, enrolled subjects, clearance, and balance — the numbers that matter, all in one row.",
        placement: "bottom",
    },
    {
        id: "student-up-next",
        target: '[data-tour="up-next"]',
        title: "Up Next",
        description: "Your next class with day, time, and room. No more guessing where you should be.",
        placement: "right",
    },
    {
        id: "student-subjects",
        target: '[data-tour="my-subjects"]',
        title: "Your Subjects",
        description: "Every enrolled class with schedule, instructor, and posted grade — open one to see the full breakdown.",
        placement: "top",
        ahaMoment: "Open any subject to see prelim, midterm, and finals side by side. That is your fastest read on where you stand.",
    },
    {
        id: "student-id-card",
        target: '[data-tour="id-card"]',
        title: "Digital ID Card",
        description: "Your student ID with a refreshable QR. Tap to expand or refresh the code at any time.",
        placement: "left",
    },
];

function getSemesterLabel(semester: number): string {
    if (semester === 1) {
        return "1st Semester";
    }

    if (semester === 2) {
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

function formatMoney(value: number, currency: string): string {
    const currencyPrefix = currency === "USD" ? "$" : "PHP ";

    return `${currencyPrefix}${value.toLocaleString(undefined, {
        minimumFractionDigits: value % 1 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    })}`;
}

function timeToFloat(time: string): number {
    const [rawTime, period] = time.trim().split(/\s+/);
    const [rawHours, rawMinutes] = rawTime.split(":").map(Number);
    let hours = rawHours;

    if (period?.toUpperCase() === "PM" && hours !== 12) {
        hours += 12;
    }

    if (period?.toUpperCase() === "AM" && hours === 12) {
        hours = 0;
    }

    return hours + rawMinutes / 60;
}

function parseSchedule(schedule: string): ScheduleSegment[] {
    if (!schedule || schedule === "TBA") {
        return [];
    }

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
    };
    const regex = /([A-Za-z/\s]+)\s+(\d{1,2}:\d{2}\s*[AP]M)\s*-\s*(\d{1,2}:\d{2}\s*[AP]M)/gi;
    const segments: ScheduleSegment[] = [];
    let match: RegExpExecArray | null;

    while ((match = regex.exec(schedule)) !== null) {
        const days = match[1]
            .trim()
            .split(/[\/\s,]+/)
            .map((day) => dayMap[day.toUpperCase()])
            .filter(Boolean);

        if (days.length > 0) {
            segments.push({
                days,
                start: timeToFloat(match[2]),
                end: timeToFloat(match[3]),
                timeString: `${match[2]} - ${match[3]}`,
            });
        }
    }

    return segments;
}

function getTodayName(): string {
    const today = new Date().getDay();

    return dayOrder[today - 1] || "Mon";
}

function getNextClass(classes: ClassInfo[]): { classItem: ClassInfo; segment: ScheduleSegment; day: string } | null {
    const now = new Date();
    const todayIndex = now.getDay() === 0 ? 6 : now.getDay() - 1;
    const currentTime = now.getHours() + now.getMinutes() / 60;

    const candidates = classes.flatMap((classItem) =>
        parseSchedule(classItem.schedule).flatMap((segment) =>
            segment.days.map((day) => {
                const dayIndex = dayOrder.indexOf(day);
                let distance = dayIndex - todayIndex;

                if (distance < 0 || (distance === 0 && segment.end < currentTime)) {
                    distance += 7;
                }

                return {
                    classItem,
                    segment,
                    day,
                    score: distance * 24 + segment.start,
                };
            }),
        ),
    );

    return candidates.sort((a, b) => a.score - b.score)[0] ?? null;
}

function StatTile({
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

    return (
        <Card className={`${dashboardCardClass} group relative min-h-[110px] overflow-hidden hover:-translate-y-0.5 sm:min-h-[132px]`}>
            <CardContent className="relative flex h-full flex-col justify-end p-3.5 pr-10 sm:p-5 sm:pr-20">
                <Icon
                    className={`pointer-events-none absolute top-3 right-3 h-8 w-8 opacity-15 transition-all duration-200 group-hover:scale-105 group-hover:opacity-25 sm:top-4 sm:right-5 sm:h-14 sm:w-14 ${iconTone}`}
                />

                <div className="relative z-10 min-w-0">
                    <div className="flex items-center gap-1.5">
                        <p className="text-muted-foreground text-[10px] font-medium tracking-wide uppercase sm:text-xs">{label}</p>
                        {privateValue && (
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setRevealed((current) => !current);
                                }}
                                className="text-muted-foreground/60 hover:text-foreground transition-colors"
                            >
                                {revealed ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                            </button>
                        )}
                    </div>
                    <p className="text-foreground mt-0.5 truncate text-lg font-semibold tracking-tight sm:mt-1 sm:text-2xl md:text-3xl">
                        {revealed ? value : "Hidden"}
                    </p>
                    <p className="text-muted-foreground mt-0.5 line-clamp-1 text-[10px] sm:mt-1 sm:text-xs">{detail}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function CourseCard({ classItem, index }: { classItem: ClassInfo; index: number }) {
    const accent = classAccents[index % classAccents.length];
    const average = classItem.grades.average;
    const gradingConfig = useGradingConfig();
    const averageTone =
        average === null
            ? "text-muted-foreground"
            : isPassingGrade(average, detectGradeScale(average), gradingConfig)
              ? "text-emerald-500"
              : "text-rose-500";

    return (
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
                        </div>
                        <h3 className="text-foreground text-base leading-tight font-semibold">{classItem.subject_title}</h3>
                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            <span className="flex items-center gap-1.5">
                                <UserRound className="h-3.5 w-3.5" />
                                {classItem.faculty_name}
                            </span>
                            <span className="flex items-center gap-1.5">
                                <MapPin className="h-3.5 w-3.5" />
                                {classItem.room}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="border-border/60 bg-background/45 grid grid-cols-2 gap-3 rounded-md border p-3 md:grid-cols-[1fr_auto]">
                    <div className="min-w-0">
                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Schedule</p>
                        <p className="text-foreground mt-1 truncate text-xs font-medium" title={classItem.schedule}>
                            {classItem.schedule}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Average</p>
                        <p className={cn("mt-1 font-mono text-lg font-semibold tabular-nums", averageTone)}>{average ?? "—"}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function MobileQuickActions() {
    const actions = [
        { label: "Profile", href: "/student/profile", icon: UserRound, color: "bg-blue-500" },
        { label: "Tuition", href: "/student/tuition", icon: CreditCard, color: "bg-emerald-500" },
        { label: "Schedule", href: "/student/schedule", icon: Calendar, color: "bg-amber-500" },
        { label: "Help", href: "/student/help", icon: HelpCircle, color: "bg-violet-500" },
    ];

    return (
        <section className="px-1 md:hidden">
            <div className="grid grid-cols-4 gap-4">
                {actions.map((action) => {
                    const Icon = action.icon;

                    return (
                        <Link key={action.href} href={action.href} className="group flex min-w-0 flex-col items-center gap-2">
                            <div
                                className={cn(
                                    "flex h-12 w-12 items-center justify-center rounded-2xl shadow-sm transition-all duration-200 group-active:scale-90",
                                    "bg-card border-border/40 group-hover:border-primary/40 group-hover:bg-primary/5 border",
                                )}
                            >
                                <Icon className="text-primary h-6 w-6" strokeWidth={1.5} />
                            </div>
                            <span className="text-foreground/70 group-hover:text-foreground max-w-full truncate text-[11px] font-bold tracking-tight transition-colors">
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
        <motion.div whileHover={{ y: -2 }} className="group">
            <Card className="border-border/30 bg-card/80 hover:border-border/60 relative overflow-hidden rounded-2xl shadow-sm transition-all duration-200 hover:shadow-md">
                {/* Gradient background accent */}
                <div
                    className={cn(
                        "absolute -top-4 -right-4 h-16 w-16 rounded-full opacity-20 blur-xl transition-all duration-300 group-hover:opacity-30",
                        bgTone,
                    )}
                />

                <CardContent className="relative p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-1.5">
                                <p className="text-foreground/60 text-[10px] font-bold tracking-wider uppercase">{label}</p>
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
                                className="text-foreground mt-1 truncate text-lg leading-none font-bold tracking-tight"
                            >
                                {revealed ? value : "••••••"}
                            </motion.p>
                        </div>
                        <motion.div
                            whileHover={{ scale: 1.1, rotate: 5 }}
                            transition={{ type: "spring", stiffness: 400, damping: 15 }}
                            className={cn("flex h-9 w-9 shrink-0 items-center justify-center rounded-xl shadow-sm", bgTone)}
                        >
                            <Icon className={cn("h-5 w-5", iconTone)} strokeWidth={2.2} />
                        </motion.div>
                    </div>
                    <p className="text-foreground/50 mt-2 line-clamp-1 text-[11px] font-medium">{detail}</p>
                </CardContent>
            </Card>
        </motion.div>
    );
}

function MobileAnnouncementDot({ type }: { type: AnnouncementInfo["type"] }) {
    return (
        <span
            className={cn(
                "mt-1.5 h-2 w-2 shrink-0 rounded-full",
                type === "important" ? "bg-rose-500" : type === "warning" ? "bg-amber-500" : "bg-blue-500",
            )}
        />
    );
}

function getMobileGreetingCopy(greeting: string): { headline: string; subline: string } {
    if (greeting === "Good morning") {
        return {
            headline: "Rise and shine",
            subline: "Let's make today count.",
        };
    }

    if (greeting === "Good afternoon") {
        return {
            headline: "Keep the momentum",
            subline: "Your afternoon snapshot is ready.",
        };
    }

    return {
        headline: "Evening check-in",
        subline: "Wrap up today's progress.",
    };
}

function getMobileHeroInsight({
    nextClass,
    studentData,
    gwa,
    currency,
}: {
    nextClass: { classItem: ClassInfo; segment: ScheduleSegment; day: string } | null;
    studentData: StudentDashboardProps["student_data"];
    gwa: string | null;
    currency: string;
}): { icon: LucideIcon; label: string; value: string; href: string } {
    if (nextClass) {
        return {
            icon: Calendar,
            label: "Next class",
            value: `${nextClass.classItem.subject_code} at ${nextClass.segment.timeString.split(" - ")[0]}`,
            href: "/student/schedule",
        };
    }

    if (studentData.tuition_balance > 0) {
        return {
            icon: CreditCard,
            label: "Balance due",
            value: formatMoney(studentData.tuition_balance, currency),
            href: "/student/tuition",
        };
    }

    if (gwa) {
        return {
            icon: Trophy,
            label: "Current GWA",
            value: gwa,
            href: "/student/classes",
        };
    }

    return {
        icon: BookOpen,
        label: "Academic load",
        value: `${studentData.enrolled_classes.length} subjects`,
        href: "/student/classes",
    };
}

function MobileStudentDashboard({
    greeting,
    studentData,
    currentSemester,
    currentSchoolYearLabel,
    gwa,
    gradedCount,
    nextClass,
    urgentAnnouncements,
    currency,
}: {
    greeting: string;
    studentData: StudentDashboardProps["student_data"];
    currentSemester: number;
    currentSchoolYearLabel: string;
    gwa: string | null;
    gradedCount: number;
    nextClass: { classItem: ClassInfo; segment: ScheduleSegment; day: string } | null;
    urgentAnnouncements: number;
    currency: string;
}) {
    const visibleAnnouncements = studentData.announcements.slice(0, 3);
    const visibleClasses = studentData.enrolled_classes.slice(0, 3);
    const greetingCopy = getMobileGreetingCopy(greeting);
    const heroInsight = getMobileHeroInsight({ nextClass, studentData, gwa, currency });
    const HeroInsightIcon = heroInsight.icon;

    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: "easeOut" }}
            className="mx-auto flex w-full max-w-md flex-col md:hidden"
        >
            <div className="bg-primary/10 relative h-[140px] w-full overflow-hidden px-4 pt-5">
                <div className="bg-primary/20 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                <div className="bg-primary/10 absolute -bottom-12 -left-12 h-40 w-40 rounded-full blur-2xl" />

                <div className="relative flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1 pr-2">
                        <p className="text-foreground/60 truncate text-[10px] font-bold tracking-wider uppercase">{greetingCopy.subline}</p>
                        <h1 className="text-foreground mt-0.5 text-xl leading-tight font-bold tracking-tight">
                            {greetingCopy.headline},{" "}
                            <span className="from-primary to-primary/60 block truncate bg-gradient-to-r bg-clip-text text-transparent">
                                {getShortName(studentData.student_name)}
                            </span>
                        </h1>
                    </div>
                    <div className="shrink-0 pt-1">
                        <Badge
                            variant="outline"
                            className="border-border/60 bg-background/65 text-foreground/70 rounded-full px-2 py-0.5 text-[9px] font-bold whitespace-nowrap"
                        >
                            {getSemesterLabel(currentSemester)}
                        </Badge>
                    </div>
                </div>
            </div>

            <div className="relative z-20 -mt-12 space-y-3 px-3.5 pb-24">
                <section>
                    <Card className="border-border/40 bg-card overflow-hidden rounded-xl shadow-xl shadow-black/10">
                        <CardContent className="p-3.5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-foreground/50 text-[9px] font-bold tracking-wider uppercase">Enrolled Course</p>
                                    <p className="text-foreground mt-0.5 text-[13px] leading-tight font-bold">{studentData.course || "N/A"}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-foreground/50 text-[9px] font-bold tracking-wider uppercase">Student ID</p>
                                    <p className="text-foreground mt-0.5 font-mono text-[13px] font-bold">{studentData.student_id}</p>
                                </div>
                            </div>

                            <Separator className="bg-border/40 my-3" />

                            <div className="grid grid-cols-2 gap-3">
                                <div className="flex items-center gap-2.5">
                                    <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                                        <GraduationCap className="h-4.5 w-4.5" />
                                    </div>
                                    <div>
                                        <p className="text-foreground/50 text-[8px] font-bold tracking-wider uppercase">Total Units</p>
                                        <p className="text-foreground text-[13px] font-bold">{studentData.total_units} units</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <div className="bg-primary/10 text-primary flex h-9 w-9 items-center justify-center rounded-lg">
                                        <BookOpen className="h-4.5 w-4.5" />
                                    </div>
                                    <div>
                                        <p className="text-foreground/50 text-[8px] font-bold tracking-wider uppercase">Subjects</p>
                                        <p className="text-foreground text-[13px] font-bold">{studentData.enrolled_classes.length} Enrolled</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <MobileQuickActions />

                <section>
                    <Card className="border-border/60 bg-card/75 overflow-hidden rounded-2xl shadow-sm">
                        <CardContent className="relative p-4">
                            <Calendar className="text-primary pointer-events-none absolute top-4 right-4 h-12 w-12 opacity-10" />
                            <div className="relative z-10">
                                <p className="text-foreground/60 text-[10px] font-bold tracking-wide uppercase">Next Class</p>
                                {nextClass ? (
                                    <div className="mt-2 space-y-2">
                                        <div className="flex items-start justify-between gap-3">
                                            <h2 className="text-foreground line-clamp-2 text-base leading-tight font-bold">
                                                {nextClass.classItem.subject_title}
                                            </h2>
                                            <Badge variant="outline" className="bg-background/60 shrink-0 font-mono text-[10px]">
                                                {nextClass.classItem.subject_code}
                                            </Badge>
                                        </div>
                                        <div className="text-foreground/65 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium">
                                            <span className="flex items-center gap-1.5">
                                                <Clock className="text-primary/70 h-4 w-4" />
                                                {nextClass.day}, {nextClass.segment.timeString}
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <MapPin className="text-primary/70 h-4 w-4" />
                                                {nextClass.classItem.room}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="mt-3 flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/5 border-primary/30 flex h-10 w-10 items-center justify-center rounded-full border border-dashed">
                                                <Calendar className="text-primary/40 h-5 w-5" />
                                            </div>
                                            <div>
                                                <p className="text-foreground/70 text-sm font-bold">No classes for today</p>
                                                <p className="text-foreground/45 text-xs font-medium">Enjoy your free time!</p>
                                            </div>
                                        </div>
                                        <Button
                                            asChild
                                            variant="secondary"
                                            size="sm"
                                            className="h-9 shrink-0 rounded-full px-4 text-xs font-bold shadow-none"
                                        >
                                            <Link href="/student/schedule">Schedule</Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid grid-cols-2 gap-3">
                    <MobileMetricCard
                        icon={Trophy}
                        label="GWA"
                        value={gwa || "—"}
                        detail={gwa ? `${gradedCount} graded subjects` : "No grades posted"}
                        tone="bg-amber-500/10 text-amber-500"
                    />
                    <MobileMetricCard
                        icon={BookOpen}
                        label="Subjects"
                        value={studentData.enrolled_classes.length}
                        detail={`${studentData.total_units} total units`}
                        tone="bg-blue-500/10 text-blue-500"
                    />
                    <MobileMetricCard
                        icon={CheckCircle2}
                        label="Clearance"
                        value={studentData.clearance_status ? "Cleared" : "Pending"}
                        detail={studentData.clearance_status ? "No requirement flagged" : "Requirements needed"}
                        tone={studentData.clearance_status ? "bg-emerald-500/10 text-emerald-500" : "bg-rose-500/10 text-rose-500"}
                    />
                    <MobileMetricCard
                        icon={CreditCard}
                        label="Balance"
                        value={formatMoney(studentData.tuition_balance, currency)}
                        detail="Tuition and fees"
                        tone="bg-violet-500/10 text-violet-500"
                        privateValue
                    />
                </section>

                <section>
                    <Card className="border-border/40 bg-card/60 rounded-2xl">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4 pb-2">
                            <CardTitle className="flex items-center gap-2 text-[15px] font-bold">
                                <Bell className="text-primary h-4 w-4" />
                                Notice Board
                            </CardTitle>
                            <Badge
                                variant={urgentAnnouncements > 0 ? "destructive" : "secondary"}
                                className="rounded-full px-2 py-0.5 text-[9px] font-bold"
                            >
                                {urgentAnnouncements} urgent
                            </Badge>
                        </CardHeader>
                        <CardContent className="space-y-2.5 p-4 pt-1">
                            {visibleAnnouncements.length > 0 ? (
                                visibleAnnouncements.map((announcement) => (
                                    <div
                                        key={announcement.id}
                                        className="border-border/40 bg-background/40 active:bg-background/60 flex gap-3 rounded-xl border p-3.5 transition-colors"
                                    >
                                        <MobileAnnouncementDot type={announcement.type} />
                                        <div className="min-w-0">
                                            <p className="line-clamp-1 text-sm font-bold">{announcement.title}</p>
                                            <p className="text-muted-foreground mt-1 line-clamp-2 text-xs leading-relaxed font-medium">
                                                {announcement.content}
                                            </p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="border-border/50 bg-background/35 rounded-xl border border-dashed p-6 text-center">
                                    <Bell className="text-muted-foreground/30 mx-auto h-8 w-8" />
                                    <p className="mt-3 text-sm font-bold">No announcements</p>
                                    <p className="text-muted-foreground mt-1 text-xs">New notices will appear here.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </motion.div>
    );
}

function SchedulePanel({ classes }: { classes: ClassInfo[] }) {
    const [selectedDay, setSelectedDay] = useState(getTodayName());
    const dayClasses = useMemo(() => {
        return classes
            .flatMap((classItem) =>
                parseSchedule(classItem.schedule)
                    .filter((segment) => segment.days.includes(selectedDay))
                    .map((segment) => ({ classItem, segment })),
            )
            .sort((a, b) => a.segment.start - b.segment.start);
    }, [classes, selectedDay]);

    return (
        <Card className={dashboardPanelClass}>
            <CardHeader className="pb-3">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Calendar className="text-primary h-4 w-4" />
                        Weekly Schedule
                    </CardTitle>
                    <div className="border-border/60 bg-background/60 grid grid-cols-6 gap-1 rounded-md border p-1">
                        {dayOrder.map((day) => (
                            <button
                                key={day}
                                type="button"
                                onClick={() => setSelectedDay(day)}
                                className={`h-8 rounded-md px-2 text-xs font-medium transition-colors ${
                                    selectedDay === day ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted"
                                }`}
                            >
                                {day}
                            </button>
                        ))}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {dayClasses.length > 0 ? (
                    dayClasses.map(({ classItem, segment }, index) => (
                        <div
                            key={`${classItem.id}-${segment.timeString}`}
                            className="border-border/60 bg-background/45 flex gap-3 rounded-md border p-3"
                        >
                            <div className="text-muted-foreground w-24 shrink-0 text-xs">
                                <p className="text-foreground font-medium">{segment.timeString.split(" - ")[0]}</p>
                                <p>{segment.timeString.split(" - ")[1]}</p>
                            </div>
                            <div className={`${classAccents[index % classAccents.length]} w-1 rounded-full`} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold">{classItem.subject_title}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {classItem.subject_code} - {classItem.room}
                                </p>
                            </div>
                        </div>
                    ))
                ) : (
                    <div className="border-border/60 bg-background/40 rounded-md border border-dashed p-8 text-center">
                        <p className="text-sm font-medium">No classes on {selectedDay}</p>
                        <p className="text-muted-foreground mt-1 text-xs">A quiet day in your weekly schedule.</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function FallbackIdCard({ user, studentData }: { user: User; studentData: StudentDashboardProps["student_data"] }) {
    return (
        <Card className={`${dashboardCardClass} overflow-hidden`}>
            <CardContent className="space-y-5 p-5">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                        <span className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Active Student</span>
                    </div>
                    <GraduationCap className="text-muted-foreground h-5 w-5" />
                </div>
                <div className="flex items-center gap-4">
                    <div className="border-border bg-muted h-16 w-16 overflow-hidden rounded-lg border">
                        {user.avatar ? (
                            <img src={user.avatar} alt={studentData.student_name} className="h-full w-full object-cover" />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center text-xl font-semibold">
                                {studentData.student_name.charAt(0)}
                            </div>
                        )}
                    </div>
                    <div className="min-w-0">
                        <p className="text-foreground truncate text-lg font-semibold">{studentData.student_name}</p>
                        <p className="text-muted-foreground truncate text-xs">{studentData.course || "Program not set"}</p>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">ID Number</p>
                        <p className="mt-1 font-mono">{studentData.student_id}</p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Year Level</p>
                        <p className="mt-1">Year {studentData.academic_year}</p>
                    </div>
                </div>
                <Separator />
                <Button asChild variant="outline" className="w-full justify-between">
                    <Link href="/student/profile">
                        View Full Profile
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

export default function StudentDashboard({ user, is_new_user, student_data, id_card, profile_completion_prompt }: StudentDashboardProps) {
    const { props } = usePage<{
        branding?: Branding;
        settings?: SemesterSelectorProps;
        onboarding?: {
            forceOnLogin?: boolean;
            features?: unknown[];
        };
    }>();
    const currency = props.branding?.currency || "PHP";
    const currentSemester = props.settings?.currentSemester ?? student_data.semester;
    const currentSchoolYear = props.settings?.currentSchoolYear ?? parseInt(student_data.school_year.split("-")[0]);
    const currentSchoolYearLabel =
        props.settings?.currentSchoolYear && props.settings?.availableSchoolYears
            ? props.settings.availableSchoolYears[props.settings.currentSchoolYear]
            : student_data.school_year;

    const shouldForceOnboarding = (props.onboarding?.forceOnLogin ?? false) && is_new_user;
    const hasOnboardingFeatures = (props.onboarding?.features?.length ?? 0) > 0;
    const onboardingEnabled = shouldForceOnboarding || hasOnboardingFeatures;
    const [qrCode, setQrCode] = useState(id_card?.qr_code ?? "");
    const [isRefreshingQr, setIsRefreshingQr] = useState(false);

    const greeting = useMemo(() => {
        const hour = new Date().getHours();

        if (hour < 12) {
            return "Good morning";
        }

        if (hour < 18) {
            return "Good afternoon";
        }

        return "Good evening";
    }, []);

    const gradingConfig = useGradingConfig();
    const gwaResult = useMemo(
        () =>
            computeGwa(
                student_data.enrolled_classes.map((classItem) => ({
                    // Fall back to 1 unit so unweighted averages still work when units are missing.
                    units: classItem.units && classItem.units > 0 ? classItem.units : 1,
                    grade: classItem.grades.average,
                    code: classItem.subject_code,
                    title: classItem.subject_title,
                    id: classItem.id,
                })),
                { config: gradingConfig },
            ),
        [student_data.enrolled_classes, gradingConfig],
    );
    const gwa = gwaResult.gwa !== null ? formatGwa(gwaResult, gradingConfig) : null;
    const gradedCount = gwaResult.gradedCount;
    const nextClass = useMemo(() => getNextClass(student_data.enrolled_classes), [student_data.enrolled_classes]);
    const urgentAnnouncements = student_data.announcements.filter((announcement) => announcement.type === "important").length;

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
        } finally {
            setIsRefreshingQr(false);
        }
    };

    const body = (
        <>
            {profile_completion_prompt && <ProfileCompletionPrompt prompt={profile_completion_prompt} />}
            <MobileStudentDashboard
                greeting={greeting}
                studentData={student_data}
                currentSemester={currentSemester}
                currentSchoolYearLabel={currentSchoolYearLabel}
                gwa={gwa}
                gradedCount={gradedCount}
                nextClass={nextClass}
                urgentAnnouncements={urgentAnnouncements}
                currency={currency}
            />

            <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: "easeOut" }}
                className="mx-auto hidden w-full max-w-7xl flex-col gap-4 p-4 pb-16 md:flex md:gap-6 md:p-6"
            >
                <section data-tour="welcome-header">
                    <Card className={cn(dashboardPanelClass, "relative overflow-hidden")}>
                        {/* Decorative Glass Elements */}
                        <div className="bg-primary/5 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                        <div className="bg-primary/10 absolute -bottom-12 -left-12 h-48 w-48 rounded-full blur-2xl" />

                        <CardContent className="relative z-10 p-2.5 sm:p-5 md:p-6">
                            <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                                <div className="max-w-2xl space-y-2 sm:space-y-4">
                                    <Badge
                                        variant="outline"
                                        className="border-border/60 bg-background/60 w-fit rounded-full px-2 py-0.5 text-[9px] sm:px-3 sm:py-1 sm:text-xs"
                                    >
                                        <Sparkles className="text-primary mr-1 h-2.5 w-2.5 sm:mr-1.5 sm:h-3 sm:w-3" />
                                        {getSemesterLabel(currentSemester)} • {currentSchoolYearLabel}
                                    </Badge>
                                    <div>
                                        <h1 className="text-foreground text-[1.35rem] leading-[1.15] font-bold tracking-tight sm:text-3xl md:text-4xl">
                                            {greeting},{" "}
                                            <span className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text text-transparent">
                                                {getShortName(student_data.student_name)}
                                            </span>
                                        </h1>
                                        <p className="text-muted-foreground hidden max-w-xl text-sm leading-relaxed sm:block sm:text-base">
                                            Your academic status, upcoming class, grades, and announcements are grouped here for the current term.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-2 md:min-w-[300px] md:gap-3">
                                    <div className="border-border/60 bg-background/40 hover:bg-background/60 group/info rounded-lg border p-2 transition-colors sm:rounded-xl sm:p-4">
                                        <p className="text-muted-foreground group-hover/info:text-primary text-[9px] font-bold tracking-wider uppercase transition-colors sm:text-[11px]">
                                            Program
                                        </p>
                                        <p
                                            className="text-foreground mt-0.5 truncate text-[11px] font-bold sm:mt-1 sm:text-sm"
                                            title={student_data.course || ""}
                                        >
                                            {student_data.course || "N/A"}
                                        </p>
                                    </div>
                                    <div className="border-border/60 bg-background/40 hover:bg-background/60 group/info rounded-lg border p-2 transition-colors sm:rounded-xl sm:p-4">
                                        <p className="text-muted-foreground group-hover/info:text-primary text-[9px] font-bold tracking-wider uppercase transition-colors sm:text-[11px]">
                                            Student ID
                                        </p>
                                        <p className="text-foreground mt-0.5 truncate font-mono text-[11px] font-bold sm:mt-1 sm:text-sm">
                                            {student_data.student_id}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <MobileQuickActions />

                {onboardingEnabled && <OnboardingChecklistWidget />}

                <section className="grid grid-cols-2 gap-3 lg:grid-cols-4" data-tour="stats-grid">
                    <StatTile
                        icon={Trophy}
                        label="GWA"
                        value={gwa || "—"}
                        detail={gwa ? `${gradedCount} graded subjects` : "No grades posted"}
                        tone="bg-amber-500/10 text-amber-500"
                    />
                    <StatTile
                        icon={BookOpen}
                        label="Subjects"
                        value={student_data.enrolled_classes.length}
                        detail={`${student_data.total_units} total units`}
                        tone="bg-blue-500/10 text-blue-500"
                    />
                    <StatTile
                        icon={CheckCircle2}
                        label="Clearance"
                        value={student_data.clearance_status ? "Cleared" : "Pending"}
                        detail={student_data.clearance_status ? "No requirement flagged" : "Requirements needed"}
                        tone={student_data.clearance_status ? "bg-emerald-500/10 text-emerald-500" : "bg-rose-500/10 text-rose-500"}
                    />
                    <StatTile
                        icon={CreditCard}
                        label="Balance"
                        value={formatMoney(student_data.tuition_balance, currency)}
                        detail="Tuition and fees"
                        tone="bg-violet-500/10 text-violet-500"
                        privateValue
                    />
                </section>

                <section data-tour="up-next">
                    <Card className={`${dashboardCardClass} group relative overflow-hidden`}>
                        <CardContent className="relative grid gap-4 p-4 pr-14 md:grid-cols-[1fr_auto] md:items-center md:p-5 md:pr-24">
                            <Calendar className="text-primary pointer-events-none absolute top-4 right-4 h-12 w-12 opacity-15 transition-all duration-200 group-hover:scale-105 group-hover:opacity-25 md:right-5 md:h-20 md:w-20" />
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase sm:text-xs">Up Next</p>
                                {nextClass ? (
                                    <div className="mt-2 space-y-2">
                                        <h2 className="text-foreground truncate text-lg leading-tight font-semibold md:text-xl">
                                            {nextClass.classItem.subject_title}
                                        </h2>
                                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                            <span className="flex items-center gap-1.5">
                                                <Clock className="h-3.5 w-3.5" />
                                                {nextClass.day}, {nextClass.segment.timeString}
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <MapPin className="h-3.5 w-3.5" />
                                                {nextClass.classItem.room}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground mt-2 text-sm">No scheduled classes yet.</p>
                                )}
                            </div>
                            {nextClass && (
                                <Badge variant="outline" className="border-border/60 bg-background/60 hidden rounded-full px-3 py-1 md:inline-flex">
                                    {nextClass.classItem.subject_code}
                                </Badge>
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <div className="min-w-0 space-y-6">
                        <Tabs defaultValue="overview" className="space-y-4">
                            <TabsList className="bg-muted/80 border-border/50 grid h-auto w-full grid-cols-3 rounded-lg border p-1 md:w-[420px]">
                                <TabsTrigger value="overview" className="gap-2 rounded-md">
                                    <LayoutGrid className="h-4 w-4" />
                                    Overview
                                </TabsTrigger>
                                <TabsTrigger value="schedule" className="gap-2 rounded-md">
                                    <Calendar className="h-4 w-4" />
                                    Schedule
                                </TabsTrigger>
                                <TabsTrigger value="grades" className="gap-2 rounded-md">
                                    <TrendingUp className="h-4 w-4" />
                                    Grades
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="overview" className="space-y-4">
                                <div className="flex items-end justify-between gap-3">
                                    <div>
                                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Academic Load</p>
                                        <h2 className="mt-1 text-lg font-semibold">Current Courses</h2>
                                    </div>
                                    <Badge variant="secondary" className="rounded-full">
                                        {student_data.enrolled_classes.length} subjects
                                    </Badge>
                                </div>

                                <div className="grid gap-3" data-tour="my-subjects">
                                    {student_data.enrolled_classes.length > 0 ? (
                                        student_data.enrolled_classes.map((classItem, index) => (
                                            <CourseCard key={classItem.id} classItem={classItem} index={index} />
                                        ))
                                    ) : (
                                        <Card className={`${dashboardPanelClass} border-dashed`}>
                                            <CardContent className="flex flex-col items-center justify-center p-10 text-center">
                                                <BookOpen className="text-muted-foreground h-9 w-9" />
                                                <h3 className="mt-3 font-semibold">No enrolled subjects</h3>
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    Once enrollment is finalized, your subjects will appear here.
                                                </p>
                                            </CardContent>
                                        </Card>
                                    )}
                                </div>
                            </TabsContent>

                            <TabsContent value="schedule">
                                <SchedulePanel classes={student_data.enrolled_classes} />
                            </TabsContent>

                            <TabsContent value="grades" className="space-y-4">
                                <GradesPanel classes={student_data.enrolled_classes} gwaResult={gwaResult} />
                            </TabsContent>
                        </Tabs>
                    </div>

                    <aside className="space-y-4">
                        <motion.div
                            data-tour="id-card"
                            initial={{ opacity: 0, y: 12 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.35 }}
                        >
                            {id_card ? (
                                <DigitalIdCard
                                    cardData={id_card.card_data}
                                    photoUrl={id_card.photo_url}
                                    qrCode={qrCode}
                                    isValid={id_card.is_valid}
                                    isCompact
                                    onRefresh={handleRefreshQr}
                                    onExpand={() => router.visit("/student/id-card/view")}
                                    isRefreshing={isRefreshingQr}
                                />
                            ) : (
                                <FallbackIdCard user={user} studentData={student_data} />
                            )}
                        </motion.div>

                        <Card className={dashboardPanelClass}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Bell className="text-primary h-4 w-4" />
                                    Notice Board
                                </CardTitle>
                                <Badge variant={urgentAnnouncements > 0 ? "destructive" : "secondary"}>{urgentAnnouncements} urgent</Badge>
                            </CardHeader>
                            <CardContent className="p-0">
                                <ScrollArea className="h-[310px]">
                                    {student_data.announcements.length > 0 ? (
                                        <div className="divide-border divide-y">
                                            {student_data.announcements.map((announcement) => (
                                                <div key={announcement.id} className="hover:bg-muted/35 p-4 transition-colors">
                                                    <div className="flex gap-3">
                                                        <span
                                                            className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${
                                                                announcement.type === "important"
                                                                    ? "bg-rose-500"
                                                                    : announcement.type === "warning"
                                                                      ? "bg-amber-500"
                                                                      : "bg-blue-500"
                                                            }`}
                                                        />
                                                        <div className="min-w-0">
                                                            <p className="line-clamp-1 text-sm font-semibold">{announcement.title}</p>
                                                            <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">{announcement.content}</p>
                                                            <p className="text-muted-foreground mt-2 text-[10px] font-semibold tracking-wide uppercase">
                                                                {announcement.date}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="p-8 text-center">
                                            <Bell className="text-muted-foreground mx-auto h-8 w-8" />
                                            <p className="mt-3 text-sm font-medium">No announcements</p>
                                            <p className="text-muted-foreground mt-1 text-xs">New notices will appear here.</p>
                                        </div>
                                    )}
                                </ScrollArea>
                            </CardContent>
                        </Card>
                    </aside>
                </section>
            </motion.div>
        </>
    );

    return (
        <StudentLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Student Dashboard" />
            {onboardingEnabled ? (
                <OnboardingProvider variant="student" userId={user.id} checklist={studentChecklist} totalSteps={4} enabled={onboardingEnabled}>
                    <OnboardingTour steps={studentTourSteps} />
                    {body}
                </OnboardingProvider>
            ) : (
                body
            )}
        </StudentLayout>
    );
}
