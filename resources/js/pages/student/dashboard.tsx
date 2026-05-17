import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import { type OnboardingChecklistItem, OnboardingProvider } from "@/components/onboarding-context";
import { OnboardingExperience, type OnboardingFeatureData } from "@/components/onboarding-experience";
import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { type User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import {
    AlertCircle,
    ArrowRight,
    Bell,
    BookOpen,
    Calendar,
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
    UserRound,
} from "lucide-react";
import { useMemo, useState } from "react";
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

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
        actionRoute: "/student/grades",
        actionLabel: "View Grades",
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

    return name.split(" ")[0] || name;
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

function getGradeTone(grade: number | null): string {
    if (!grade) {
        return "text-muted-foreground";
    }

    return grade <= 3 ? "text-emerald-500" : "text-rose-500";
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
        <Card className={`${dashboardCardClass} group relative min-h-[132px] overflow-hidden hover:-translate-y-0.5`}>
            <CardContent className="relative flex h-full flex-col justify-end p-5 pr-20 sm:min-h-[150px]">
                <Icon
                    className={`pointer-events-none absolute top-4 right-5 h-14 w-14 opacity-15 transition-all duration-200 group-hover:scale-105 group-hover:opacity-25 ${iconTone}`}
                />

                <div className="absolute top-4 left-5 z-10">
                    {privateValue && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="hover:bg-muted/70 h-8 w-8 rounded-md"
                            onClick={() => setRevealed((current) => !current)}
                        >
                            {revealed ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </Button>
                    )}
                </div>

                <div className="relative z-10 min-w-0">
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</p>
                    <p className="text-foreground mt-1 truncate text-2xl font-semibold tracking-tight md:text-3xl">{revealed ? value : "Hidden"}</p>
                    <p className="text-muted-foreground mt-1 text-xs">{detail}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function CourseCard({ classItem, index }: { classItem: ClassInfo; index: number }) {
    const accent = classAccents[index % classAccents.length];
    const average = classItem.grades.average;

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
                        <p className={`mt-1 font-mono text-lg font-semibold ${getGradeTone(average)}`}>{average ?? "N/A"}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
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
    const shouldForceOnboarding = props.onboarding?.forceOnLogin ?? false;
    const onboardingFeatures = props.onboarding?.features ?? [];
    const dismissEndpoint = props.onboarding?.dismissEndpoint;
    const onboardingEnabled = shouldForceOnboarding || onboardingFeatures.length > 0;
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

    const gwa = useMemo(() => {
        const classesWithGrades = student_data.enrolled_classes.filter((classItem) => classItem.grades.average !== null);

        if (classesWithGrades.length === 0) {
            return null;
        }

        const total = classesWithGrades.reduce((sum, classItem) => sum + (classItem.grades.average ?? 0), 0);

        return (total / classesWithGrades.length).toFixed(2);
    }, [student_data.enrolled_classes]);

    const gradedCount = student_data.enrolled_classes.filter((classItem) => classItem.grades.average !== null).length;
    const gradeProgress = student_data.enrolled_classes.length > 0 ? (gradedCount / student_data.enrolled_classes.length) * 100 : 0;
    const nextClass = useMemo(() => getNextClass(student_data.enrolled_classes), [student_data.enrolled_classes]);
    const urgentAnnouncements = student_data.announcements.filter((announcement) => announcement.type === "important").length;
    const chartData = useMemo(
        () =>
            student_data.enrolled_classes.map((classItem) => ({
                name: classItem.subject_code,
                grade: classItem.grades.average ?? 0,
                hasGrade: classItem.grades.average !== null,
            })),
        [student_data.enrolled_classes],
    );

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
            {onboardingEnabled && (
                <OnboardingProvider variant="student" userId={user.id} checklist={studentChecklist} totalSteps={4} enabled={onboardingEnabled}>
                    <OnboardingExperience
                        enabled={onboardingEnabled}
                        features={onboardingFeatures.length > 0 ? onboardingFeatures : undefined}
                        onDismiss={(featureKey) => {
                            if (!dismissEndpoint) {
                                return;
                            }

                            router.post(dismissEndpoint, { feature_key: featureKey }, { preserveScroll: true });
                        }}
                    />
                </OnboardingProvider>
            )}

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 pb-16 md:gap-6 md:p-6">
                <section>
                    <Card className={dashboardPanelClass}>
                        <CardContent className="p-4 md:p-5">
                            <div className="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                                <div className="max-w-2xl space-y-3">
                                    <Badge variant="outline" className="bg-background/75 w-fit rounded-full px-3 py-1">
                                        <Sparkles className="text-primary mr-1.5 h-3 w-3" />
                                        {getSemesterLabel(student_data.semester)} - {student_data.school_year}
                                    </Badge>
                                    <div>
                                        <h1 className="text-foreground text-2xl font-semibold tracking-tight md:text-3xl">
                                            {greeting}, {getShortName(student_data.student_name)}
                                        </h1>
                                        <p className="text-muted-foreground mt-2 max-w-xl text-sm">
                                            Your academic status, upcoming class, grades, and announcements are grouped here for the current term.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3 md:min-w-[280px]">
                                    <div className="border-border/60 bg-background/50 rounded-md border p-3">
                                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Program</p>
                                        <p className="mt-1 truncate text-sm font-semibold" title={student_data.course || ""}>
                                            {student_data.course || "N/A"}
                                        </p>
                                    </div>
                                    <div className="border-border/60 bg-background/50 rounded-md border p-3">
                                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Student ID</p>
                                        <p className="mt-1 truncate font-mono text-sm font-semibold">{student_data.student_id}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile
                        icon={Trophy}
                        label="GWA"
                        value={gwa || "N/A"}
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

                <section>
                    <Card className={`${dashboardCardClass} group relative overflow-hidden`}>
                        <CardContent className="relative grid gap-4 p-4 pr-20 md:grid-cols-[1fr_auto] md:items-center md:p-5 md:pr-24">
                            <Calendar className="text-primary pointer-events-none absolute top-4 right-5 h-16 w-16 opacity-15 transition-all duration-200 group-hover:scale-105 group-hover:opacity-25 md:h-20 md:w-20" />
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Up Next</p>
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

                                <div className="grid gap-3">
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
                                <Card className={dashboardPanelClass}>
                                    <CardHeader className="pb-3">
                                        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <TrendingUp className="text-primary h-4 w-4" />
                                                Grade Overview
                                            </CardTitle>
                                            <div className="min-w-[180px]">
                                                <div className="mb-1 flex items-center justify-between text-xs">
                                                    <span className="text-muted-foreground">Posted grades</span>
                                                    <span>
                                                        {gradedCount}/{student_data.enrolled_classes.length}
                                                    </span>
                                                </div>
                                                <Progress value={gradeProgress} className="h-2" />
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="h-[260px] md:h-[280px]">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart data={chartData} margin={{ top: 10, right: 8, left: -20, bottom: 0 }}>
                                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" />
                                                    <XAxis
                                                        dataKey="name"
                                                        axisLine={false}
                                                        tickLine={false}
                                                        tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 12 }}
                                                    />
                                                    <YAxis
                                                        axisLine={false}
                                                        tickLine={false}
                                                        tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 12 }}
                                                        domain={[0, 5]}
                                                        reversed
                                                    />
                                                    <Tooltip
                                                        cursor={{ fill: "hsl(var(--muted))" }}
                                                        content={({ active, payload }) => {
                                                            if (!active || !payload?.length) {
                                                                return null;
                                                            }

                                                            const data = payload[0].payload as { name: string; grade: number; hasGrade: boolean };

                                                            return (
                                                                <div className="bg-popover rounded-lg border p-3 text-sm shadow-lg">
                                                                    <p className="font-semibold">{data.name}</p>
                                                                    <p className="text-muted-foreground mt-1 text-xs">
                                                                        Average: {data.hasGrade ? data.grade : "Not posted"}
                                                                    </p>
                                                                </div>
                                                            );
                                                        }}
                                                    />
                                                    <Bar dataKey="grade" radius={[6, 6, 0, 0]} barSize={36}>
                                                        {chartData.map((entry, index) => (
                                                            <Cell
                                                                key={entry.name}
                                                                fill={entry.hasGrade ? `var(--chart-${(index % 5) + 1})` : "hsl(var(--muted))"}
                                                            />
                                                        ))}
                                                    </Bar>
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </CardContent>
                                </Card>

                                <div className="grid gap-3 md:grid-cols-2">
                                    {student_data.enrolled_classes.map((classItem) => (
                                        <Card key={classItem.id} className={dashboardCardClass}>
                                            <CardContent className="space-y-3 p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-semibold">{classItem.subject_title}</p>
                                                        <p className="text-muted-foreground font-mono text-xs">{classItem.subject_code}</p>
                                                    </div>
                                                    <p className={`font-mono text-lg font-semibold ${getGradeTone(classItem.grades.average)}`}>
                                                        {classItem.grades.average ?? "N/A"}
                                                    </p>
                                                </div>
                                                <div className="grid grid-cols-3 gap-2 text-center">
                                                    <div className="bg-muted/55 rounded-md p-2">
                                                        <p className="text-muted-foreground text-[10px] uppercase">Prelim</p>
                                                        <p className="font-mono text-sm">{classItem.grades.prelim ?? "N/A"}</p>
                                                    </div>
                                                    <div className="bg-muted/55 rounded-md p-2">
                                                        <p className="text-muted-foreground text-[10px] uppercase">Midterm</p>
                                                        <p className="font-mono text-sm">{classItem.grades.midterm ?? "N/A"}</p>
                                                    </div>
                                                    <div className="bg-muted/55 rounded-md p-2">
                                                        <p className="text-muted-foreground text-[10px] uppercase">Finals</p>
                                                        <p className="font-mono text-sm">{classItem.grades.finals ?? "N/A"}</p>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>

                    <aside className="space-y-4">
                        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }}>
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

                        <div className="grid grid-cols-2 gap-3">
                            <Button
                                asChild
                                variant="outline"
                                className="hover:border-primary/30 h-11 justify-start gap-2 rounded-lg transition-all hover:-translate-y-0.5"
                            >
                                <Link href="/student/profile">
                                    <UserRound className="h-4 w-4" />
                                    Profile
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="hover:border-primary/30 h-11 justify-start gap-2 rounded-lg transition-all hover:-translate-y-0.5"
                            >
                                <Link href="/student/tuition">
                                    <CreditCard className="h-4 w-4" />
                                    Tuition
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="hover:border-primary/30 h-11 justify-start gap-2 rounded-lg transition-all hover:-translate-y-0.5"
                            >
                                <Link href="/student/schedule">
                                    <Calendar className="h-4 w-4" />
                                    Schedule
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="hover:border-primary/30 h-11 justify-start gap-2 rounded-lg transition-all hover:-translate-y-0.5"
                            >
                                <Link href="/student/help">
                                    <AlertCircle className="h-4 w-4" />
                                    Help
                                </Link>
                            </Button>
                        </div>
                    </aside>
                </section>
            </div>
        </StudentLayout>
    );
}
