import AdminLayout from "@/components/administrators/admin-layout";
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    BarXAxis,
    BarYAxis,
    ChartTooltip,
    FunnelChart,
    Gauge,
    Grid,
    Legend,
    LegendItem,
    LegendLabel,
    LegendMarker,
    LegendProgress,
    LegendValue,
    Ring,
    RingCenter,
    RingChart,
    XAxis,
    chartCssVars,
    type LegendItemData,
} from "@/components/charts";
import { StatCardArea, type StatCardAreaPoint } from "@/components/stat-card-area";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    Banknote,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    GraduationCap,
    Info,
    LayoutDashboard,
    ListChecks,
    PieChart,
    School,
    ShieldCheck,
    TrendingUp,
    UserCheck,
    Users,
    Workflow,
    type LucideIcon,
} from "lucide-react";
import { useMemo, useState } from "react";
import { route } from "ziggy-js";

type AdminStatTone = "success" | "warning" | "info" | "neutral";

type AdminStat = {
    label: string;
    value: number | string;
    description: string;
    format?: "number" | "percent";
    series?: StatCardAreaPoint[];
    tone: AdminStatTone;
    trend?: number;
};

type QuickAction = {
    title: string;
    description: string;
    href: string;
    disabled: boolean;
    disabledTooltip?: string;
};

type RecentActivityItem = {
    actor: string;
    action: string;
    time: string;
    status: "success" | "info" | "warning" | "error" | "neutral";
};

type BeginnerTip = {
    title: string;
    content: string;
};

type PipelineItem = {
    status: string;
    count: number;
    color?: string;
};

type StudentTypeItem = {
    type: string;
    label: string;
    count: number;
    percentage: number;
};

type TopCourse = {
    code: string;
    title: string;
    student_count: number;
};

type RecentStudent = {
    id: number;
    student_id: string | null;
    name: string;
    type: string | null;
    status: string | null;
    course: string | null;
    registered_at: string;
};

type FinanceSnapshot = {
    total_revenue: number;
    total_collectibles: number;
    total_assessed: number;
    collection_rate: number;
    fully_paid_count: number;
    outstanding_count: number;
    today_collection: number;
    today_transactions: number;
};

type ActionQueueItem = {
    label: string;
    value: number;
    description: string;
    href: string;
    tone: AdminStatTone;
};

type AdminAnalytics = {
    last_updated_at: string;
    enrollment_trends: TrendPoint[];
    enrollment_status: PipelineItem[];
    application_vs_enrollment: {
        applicants: number;
        enrolled: number;
        on_leave: number;
        conversion_rate: number;
    };
    student_types: StudentTypeItem[];
    gender_distribution: { gender: string; count: number }[];
    year_level_distribution: { year_level: string; count: number }[];
    top_courses: TopCourse[];
    recent_students: RecentStudent[];
};

type AdminData = {
    current_period: {
        school_year: string;
        semester: number;
        label: string;
    };
    stats: AdminStat[];
    quick_actions: QuickAction[];
    recent_activity: RecentActivityItem[];
    beginner_tips: BeginnerTip[];
    executive_summary: {
        kpis: AdminStat[];
        last_updated_at: string;
    };
    enrollment_health: {
        pending: number;
        enrolled_this_period: number;
        conversion_rate: number;
        applicants: number;
        enrolled: number;
        on_leave: number;
        pipeline: PipelineItem[];
        trends: TrendPoint[];
    };
    student_demographics: {
        total: number;
        by_type: StudentTypeItem[];
        by_gender: { gender: string; count: number }[];
        by_year_level: { year_level: string; count: number }[];
        top_courses: TopCourse[];
    };
    finance_snapshot: FinanceSnapshot;
    operations: {
        total_faculty: number;
        active_classes: number;
        total_users: number;
        unassigned_classes: number;
        action_queue: ActionQueueItem[];
    };
    recent_records: {
        students: RecentStudent[];
        activity: RecentActivityItem[];
    };
    analytics: AdminAnalytics;
};

