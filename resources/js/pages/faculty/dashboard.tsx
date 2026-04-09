import { ChartAreaInteractive } from "@/components/chart-area-interactive";
import { AnnouncementsWidget } from "@/components/dashboard/announcements-widget";
import { CalendarWidget } from "@/components/dashboard/calendar-widget";
import { QuickActions } from "@/components/dashboard/quick-actions";
import { RecentActivity } from "@/components/dashboard/recent-activity";
import { TodaySchedule } from "@/components/dashboard/today-schedule";
import { ClassData, DataTable } from "@/components/data-table";
import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import FacultyLayout from "@/components/faculty/faculty-layout";
import { OnboardingExperience, type OnboardingFeatureData } from "@/components/onboarding-experience";
import { SectionCards, Stat } from "@/components/section-cards";
import { Button } from "@/components/ui/button";
import { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
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

type DashboardRecentActivity = {
    action: string;
    target: string;
    time: string;
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

interface DashboardProps {
    user: User;
    faculty_data: {
        stats: Stat[];
        upcoming_classes: ClassData[];
        recent_activity: DashboardRecentActivity[];
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

export default function FacultyDashboard({ user, faculty_data, id_card }: DashboardProps) {
    const { props } = usePage<{
        onboarding?: {
            forceOnLogin?: boolean;
            features?: OnboardingFeatureData[];
            dismissEndpoint?: string;
        };
    }>();
    const shouldForceOnboarding = props.onboarding?.forceOnLogin ?? false;
    const onboardingFeatures = props.onboarding?.features ?? [];
    const dismissEndpoint = props.onboarding?.dismissEndpoint;
    const hasOnboardingFeatures = onboardingFeatures.length > 0;
    const onboardingEnabled = shouldForceOnboarding || hasOnboardingFeatures;

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

    return (
        <FacultyLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Faculty Dashboard" />
            <OnboardingExperience
                variant="faculty"
                userId={user.id}
                enabled={onboardingEnabled}
                force={!hasOnboardingFeatures && shouldForceOnboarding}
                features={hasOnboardingFeatures ? onboardingFeatures : undefined}
                onDismiss={(featureKey) => {
                    if (!dismissEndpoint) return;
                    router.post(dismissEndpoint, { feature_key: featureKey }, { preserveScroll: true });
                }}
            />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <section className="flex flex-col gap-4 rounded-2xl border bg-gradient-to-r from-sky-50 to-blue-50 p-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">Faculty Dashboard</h1>
                        <p className="mt-1 text-sm text-slate-600">Welcome back, {user.name}. Here is your teaching overview for today.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild>
                            <Link href="/faculty/classes">My Classes</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/faculty/schedule">View Schedule</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/faculty/action-center">Action Center</Link>
                        </Button>
                    </div>
                </section>

                <SectionCards stats={faculty_data.stats} />

                <div className="grid gap-6 lg:grid-cols-12">
                    <div className="flex flex-col gap-6 lg:col-span-8">
                        <QuickActions classes={faculty_data.upcoming_classes} />
                        <TodaySchedule schedule={faculty_data.today_schedule} />

                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-foreground text-lg font-medium">Upcoming Classes</h3>
                            </div>
                            <DataTable data={faculty_data.upcoming_classes} />
                        </div>

                        <ChartAreaInteractive
                            chartData={faculty_data.attendance_chart?.chart_data ?? []}
                            classes={faculty_data.attendance_chart?.classes ?? []}
                        />
                    </div>

                    <div className="flex flex-col gap-6 lg:col-span-4">
                        {id_card && (
                            <motion.div initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.2 }}>
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
                        <RecentActivity activities={faculty_data.recent_activity} />
                    </div>
                </div>
            </main>
        </FacultyLayout>
    );
}
