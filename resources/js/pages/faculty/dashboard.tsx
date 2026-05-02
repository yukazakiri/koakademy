import { AnnouncementsWidget } from "@/components/dashboard/announcements-widget";
import { CalendarWidget } from "@/components/dashboard/calendar-widget";
import { StatsGrid, Stat } from "@/components/dashboard/stats-grid";
import { TodaySchedule } from "@/components/dashboard/today-schedule";
import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import FacultyLayout from "@/components/faculty/faculty-layout";
import { OnboardingChecklistWidget } from "@/components/onboarding-checklist";
import { OnboardingProvider } from "@/components/onboarding-context";
import { OnboardingTour, type TourStep } from "@/components/onboarding-tour";
import type { OnboardingFeatureData } from "@/components/onboarding-experience";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ChartAreaInteractive } from "@/components/chart-area-interactive";
import { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { IconArrowRight, IconCalendar, IconCalendarEvent, IconMapPin, IconSchool, IconUsers } from "@tabler/icons-react";
import { motion } from "framer-motion";
import { useState } from "react";

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

const classColors = [
    "bg-blue-500",
    "bg-emerald-500",
    "bg-amber-500",
    "bg-rose-500",
    "bg-violet-500",
    "bg-cyan-500",
];

function PeriodBadge({ semester, schoolYear }: { semester: string; schoolYear: string }) {
    const label = semester === "summer" ? "Summer" : `Semester ${semester}`;

    return (
        <Badge variant="outline" className="bg-background/80 rounded-full px-3 py-1 text-xs font-medium backdrop-blur-sm">
            <IconCalendarEvent className="mr-1.5 h-3 w-3 text-primary" />
            {label} &bull; {schoolYear}
        </Badge>
    );
}

function ClassListCard({ classItem, index }: { classItem: DashboardClass; index: number }) {
    const color = classColors[index % classColors.length];
    const isShs = (classItem.classification ?? "").toLowerCase() === "shs";

    return (
        <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05, duration: 0.35 }}
        >
            <Card className="border-border/60 hover:border-primary/20 group overflow-hidden border transition-all duration-300 hover:shadow-md">
                <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3.5">
                        <div className={`mt-0.5 h-10 w-1.5 shrink-0 rounded-full ${color}`} />
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h4 className="text-foreground text-sm font-semibold leading-tight">{classItem.subject_title}</h4>
                                <Badge variant="outline" className="h-5 px-1.5 text-[10px] font-medium">
                                    {classItem.subject_code}
                                </Badge>
                                {isShs && (
                                    <Badge variant="secondary" className="h-5 px-1.5 text-[10px] font-medium">
                                        SHS
                                    </Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                <span className="flex items-center gap-1">
                                    <IconSchool className="h-3 w-3" />
                                    Section {classItem.section}
                                </span>
                                <span className="flex items-center gap-1">
                                    <IconMapPin className="h-3 w-3" />
                                    {classItem.room}
                                </span>
                                <span className="flex items-center gap-1">
                                    <IconUsers className="h-3 w-3" />
                                    {classItem.students_count} students
                                </span>
                            </div>
                            <p className="text-muted-foreground/70 text-[10px]">
                                {classItem.semester === "summer" ? "Summer" : `Semester ${classItem.semester}`} &bull; {classItem.school_year}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 sm:pl-4">
                        <Button asChild size="sm" variant="outline" className="rounded-lg">
                            <Link href={`/faculty/classes/${classItem.id}?view=attendance`} prefetch>
                                <IconCalendar className="mr-1.5 h-3.5 w-3.5" />
                                Attendance
                            </Link>
                        </Button>
                        <Button asChild size="sm" className="rounded-lg">
                            <Link href={`/faculty/classes/${classItem.id}`}>
                                Open
                                <IconArrowRight className="ml-1 h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </motion.div>
    );
}

// Faculty onboarding checklist items
const facultyChecklist = [
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

    return (
        <>
            <Head title="Faculty Dashboard" />
            {onboardingEnabled && <OnboardingTour steps={facultyTourSteps} />}

            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                {/* Header */}
                <section data-tour="welcome-header" className="flex flex-col gap-4 rounded-2xl border bg-gradient-to-r from-sky-50 to-blue-50 p-5 md:flex-row md:items-center md:justify-between md:p-6 dark:from-sky-950/20 dark:to-blue-950/20">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">Welcome back, {user.name.split(" ")[0]}</h1>
                            <PeriodBadge semester={current_semester} schoolYear={current_school_year} />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {faculty_data.today_schedule.entries.length > 0
                                ? `You have ${faculty_data.today_schedule.entries.length} class${faculty_data.today_schedule.entries.length > 1 ? "es" : ""} scheduled today.`
                                : "No classes scheduled for today. Enjoy your free time!"}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild>
                            <Link href="/faculty/classes">My Classes</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/faculty/schedule">Schedule</Link>
                        </Button>
                    </div>
                </section>

                {/* Onboarding Checklist Widget */}
                {onboardingEnabled && (
                    <OnboardingChecklistWidget />
                )}

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
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-foreground text-lg font-semibold">My Classes</h3>
                                    <p className="text-muted-foreground text-xs">
                                        {hasClasses
                                            ? `${faculty_data.upcoming_classes.length} active class${faculty_data.upcoming_classes.length > 1 ? "es" : ""} this period`
                                            : "No classes assigned for the selected period"}
                                    </p>
                                </div>
                                <Button asChild size="sm" variant="outline">
                                    <Link href="/faculty/classes">View All</Link>
                                </Button>
                            </div>

                            {hasClasses ? (
                                <div className="grid gap-3">
                                    {faculty_data.upcoming_classes.map((classItem, index) => (
                                        <ClassListCard key={classItem.id} classItem={classItem} index={index} />
                                    ))}
                                </div>
                            ) : (
                                <Card className="border-border/60 border">
                                    <CardContent className="flex flex-col items-center justify-center gap-3 py-14 text-center">
                                        <div className="bg-muted/50 rounded-full p-3">
                                            <IconSchool className="text-muted-foreground h-6 w-6" />
                                        </div>
                                        <div>
                                            <p className="text-foreground text-sm font-medium">No classes found</p>
                                            <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                                                You don&apos;t have any classes assigned for{" "}
                                                {current_semester === "summer" ? "Summer" : `Semester ${current_semester}`}, {current_school_year}.
                                            </p>
                                        </div>
                                        <Button asChild variant="outline" size="sm">
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
            </main>
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