interface AdminDashboardProps {
    user: User;
    admin_data: AdminData;
}

type TrendPoint = {
    date: string;
    month: string;
    enrollments: number;
};

type Branding = {
    currency?: string;
};

type TrendRange = "year" | "six_months";

const adminCardClass = "border-border/60 bg-card/80 rounded-lg shadow-sm";
const adminPanelClass = "border-border/60 bg-card/80 rounded-lg shadow-sm";
const chartPalette = ["var(--chart-1)", "var(--chart-2)", "var(--chart-3)", "var(--chart-4)", "var(--chart-5)"] as const;

function toneBadgeClass(tone: AdminStatTone): string {
    if (tone === "success") {
        return "border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400";
    }

    if (tone === "warning") {
        return "border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400";
    }

    if (tone === "info") {
        return "border-sky-500/30 bg-sky-500/10 text-sky-600 dark:text-sky-400";
    }

    return "border-border bg-muted/40 text-muted-foreground";
}

function toneIconClass(tone: AdminStatTone): string {
    if (tone === "success") {
        return "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400";
    }

    if (tone === "warning") {
        return "bg-amber-500/10 text-amber-600 dark:text-amber-400";
    }

    if (tone === "info") {
        return "bg-sky-500/10 text-sky-600 dark:text-sky-400";
    }

    return "bg-muted text-muted-foreground";
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat("en-US").format(value);
}

function formatPercent(value: number): string {
    return `${new Intl.NumberFormat("en-US", { maximumFractionDigits: 1 }).format(value)}%`;
}

function formatCurrency(amount: number, currency: string): string {
    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
        style: "currency",
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(new Date(value));
}

function parseStatNumber(value: number | string): number {
    if (typeof value === "number") {
        return value;
    }

    const parsed = Number.parseFloat(value.replace(/[^0-9.-]+/g, ""));

    return Number.isFinite(parsed) ? parsed : 0;
}

function hasPositiveData<T extends Record<string, unknown>>(data: T[], key: keyof T): boolean {
    return data.some((item) => Number(item[key] ?? 0) > 0);
}

function EmptyPanel({ label }: { label: string }) {
    return (
        <div className="text-muted-foreground flex min-h-40 flex-col items-center justify-center rounded-lg border border-dashed p-6 text-center">
            <ListChecks className="mb-2 h-6 w-6 opacity-40" />
            <p className="text-sm">{label}</p>
        </div>
    );
}

function SectionHeading({ icon: Icon, title, description }: { icon: LucideIcon; title: string; description: string }) {
    return (
        <CardHeader className="border-border/60 border-b pb-4">
            <CardTitle className="flex items-center gap-2 text-base">
                <Icon className="text-primary h-4 w-4" />
                {title}
            </CardTitle>
            <CardDescription>{description}</CardDescription>
        </CardHeader>
    );
}

function DashboardStatCard({ stat, index }: { stat: AdminStat; index: number }) {
    const numericValue = parseStatNumber(stat.value);
    const chartData = stat.series?.length ? stat.series : [{ date: new Date().toISOString(), value: numericValue }];
    const isPercent = stat.format === "percent";

    return (
        <StatCardArea
            chartColor={chartPalette[index % chartPalette.length]}
            data={chartData}
            description={stat.description}
            formatOptions={{
                maximumFractionDigits: isPercent ? 1 : 0,
            }}
            label={isPercent ? "Rate" : "Current"}
            suffix={isPercent ? "%" : undefined}
            title={stat.label}
            trend={stat.trend ?? 0}
            value={numericValue}
        />
    );
}

function MetricTile({ icon: Icon, label, value, description }: { icon: LucideIcon; label: string; value: string; description: string }) {
    return (
        <div className="border-border/60 bg-background/40 rounded-lg border p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</p>
                    <p className="text-foreground mt-2 text-2xl font-semibold tracking-tight">{value}</p>
                </div>
                <div className="bg-primary/10 text-primary rounded-lg p-2">
                    <Icon className="h-4 w-4" />
                </div>
            </div>
            <p className="text-muted-foreground mt-2 text-sm">{description}</p>
        </div>
    );
}

