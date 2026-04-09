import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartContainer, ChartLegend, ChartLegendContent, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@/components/ui/chart";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
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

function toneBadgeClass(tone: AdminStatTone): string {
    if (tone === "success") {
        return "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200";
    }

    if (tone === "warning") {
        return "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200";
    }

    if (tone === "info") {
        return "border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-900/20 dark:text-sky-200";
    }

    return "border-border bg-background text-muted-foreground";
}

const enrollmentTrendConfig = {
    enrollments: {
        label: "Enrollments",
        color: "hsl(217.2 91.2% 59.8%)", // blue-500
    },
} satisfies ChartConfig;

const studentCountConfig = {
    count: {
        label: "Students",
        color: "hsl(142.1 76.2% 36.3%)", // green-600
    },
} satisfies ChartConfig;

const enrollmentCountConfig = {
    count: {
        label: "Enrollments",
        color: "hsl(45.4 93.4% 47.5%)", // amber-500
    },
} satisfies ChartConfig;

export default function AdministratorDashboard({ user, admin_data }: AdminDashboardProps) {
    return (
        <AdminLayout user={user} title="Administrator Overview">
            <Head title="Administrators • Overview" />

            <div className="flex flex-col gap-2">
                <h2 className="text-foreground text-2xl font-semibold tracking-tight">Welcome, {user.name.split(" ")[0]}</h2>
                <p className="text-muted-foreground">Start here to review what needs attention today.</p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {admin_data.stats.map((stat) => (
                    <Card key={stat.label} className="shadow-sm">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="text-muted-foreground text-sm font-medium">{stat.label}</CardTitle>
                                <Badge variant="outline" className={toneBadgeClass(stat.tone)}>
                                    {stat.tone}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-foreground text-3xl font-semibold tracking-tight">{stat.value}</div>
                            <p className="text-muted-foreground mt-1 text-sm">{stat.description}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-7">
                    <CardHeader>
                        <CardTitle>Enrollment trends</CardTitle>
                        <CardDescription>
                            Monthly enrollments for the current year • Updated {new Date(admin_data.analytics.last_updated_at).toLocaleString()}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={enrollmentTrendConfig} className="aspect-auto h-[250px] w-full">
                            <AreaChart data={admin_data.analytics.enrollment_trends}>
                                <defs>
                                    <linearGradient id="fillEnrollments" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="var(--color-enrollments)" stopOpacity={0.8} />
                                        <stop offset="95%" stopColor="var(--color-enrollments)" stopOpacity={0.1} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid vertical={false} />
                                <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} />
                                <YAxis tickLine={false} axisLine={false} tickMargin={8} width={40} />
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
                    </CardContent>
                </Card>

                <Card className="lg:col-span-5">
                    <CardHeader>
                        <CardTitle>Enrollment workflow</CardTitle>
                        <CardDescription>Distribution for the current term</CardDescription>
                    </CardHeader>
                    <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={enrollmentCountConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart data={admin_data.analytics.enrollment_status} layout="vertical">
                                <CartesianGrid horizontal={false} />
                                <XAxis type="number" tickLine={false} axisLine={false} />
                                <YAxis dataKey="status" type="category" tickLine={false} axisLine={false} width={140} />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
                                <Bar dataKey="count" fill="var(--color-count)" radius={6} />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-6">
                    <CardHeader>
                        <CardTitle>Student types</CardTitle>
                        <CardDescription>Distribution of students by type</CardDescription>
                    </CardHeader>
                    <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={studentCountConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart
                                data={admin_data.analytics.student_types.map((item) => ({
                                    label: item.type.toUpperCase(),
                                    count: item.count,
                                }))}
                            >
                                <CartesianGrid vertical={false} />
                                <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                                <YAxis tickLine={false} axisLine={false} tickMargin={8} width={40} />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="dot" />} />
                                <Bar dataKey="count" fill="var(--color-count)" radius={6} />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                <Card className="lg:col-span-6">
                    <CardHeader>
                        <CardTitle>Top courses</CardTitle>
                        <CardDescription>Largest student populations by course</CardDescription>
                    </CardHeader>
                    <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                        <ChartContainer config={studentCountConfig} className="aspect-auto h-[250px] w-full">
                            <BarChart
                                data={admin_data.analytics.top_courses.map((course) => ({
                                    label: course.code,
                                    count: course.student_count,
                                }))}
                            >
                                <CartesianGrid vertical={false} />
                                <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                                <YAxis tickLine={false} axisLine={false} tickMargin={8} width={40} />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="dot" />} />
                                <Bar dataKey="count" fill="var(--color-count)" radius={6} />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent student registrations</CardTitle>
                    <CardDescription>Latest student profiles created in the system</CardDescription>
                </CardHeader>
                <CardContent>
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
                                <TableRow key={student.id}>
                                    <TableCell className="text-muted-foreground font-mono text-xs">{student.student_id || "—"}</TableCell>
                                    <TableCell className="font-medium">{student.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{student.type?.toUpperCase() || "—"}</TableCell>
                                    <TableCell className="text-muted-foreground">{student.course || "—"}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{student.status || "—"}</Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-right">
                                        {new Date(student.registered_at).toLocaleString()}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-7">
                    <CardHeader>
                        <CardTitle>Quick actions</CardTitle>
                        <CardDescription>Shortcuts for common admin tasks (more tools coming soon).</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {admin_data.quick_actions.map((action) => (
                            <div
                                key={action.title}
                                className="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="text-foreground font-medium">{action.title}</p>
                                        {action.disabled && (
                                            <Badge variant="outline" className="text-muted-foreground">
                                                Coming soon
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground text-sm">{action.description}</p>
                                </div>

                                {action.disabled ? (
                                    <Button variant="secondary" disabled title={action.disabledTooltip}>
                                        Unavailable
                                    </Button>
                                ) : (
                                    <Button asChild>
                                        <Link href={action.href}>Open</Link>
                                    </Button>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-5">
                    <CardHeader>
                        <CardTitle>Beginner tips</CardTitle>
                        <CardDescription>Simple guidance for new admins.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {admin_data.beginner_tips.map((tip) => (
                            <div key={tip.title} className="rounded-lg border p-4">
                                <p className="text-foreground font-medium">{tip.title}</p>
                                <p className="text-muted-foreground mt-1 text-sm">{tip.content}</p>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent activity</CardTitle>
                    <CardDescription>A quick log of recent system and staff actions.</CardDescription>
                </CardHeader>
                <CardContent>
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
                                <TableRow key={`${item.actor}-${item.action}-${item.time}-${index}`}>
                                    <TableCell className="font-medium">{item.actor}</TableCell>
                                    <TableCell className="text-muted-foreground">{item.action}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{item.status}</Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-right">{item.time}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card className="border-dashed">
                <CardHeader>
                    <CardTitle>Where is the Filament admin panel?</CardTitle>
                    <CardDescription>
                        Filament is served on the separate admin subdomain. This portal section is only for administrator pages inside the PORTAL URL.
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
