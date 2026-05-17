import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@/components/ui/chart";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { AlertTriangle, ArrowUpRight, BarChart3, ClipboardCheck, GraduationCap, Info, LineChart, ListChecks, Users, Workflow } from "lucide-react";
import { Area, AreaChart, Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts";

type AdminStatTone = "success" | "warning" | "info" | "neutral";

type AdminStat = {
    label: string;
    value: number | string;
    description: string;
    tone: AdminStatTone;
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
    status: "success" | "info" | "warning" | "error";
};

type BeginnerTip = {
    title: string;
    content: string;
};

type AdminAnalytics = {
    last_updated_at: string;
    enrollment_trends: { month: string; enrollments: number }[];
    enrollment_status: { status: string; count: number }[];
    application_vs_enrollment: {
        applicants: number;
        enrolled: number;
        on_leave: number;
        conversion_rate: number;
    };
    student_types: { type: string; label: string; count: number; percentage: number }[];
    gender_distribution: { gender: string; count: number }[];
    year_level_distribution: { year_level: string; count: number }[];
    top_courses: { code: string; title: string; student_count: number }[];
    recent_students: {
        id: number;
        student_id: string | null;
        name: string;
        type: string | null;
        status: string | null;
        course: string | null;
        registered_at: string;
    }[];
};

interface AdminDashboardProps {
    user: User;
    admin_data: {
        stats: AdminStat[];
        quick_actions: QuickAction[];
        recent_activity: RecentActivityItem[];
        beginner_tips: BeginnerTip[];
        analytics: AdminAnalytics;
    };
}

const adminCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/25 hover:bg-card hover:shadow-md";
const adminPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

const enrollmentTrendConfig = {
    enrollments: {
        label: "Enrollments",
        color: "hsl(217.2 91.2% 59.8%)",
    },
} satisfies ChartConfig;

const studentCountConfig = {
    count: {
        label: "Students",
        color: "hsl(142.1 76.2% 36.3%)",
    },
} satisfies ChartConfig;

const enrollmentCountConfig = {
    count: {
        label: "Enrollments",
        color: "hsl(45.4 93.4% 47.5%)",
    },
} satisfies ChartConfig;

const statIcons = {
    "Pending Enrollments": ClipboardCheck,
    "Enrolled This Period": GraduationCap,
    "Total Students": Users,
    "Conversion Rate": BarChart3,
} as const;

function toneBadgeClass(tone: AdminStatTone): string {
    if (tone === "success") {
        return "border-emerald-500/30 bg-emerald-500/10 text-emerald-500";
    }

    if (tone === "warning") {
        return "border-amber-500/30 bg-amber-500/10 text-amber-500";
    }

    if (tone === "info") {
        return "border-sky-500/30 bg-sky-500/10 text-sky-500";
    }

    return "border-border bg-muted/40 text-muted-foreground";
}

function toneAccentClass(tone: AdminStatTone): string {
    if (tone === "success") {
        return "bg-emerald-500";
    }

    if (tone === "warning") {
        return "bg-amber-500";
    }

    if (tone === "info") {
        return "bg-sky-500";
    }

    return "bg-muted-foreground";
}

function toneIconClass(tone: AdminStatTone): string {
    if (tone === "success") {
        return "text-emerald-500 bg-emerald-500/10";
    }

    if (tone === "warning") {
        return "text-amber-500 bg-amber-500/10";
    }

    if (tone === "info") {
        return "text-sky-500 bg-sky-500/10";
    }

    return "text-muted-foreground bg-muted/60";
}

function chartHasData<T extends Record<string, unknown>>(data: T[], key: keyof T): boolean {
    return data.some((item) => Number(item[key] ?? 0) > 0);
}

function EmptyChartState({ title, description }: { title: string; description: string }) {
    return (
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center px-6">
            <div className="bg-background/75 border-border/60 rounded-lg border px-4 py-3 text-center shadow-sm backdrop-blur">
                <BarChart3 className="text-muted-foreground mx-auto mb-2 h-5 w-5" />
                <p className="text-foreground text-sm font-medium">{title}</p>
                <p className="text-muted-foreground mt-1 text-xs">{description}</p>
            </div>
        </div>
    );
}

function EmptyTableState({ label }: { label: string }) {
    return (
        <div className="text-muted-foreground flex min-h-32 flex-col items-center justify-center rounded-lg border border-dashed p-6 text-center">
            <ListChecks className="mb-2 h-6 w-6 opacity-40" />
            <p className="text-sm">{label}</p>
        </div>
    );
}

export default function AdministratorDashboard({ user, admin_data }: AdminDashboardProps) {
    const firstName = user.name.split(" ")[0];
    const hasEnrollmentTrendData = chartHasData(admin_data.analytics.enrollment_trends, "enrollments");
    const hasWorkflowData = chartHasData(admin_data.analytics.enrollment_status, "count");
    const hasStudentTypeData = chartHasData(admin_data.analytics.student_types, "count");
    const hasTopCourseData = chartHasData(admin_data.analytics.top_courses, "student_count");

    return (
        <AdminLayout user={user} title="Administrator Overview">
            <Head title="Administrators - Overview" />

            <div className={`${adminPanelClass} flex flex-col justify-between gap-4 p-4 md:flex-row md:items-end md:p-5`}>
                <div className="min-w-0">
                    <h2 className="text-foreground mt-2 text-2xl font-semibold tracking-tight md:text-3xl">Welcome, {firstName}</h2>
                    <p className="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Here's a summary of what's happening in your institution.
                    </p>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {admin_data.stats.map((stat) => {
                    const Icon = statIcons[stat.label as keyof typeof statIcons] ?? BarChart3;

                    return (
                        <Card key={stat.label} className={`${adminCardClass} group relative overflow-hidden hover:-translate-y-0.5`}>
                            <Icon className="text-primary pointer-events-none absolute top-5 right-5 h-16 w-16 opacity-10 transition-all duration-200 group-hover:scale-105 group-hover:opacity-20" />
                            <div className={`absolute inset-y-0 left-0 w-1 ${toneAccentClass(stat.tone)}`} />
                            <CardContent className="relative p-5 pr-20">
                                <div className={`mb-5 inline-flex rounded-lg p-2.5 ${toneIconClass(stat.tone)}`}>
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{stat.label}</p>
                                        <div className="text-foreground mt-3 text-3xl font-semibold tracking-tight">{stat.value}</div>
                                    </div>
                                    <Badge variant="outline" className={`${toneBadgeClass(stat.tone)} shrink-0 rounded-md text-[10px]`}>
                                        {stat.tone}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-3 text-sm">{stat.description}</p>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            <div className="grid gap-4 lg:grid-cols-12">
                <Card className={`${adminPanelClass} lg:col-span-7`}>
                    <CardHeader className="border-border/60 border-b pb-4">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <LineChart className="text-primary h-4 w-4" />
                            Enrollment trends
                        </CardTitle>
                        <CardDescription>Monthly enrollments for the current year</CardDescription>
                    </CardHeader>
                    <CardContent className="relative px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={enrollmentTrendConfig} className="aspect-auto h-[250px] w-full">
                            <AreaChart data={admin_data.analytics.enrollment_trends}>
                                <defs>
                                    <linearGradient id="fillEnrollments" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="var(--color-enrollments)" stopOpacity={0.45} />
                                        <stop offset="95%" stopColor="var(--color-enrollments)" stopOpacity={0.08} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid vertical={false} strokeDasharray="4 4" className="stroke-border/70" />
                                <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} className="text-muted-foreground" />
                                <YAxis tickLine={false} axisLine={false} tickMargin={8} width={40} className="text-muted-foreground" />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="dot" />} />
                                <ChartLegend content={<ChartLegendContent />} />
                                <Area
                                    dataKey="enrollments"
                                    type="monotone"
                                    fill="url(#fillEnrollments)"
                                    stroke="var(--color-enrollments)"
                                    strokeWidth={2}
                                />
                            </AreaChart>
                        </ChartContainer>
                        {!hasEnrollmentTrendData && (
                            <EmptyChartState title="No enrollment trend yet" description="Monthly movement appears once records are available." />
                        )}
                    </CardContent>
                </Card>

                <Card className={`${adminPanelClass} lg:col-span-5`}>
                    <CardHeader className="border-border/60 border-b pb-4">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Workflow className="text-primary h-4 w-4" />
                            Enrollment workflow
                        </CardTitle>
                        <CardDescription>Distribution for the current term</CardDescription>
                    </CardHeader>
                    <CardContent className="relative px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={enrollmentCountConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart data={admin_data.analytics.enrollment_status} layout="vertical">
                                <CartesianGrid horizontal={false} strokeDasharray="4 4" className="stroke-border/70" />
                                <XAxis type="number" tickLine={false} axisLine={false} className="text-muted-foreground" />
                                <YAxis
                                    dataKey="status"
                                    type="category"
                                    tickLine={false}
                                    axisLine={false}
                                    width={140}
                                    className="text-muted-foreground"
                                />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
                                <Bar dataKey="count" fill="var(--color-count)" radius={6} />
                            </BarChart>
                        </ChartContainer>
                        {!hasWorkflowData && (
                            <EmptyChartState
                                title="No workflow volume"
                                description="Pending, verification, and enrolled counts are currently empty."
                            />
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-12">
                <Card className={`${adminPanelClass} lg:col-span-6`}>
                    <CardHeader className="border-border/60 border-b pb-4">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Users className="text-primary h-4 w-4" />
                            Student types
                        </CardTitle>
                        <CardDescription>Distribution of students by type</CardDescription>
                    </CardHeader>
                    <CardContent className="relative px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={studentCountConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart
                                data={admin_data.analytics.student_types.map((item) => ({
                                    label: item.type.toUpperCase(),
                                    count: item.count,
                                }))}
                            >
                                <CartesianGrid vertical={false} strokeDasharray="4 4" className="stroke-border/70" />
                                <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} className="text-muted-foreground" />
                                <YAxis tickLine={false} axisLine={false} tickMargin={8} width={40} className="text-muted-foreground" />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="dot" />} />
                                <Bar dataKey="count" fill="var(--color-count)" radius={6} />
                            </BarChart>
                        </ChartContainer>
                        {!hasStudentTypeData && (
                            <EmptyChartState title="No student type data" description="Distribution appears after student profiles are added." />
                        )}
                    </CardContent>
                </Card>

                <Card className={`${adminPanelClass} lg:col-span-6`}>
                    <CardHeader className="border-border/60 border-b pb-4">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <GraduationCap className="text-primary h-4 w-4" />
                            Top courses
                        </CardTitle>
                        <CardDescription>Largest student populations by course</CardDescription>
                    </CardHeader>
                    <CardContent className="relative px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={studentCountConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart
                                data={admin_data.analytics.top_courses.map((course) => ({
                                    label: course.code,
                                    count: course.student_count,
                                }))}
                            >
                                <CartesianGrid vertical={false} strokeDasharray="4 4" className="stroke-border/70" />
                                <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} className="text-muted-foreground" />
                                <YAxis tickLine={false} axisLine={false} tickMargin={8} width={40} className="text-muted-foreground" />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="dot" />} />
                                <Bar dataKey="count" fill="var(--color-count)" radius={6} />
                            </BarChart>
                        </ChartContainer>
                        {!hasTopCourseData && (
                            <EmptyChartState title="No course volume yet" description="Top courses appear after enrollments exist." />
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className={adminPanelClass}>
                <CardHeader className="border-border/60 border-b pb-4">
                    <CardTitle className="text-base">Recent student registrations</CardTitle>
                    <CardDescription>Latest student profiles created in the system</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {admin_data.analytics.recent_students.length > 0 ? (
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
                                    {admin_data.analytics.recent_students.map((student) => (
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
                                            <TableCell className="text-muted-foreground text-right">
                                                {new Date(student.registered_at).toLocaleString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="p-4">
                            <EmptyTableState label="No student registrations yet." />
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-12">
                <Card className={`${adminPanelClass} lg:col-span-7`}>
                    <CardHeader className="border-border/60 border-b pb-4">
                        <CardTitle className="text-base">Quick actions</CardTitle>
                        <CardDescription>Shortcuts for common admin tasks.</CardDescription>
                    </CardHeader>
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
                                    <Button asChild className="rounded-lg">
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
                    <CardHeader className="border-border/60 border-b pb-4">
                        <CardTitle className="text-base">Beginner tips</CardTitle>
                        <CardDescription>Simple guidance for new admins.</CardDescription>
                    </CardHeader>
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
                <CardHeader className="border-border/60 border-b pb-4">
                    <CardTitle className="text-base">Recent activity</CardTitle>
                    <CardDescription>A quick log of recent system and staff actions.</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    {admin_data.recent_activity.length > 0 ? (
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
                                    {admin_data.recent_activity.map((item, index) => (
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
                    ) : (
                        <div className="p-4">
                            <EmptyTableState label="No recent activity yet." />
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card className="border-border/60 bg-muted/20 rounded-lg border-dashed">
                <CardHeader className="pb-3">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <AlertTriangle className="text-muted-foreground h-4 w-4" />
                        Filament admin panel
                    </CardTitle>
                    <CardDescription>
                        Filament is served on the separate admin subdomain. This portal section is only for administrator pages inside the portal URL.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-muted-foreground text-sm">
                        Open Filament by visiting the admin domain configured by your system administrator.
                    </p>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