function ActionQueue({ items }: { items: ActionQueueItem[] }) {
    return (
        <div className="grid gap-3">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="border-border/60 hover:bg-muted/25 flex flex-col gap-3 rounded-lg border p-4 transition-colors sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-foreground font-medium">{item.label}</p>
                            <Badge variant="outline" className={`${toneBadgeClass(item.tone)} rounded-md`}>
                                {formatNumber(item.value)}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">{item.description}</p>
                    </div>
                    <Button asChild variant="outline" className="rounded-lg">
                        <Link href={item.href}>
                            Open
                            <ArrowUpRight className="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            ))}
        </div>
    );
}

function EnrollmentTrendChart({ data }: { data: TrendPoint[] }) {
    const hasData = hasPositiveData(data, "enrollments");

    if (!hasData) {
        return <EmptyPanel label="No enrollment trend data is available for this period yet." />;
    }

    return (
        <AreaChart data={data} xDataKey="date" className="h-[280px] w-full" aspectRatio="16 / 7">
            <Grid horizontal />
            <Area dataKey="enrollments" fill={chartCssVars.linePrimary} fillOpacity={0.36} showMarkers />
            <XAxis tickMode="data" />
            <ChartTooltip showDatePill={false} />
        </AreaChart>
    );
}

function BarComparisonChart({ data, dataKey, labelKey }: { data: Record<string, string | number>[]; dataKey: string; labelKey: string }) {
    if (!hasPositiveData(data, dataKey)) {
        return <EmptyPanel label="No comparison data is available yet." />;
    }

    return (
        <BarChart data={data} xDataKey={labelKey} className="h-[250px] w-full" aspectRatio="16 / 8" margin={{ left: 64, right: 20, top: 24, bottom: 36 }}>
            <Grid horizontal />
            <Bar dataKey={dataKey} fill={chartCssVars.linePrimary} />
            <BarXAxis />
            <ChartTooltip showDatePill={false} />
        </BarChart>
    );
}

function HorizontalBarChart({ data, dataKey, labelKey }: { data: Record<string, string | number>[]; dataKey: string; labelKey: string }) {
    if (!hasPositiveData(data, dataKey)) {
        return <EmptyPanel label="No ranked data is available yet." />;
    }

    return (
        <BarChart
            data={data}
            xDataKey={labelKey}
            orientation="horizontal"
            className="h-[280px] w-full"
            aspectRatio="16 / 8"
            margin={{ left: 86, right: 24, top: 18, bottom: 24 }}
        >
            <Grid vertical />
            <Bar dataKey={dataKey} fill={chartCssVars.linePrimary} />
            <BarYAxis />
            <ChartTooltip showDatePill={false} />
        </BarChart>
    );
}

function StudentMixChart({ items, total }: { items: StudentTypeItem[]; total: number }) {
    const ringData: LegendItemData[] = items.map((item, index) => ({
        label: item.label,
        value: item.count,
        maxValue: Math.max(total, 1),
        color: chartPalette[index % chartPalette.length],
    }));

    if (!hasPositiveData(items, "count")) {
        return <EmptyPanel label="Student mix appears after student profiles are added." />;
    }

    return (
        <div className="grid gap-4 md:grid-cols-[minmax(220px,280px)_1fr] md:items-center">
            <div className="mx-auto w-full max-w-[280px]">
                <RingChart data={ringData} size={260}>
                    {ringData.map((item, index) => (
                        <Ring key={item.label} index={index} />
                    ))}
                    <RingCenter defaultLabel="Students" />
                </RingChart>
            </div>
            <Legend items={ringData} className="grid gap-3">
                <LegendItem className="grid grid-cols-[auto_1fr_auto] items-center gap-x-3 gap-y-1">
                    <LegendMarker />
                    <LegendLabel />
                    <LegendValue showPercentage />
                    <div className="col-span-full">
                        <LegendProgress />
                    </div>
                </LegendItem>
            </Legend>
        </div>
    );
}

function RecentStudentsTable({ students }: { students: RecentStudent[] }) {
    if (students.length === 0) {
        return <EmptyPanel label="No student registrations yet." />;
    }

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Student ID</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Course</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Registered</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {students.map((student) => (
                        <TableRow key={student.id} className="hover:bg-muted/35">
                            <TableCell className="text-muted-foreground font-mono text-xs">{student.student_id || "-"}</TableCell>
                            <TableCell className="font-medium">{student.name}</TableCell>
                            <TableCell className="text-muted-foreground">{student.type?.toUpperCase() || "-"}</TableCell>
                            <TableCell className="text-muted-foreground">{student.course || "-"}</TableCell>
                            <TableCell>
                                <Badge variant="outline" className="rounded-md">
                                    {student.status || "-"}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-muted-foreground text-right">{formatDateTime(student.registered_at)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function RecentActivityTable({ activity }: { activity: RecentActivityItem[] }) {
    if (activity.length === 0) {
        return <EmptyPanel label="No recent activity yet." />;
    }

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Actor</TableHead>
                        <TableHead>Action</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Time</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {activity.map((item, index) => (
                        <TableRow key={`${item.actor}-${item.action}-${item.time}-${index}`} className="hover:bg-muted/35">
                            <TableCell className="font-medium">{item.actor}</TableCell>
                            <TableCell className="text-muted-foreground">{item.action}</TableCell>
                            <TableCell>
                                <Badge variant="outline" className="rounded-md">
                                    {item.status}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-muted-foreground text-right">{item.time}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

export default function AdministratorDashboard({ user, admin_data }: AdminDashboardProps) {
    const [trendRange, setTrendRange] = useState<TrendRange>("year");
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const firstName = user.name.split(" ")[0];
    const enrollmentTrends = admin_data.enrollment_health.trends;
    const filteredTrends = useMemo(
        () => (trendRange === "six_months" ? enrollmentTrends.slice(-6) : enrollmentTrends),
        [enrollmentTrends, trendRange],
    );
    const pipelineData = admin_data.enrollment_health.pipeline.map((item) => ({
        label: item.status,
        value: item.count,
        displayValue: formatNumber(item.count),
    }));
    const yearLevelData = admin_data.student_demographics.by_year_level.map((item) => ({
        label: item.year_level,
        count: item.count,
    }));
    const topCourseData = admin_data.student_demographics.top_courses.map((course) => ({
        label: course.code,
        count: course.student_count,
    }));
    const finance = admin_data.finance_snapshot;
    const collectionRate = Math.min(Math.max(finance.collection_rate, 0), 100);
    const conversionRate = Math.min(Math.max(admin_data.enrollment_health.conversion_rate, 0), 100);

    return (
        <AdminLayout user={user} title="Administrator Overview">
            <Head title="Administrators - Overview" />

            <div className={`${adminPanelClass} overflow-hidden`}>
                <div className="border-border/60 flex flex-col gap-5 border-b p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="rounded-md">
                                <CalendarDays className="mr-1.5 h-3.5 w-3.5" />
                                {admin_data.current_period.label}
                            </Badge>
                            <Badge variant="outline" className="rounded-md">
                                Updated {formatDateTime(admin_data.executive_summary.last_updated_at)}
                            </Badge>
                        </div>
                        <h2 className="text-foreground mt-4 text-2xl font-semibold tracking-tight md:text-3xl">Welcome, {firstName}</h2>
                        <p className="text-muted-foreground mt-1 max-w-3xl text-sm">
                            A stakeholder-ready view of enrollment movement, student composition, finance health, and operational work.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="rounded-lg">
                            <Link href={route("administrators.enrollments.index")}>
                                <ClipboardCheck className="mr-2 h-4 w-4" />
                                Enrollments
                            </Link>
                        </Button>
                        <Button asChild className="rounded-lg">
                            <Link href={route("administrators.finance.index")}>
                                <Banknote className="mr-2 h-4 w-4" />
                                Finance
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                    {admin_data.executive_summary.kpis.map((stat, index) => (
                        <DashboardStatCard key={stat.label} index={index} stat={stat} />
                    ))}
                </div>
            </div>

            <Tabs defaultValue="overview" className="grid gap-4">
                <TabsList className="grid h-auto w-full grid-cols-2 rounded-lg lg:w-fit lg:grid-cols-4">
                    <TabsTrigger value="overview" className="rounded-md">
                        <LayoutDashboard className="mr-2 h-4 w-4" />
                        Overview
                    </TabsTrigger>
                    <TabsTrigger value="enrollment" className="rounded-md">
                        <Workflow className="mr-2 h-4 w-4" />
                        Enrollment
                    </TabsTrigger>
                    <TabsTrigger value="students" className="rounded-md">
                        <Users className="mr-2 h-4 w-4" />
                        Students
                    </TabsTrigger>
                    <TabsTrigger value="operations" className="rounded-md">
                        <ShieldCheck className="mr-2 h-4 w-4" />
                        Operations
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="grid gap-4">
                    <div className="grid gap-4 lg:grid-cols-12">
                        <Card className={`${adminPanelClass} lg:col-span-8`}>
                            <CardHeader className="border-border/60 flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <TrendingUp className="text-primary h-4 w-4" />
                                        Enrollment trend
                                    </CardTitle>
                                    <CardDescription>Monthly enrollment volume for the current year</CardDescription>
                                </div>
                                <div className="bg-muted grid grid-cols-2 rounded-lg p-1 text-xs">
                                    <button
                                        type="button"
                                        onClick={() => setTrendRange("year")}
                                        className={`rounded-md px-3 py-1.5 ${trendRange === "year" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground"}`}
                                    >
                                        Year
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTrendRange("six_months")}
                                        className={`rounded-md px-3 py-1.5 ${trendRange === "six_months" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground"}`}
                                    >
                                        6 months
                                    </button>
                                </div>
                            </CardHeader>
                            <CardContent className="p-4 sm:p-6">
                                <EnrollmentTrendChart data={filteredTrends} />
                            </CardContent>
                        </Card>

                        <Card className={`${adminPanelClass} lg:col-span-4`}>
                            <SectionHeading icon={BarChart3} title="Institution health" description="Conversion and collection signal" />
                            <CardContent className="grid gap-5 p-5">
                                <div className="grid place-items-center">
                                    <Gauge
                                        value={conversionRate}
                                        centerValue={conversionRate}
                                        defaultLabel="Conversion"
                                        suffix="%"
                                        inactiveFillOpacity={0.35}
                                        spacing={24}
                                        useGradient
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <MetricTile
                                        icon={UserCheck}
                                        label="Collection"
                                        value={formatPercent(collectionRate)}
                                        description="Assessed tuition collected"
                                    />
                                    <MetricTile
                                        icon={CheckCircle2}
                                        label="Paid"
                                        value={formatNumber(finance.fully_paid_count)}
                                        description="Fully paid students"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-12">
                        <Card className={`${adminPanelClass} lg:col-span-5`}>
                            <SectionHeading icon={Workflow} title="Enrollment pipeline" description="Current-period volume by workflow stage" />
                            <CardContent className="p-5">
                                {hasPositiveData(pipelineData, "value") ? (
                                    <FunnelChart data={pipelineData} color={chartCssVars.linePrimary} />
                                ) : (
                                    <EmptyPanel label="No enrollment pipeline data is available yet." />
                                )}
                            </CardContent>
                        </Card>

                        <Card className={`${adminPanelClass} lg:col-span-7`}>
                            <SectionHeading icon={Banknote} title="Finance snapshot" description="Current period assessment and collection" />
                            <CardContent className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                                <MetricTile
                                    icon={Banknote}
                                    label="Collected"
                                    value={formatCurrency(finance.total_revenue, currency)}
                                    description="Revenue recorded this period"
                                />
                                <MetricTile
                                    icon={AlertTriangle}
                                    label="Outstanding"
                                    value={formatCurrency(finance.total_collectibles, currency)}
                                    description="Remaining balances"
                                />
                                <MetricTile
                                    icon={School}
                                    label="Assessed"
                                    value={formatCurrency(finance.total_assessed, currency)}
                                    description="Total tuition assessed"
                                />
                                <MetricTile
                                    icon={Activity}
                                    label="Today"
                                    value={formatCurrency(finance.today_collection, currency)}
                                    description={`${formatNumber(finance.today_transactions)} transactions`}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="enrollment" className="grid gap-4">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricTile
                            icon={ClipboardCheck}
                            label="Applicants"
                            value={formatNumber(admin_data.enrollment_health.applicants)}
                            description="Students still marked applicant"
                        />
                        <MetricTile
                            icon={GraduationCap}
                            label="Enrolled"
                            value={formatNumber(admin_data.enrollment_health.enrolled)}
                            description="Student profiles marked enrolled"
                        />
                        <MetricTile
                            icon={Workflow}
                            label="Pending"
                            value={formatNumber(admin_data.enrollment_health.pending)}
                            description="Current-period records in review"
                        />
                        <MetricTile
                            icon={PieChart}
                            label="On leave"
                            value={formatNumber(admin_data.enrollment_health.on_leave)}
                            description="Students currently on leave"
                        />
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card className={adminPanelClass}>
                            <SectionHeading icon={TrendingUp} title="Enrollment movement" description="Trend filtered by the selected range" />
                            <CardContent className="p-5">
                                <EnrollmentTrendChart data={filteredTrends} />
                            </CardContent>
                        </Card>
                        <Card className={adminPanelClass}>
                            <SectionHeading icon={Workflow} title="Pipeline stages" description="Where enrollment work currently sits" />
                            <CardContent className="p-5">
                                {hasPositiveData(pipelineData, "value") ? (
                                    <FunnelChart data={pipelineData} color={chartCssVars.linePrimary} orientation="vertical" />
                                ) : (
                                    <EmptyPanel label="No enrollment pipeline data is available yet." />
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="students" className="grid gap-4">
                    <div className="grid gap-4 lg:grid-cols-12">
                        <Card className={`${adminPanelClass} lg:col-span-7`}>
                            <SectionHeading icon={Users} title="Student mix" description="Distribution by student type" />
                            <CardContent className="p-5">
                                <StudentMixChart items={admin_data.student_demographics.by_type} total={admin_data.student_demographics.total} />
                            </CardContent>
                        </Card>
                        <Card className={`${adminPanelClass} lg:col-span-5`}>
                            <SectionHeading icon={GraduationCap} title="Year levels" description="Students by academic year" />
                            <CardContent className="p-5">
                                <BarComparisonChart data={yearLevelData} labelKey="label" dataKey="count" />
                            </CardContent>
                        </Card>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-12">
                        <Card className={`${adminPanelClass} lg:col-span-5`}>
                            <SectionHeading icon={PieChart} title="Gender distribution" description="Student profile distribution" />
                            <CardContent className="grid gap-3 p-5">
                                {admin_data.student_demographics.by_gender.map((item, index) => (
                                    <div key={item.gender} className="grid grid-cols-[1fr_auto] items-center gap-3">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: chartPalette[index % chartPalette.length] }} />
                                                <p className="text-sm font-medium">{item.gender}</p>
                                            </div>
                                            <div className="bg-muted mt-2 h-2 rounded-full">
                                                <div
                                                    className="h-2 rounded-full"
                                                    style={{
                                                        width: `${admin_data.student_demographics.total > 0 ? Math.round((item.count / admin_data.student_demographics.total) * 100) : 0}%`,
                                                        backgroundColor: chartPalette[index % chartPalette.length],
                                                    }}
                                                />
                                            </div>
                                        </div>
                                        <span className="text-muted-foreground text-sm tabular-nums">{formatNumber(item.count)}</span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        <Card className={`${adminPanelClass} lg:col-span-7`}>
                            <SectionHeading icon={School} title="Top courses" description="Largest student populations by course" />
                            <CardContent className="p-5">
                                <HorizontalBarChart data={topCourseData} labelKey="label" dataKey="count" />
                            </CardContent>
                        </Card>
                    </div>
                    <Card className={adminPanelClass}>
                        <SectionHeading icon={Users} title="Recent registrations" description="Latest student profiles created in the system" />
                        <CardContent className="p-0">
                            <RecentStudentsTable students={admin_data.recent_records.students} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="operations" className="grid gap-4">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricTile
                            icon={School}
                            label="Active classes"
                            value={formatNumber(admin_data.operations.active_classes)}
                            description="Classes in the current academic period"
                        />
                        <MetricTile
                            icon={GraduationCap}
                            label="Faculty"
                            value={formatNumber(admin_data.operations.total_faculty)}
                            description="Faculty profiles in the system"
                        />
                        <MetricTile
                            icon={Users}
                            label="Portal users"
                            value={formatNumber(admin_data.operations.total_users)}
                            description="Accounts with portal access"
                        />
                        <MetricTile
                            icon={AlertTriangle}
                            label="Unassigned"
                            value={formatNumber(admin_data.operations.unassigned_classes)}
                            description="Classes without assigned faculty"
                        />
                    </div>
                    <div className="grid gap-4 lg:grid-cols-12">
                        <Card className={`${adminPanelClass} lg:col-span-7`}>
                            <SectionHeading icon={ListChecks} title="Operational queue" description="Work that needs attention" />
                            <CardContent className="p-4">
                                <ActionQueue items={admin_data.operations.action_queue} />
                            </CardContent>
                        </Card>
                        <Card className={`${adminPanelClass} lg:col-span-5`}>
                            <SectionHeading icon={Info} title="Admin guidance" description="Simple hints for new administrators" />
                            <CardContent className="grid gap-3 p-4">
                                {admin_data.beginner_tips.map((tip) => (
                                    <div key={tip.title} className="border-border/60 bg-background/40 rounded-lg border p-4">
                                        <div className="flex gap-3">
                                            <Info className="text-primary mt-0.5 h-4 w-4 shrink-0" />
                                            <div>
                                                <p className="text-foreground font-medium">{tip.title}</p>
                                                <p className="text-muted-foreground mt-1 text-sm">{tip.content}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                    <Card className={adminPanelClass}>
                        <SectionHeading icon={Activity} title="Recent activity" description="Recent system and staff actions" />
                        <CardContent className="p-0">
                            <RecentActivityTable activity={admin_data.recent_records.activity} />
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <div className="grid gap-4 lg:grid-cols-12">
                <Card className={`${adminPanelClass} lg:col-span-7`}>
                    <SectionHeading icon={ArrowUpRight} title="Quick links" description="Shortcuts for common administrator tasks" />
                    <CardContent className="grid gap-3 p-4">
                        {admin_data.quick_actions.map((action) => (
                            <div
                                key={action.title}
                                className="border-border/60 hover:bg-muted/25 flex flex-col gap-3 rounded-lg border p-4 transition-colors sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-foreground font-medium">{action.title}</p>
                                        {action.disabled && (
                                            <Badge variant="outline" className="text-muted-foreground rounded-md">
                                                Coming soon
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-sm">{action.description}</p>
                                </div>
                                {action.disabled ? (
                                    <Button variant="secondary" disabled title={action.disabledTooltip} className="rounded-lg">
                                        Unavailable
                                    </Button>
                                ) : (
                                    <Button asChild variant="outline" className="rounded-lg">
                                        <Link href={action.href}>
                                            Open
                                            <ArrowUpRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card className={`${adminPanelClass} lg:col-span-5`}>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <AlertTriangle className="text-muted-foreground h-4 w-4" />
                            Filament admin panel
                        </CardTitle>
                        <CardDescription>
                            Filament is served on the separate admin subdomain. This portal view focuses on administrator workflows inside the portal URL.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground text-sm">
                            Open Filament by visiting the admin domain configured by your system administrator.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
