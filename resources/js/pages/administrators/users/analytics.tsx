import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    BarXAxis,
    BarYAxis,
    ChartTooltip,
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
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { Activity, Building2, CheckCircle2, Clock3, Radio, ShieldCheck, Sparkles, UserRoundCheck, Users, Zap, type LucideIcon } from "lucide-react";
import { useEffect, useState } from "react";

export interface AnalyticsData {
    total_users: number;
    all_time_users: number;
    trashed_users: number;
    new_users_today: number;
    new_users_30_days: number;
    previous_30_days_users: number;
    growth_rate: number;
    verified_users: number;
    unverified_users: number;
    verification_rate: number;
    online_users: number;
    online_rate: number;
    two_factor_enabled_users: number;
    two_factor_rate: number;
    assigned_users: number;
    assignment_rate: number;
    top_active_users: ActiveUser[];
    registrations_chart: RegistrationPoint[];
    role_distribution: DistributionPoint[];
    school_distribution: OrganizationPoint[];
    department_distribution: OrganizationPoint[];
    recent_users: RecentUser[];
    last_updated_at: string;
}

interface ActiveUser {
    id: string;
    name: string;
    email: string;
    requests: number;
    avatar?: string | null;
}

interface RegistrationPoint {
    date: string;
    label: string;
    count: number;
    cumulative: number;
}

interface DistributionPoint {
    role: string;
    label: string;
    count: number;
    percentage: number;
}

interface OrganizationPoint {
    id: number | null;
    name: string;
    count: number;
    percentage: number;
}

interface RecentUser {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    avatar?: string | null;
    verified: boolean;
    created_at: string | null;
}

const chartPalette = ["var(--chart-1)", "var(--chart-2)", "var(--chart-3)", "var(--chart-4)", "var(--chart-5)"] as const;

function formatNumber(value: number): string {
    return new Intl.NumberFormat("en-US").format(value);
}

function formatPercent(value: number): string {
    return `${new Intl.NumberFormat("en-US", { maximumFractionDigits: 1 }).format(value)}%`;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return "Unknown";
    }

    return new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(new Date(value));
}

function hasPositiveData<T extends Record<string, unknown>>(items: T[], key: keyof T): boolean {
    return items.some((item) => Number(item[key] ?? 0) > 0);
}

function updateRegistrationChart(chart: RegistrationPoint[]): RegistrationPoint[] {
    const today = new Date().toISOString().slice(0, 10);
    const nextChart = [...chart];
    const todayIndex = nextChart.findIndex((item) => item.date === today);

    if (todayIndex >= 0) {
        for (let index = todayIndex; index < nextChart.length; index += 1) {
            nextChart[index] = {
                ...nextChart[index],
                count: index === todayIndex ? nextChart[index].count + 1 : nextChart[index].count,
                cumulative: nextChart[index].cumulative + 1,
            };
        }

        return nextChart;
    }

    const cumulative = (nextChart.at(-1)?.cumulative ?? 0) + 1;

    return [
        ...nextChart.slice(-29),
        {
            date: today,
            label: new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric" }).format(new Date()),
            count: 1,
            cumulative,
        },
    ];
}

function EmptyPanel({ label }: { label: string }) {
    return (
        <div className="text-muted-foreground flex min-h-36 flex-col items-center justify-center rounded-lg border border-dashed p-6 text-center">
            <Activity className="mb-2 size-6 opacity-40" />
            <p className="text-sm">{label}</p>
        </div>
    );
}

function MetricCard({
    title,
    value,
    description,
    icon: Icon,
    tone,
    progress,
    detail,
}: {
    title: string;
    value: string;
    description: string;
    icon: LucideIcon;
    tone: string;
    progress?: number;
    detail?: string;
}) {
    return (
        <Card className="border-border/60 bg-card/80 overflow-hidden">
            <CardContent className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{title}</p>
                        <p className="text-foreground mt-2 text-2xl font-semibold tracking-tight">{value}</p>
                    </div>
                    <div className={cn("rounded-lg p-2", tone)}>
                        <Icon className="size-4" />
                    </div>
                </div>
                <p className="text-muted-foreground mt-2 min-h-9 text-sm leading-5">{description}</p>
                {progress !== undefined ? (
                    <div className="mt-4 space-y-2">
                        <Progress value={Math.max(0, Math.min(progress, 100))} className="h-2" />
                        {detail ? <p className="text-muted-foreground text-xs tabular-nums">{detail}</p> : null}
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

function RegistrationChart({ data }: { data: RegistrationPoint[] }) {
    if (!hasPositiveData(data, "cumulative")) {
        return <EmptyPanel label="Registration data will appear once users are created." />;
    }

    return (
        <AreaChart data={data} xDataKey="date" className="h-[310px] w-full" aspectRatio="16 / 7" margin={{ left: 36, right: 24, top: 26, bottom: 38 }}>
            <Grid horizontal />
            <Area dataKey="cumulative" fill={chartCssVars.lineSecondary} fillOpacity={0.18} stroke={chartCssVars.lineSecondary} strokeWidth={2} />
            <Area dataKey="count" fill={chartCssVars.linePrimary} fillOpacity={0.36} stroke={chartCssVars.linePrimary} strokeWidth={2} showMarkers />
            <XAxis tickMode="data" />
            <ChartTooltip
                showDatePill
                rows={(point) => [
                    {
                        color: chartCssVars.linePrimary,
                        label: "New users",
                        value: formatNumber(Number(point.count ?? 0)),
                    },
                    {
                        color: chartCssVars.lineSecondary,
                        label: "Running total",
                        value: formatNumber(Number(point.cumulative ?? 0)),
                    },
                ]}
            />
        </AreaChart>
    );
}

function RoleChart({ data }: { data: DistributionPoint[] }) {
    const topRoles = data.slice(0, 8);

    if (!hasPositiveData(topRoles, "count")) {
        return <EmptyPanel label="No role distribution data is available yet." />;
    }

    return (
        <BarChart
            data={topRoles}
            xDataKey="label"
            orientation="horizontal"
            className="h-[300px] w-full"
            aspectRatio="16 / 8"
            margin={{ left: 130, right: 22, top: 20, bottom: 24 }}
            revealSignature={`roles-${topRoles.map((item) => `${item.role}:${item.count}`).join("|")}`}
        >
            <Grid vertical />
            <Bar dataKey="count" fill={chartCssVars.linePrimary} minBarHeight={3} />
            <BarYAxis />
            <ChartTooltip
                showDatePill={false}
                rows={(point) => [
                    {
                        color: chartCssVars.linePrimary,
                        label: "Users",
                        value: `${formatNumber(Number(point.count ?? 0))} / ${formatPercent(Number(point.percentage ?? 0))}`,
                    },
                ]}
            />
        </BarChart>
    );
}

function StatusRing({ data }: { data: AnalyticsData }) {
    const ringData: LegendItemData[] = [
        {
            label: "Verified",
            value: data.verified_users,
            maxValue: Math.max(data.total_users, 1),
            color: chartPalette[1],
        },
        {
            label: "Unverified",
            value: data.unverified_users,
            maxValue: Math.max(data.total_users, 1),
            color: chartPalette[3],
        },
        {
            label: "2FA enabled",
            value: data.two_factor_enabled_users,
            maxValue: Math.max(data.total_users, 1),
            color: chartPalette[2],
        },
    ];

    if (data.total_users === 0) {
        return <EmptyPanel label="Identity coverage data will appear once users are created." />;
    }

    return (
        <div className="grid gap-5 lg:grid-cols-[minmax(210px,260px)_1fr] lg:items-center">
            <div className="mx-auto w-full max-w-[260px]">
                <RingChart data={ringData} size={250}>
                    {ringData.map((item, index) => (
                        <Ring key={item.label} index={index} />
                    ))}
                    <RingCenter defaultLabel="Users" formatValue={formatNumber} />
                </RingChart>
            </div>
            <Legend items={ringData} className="grid gap-3">
                <LegendItem className="grid grid-cols-[auto_1fr_auto] items-center gap-x-3 gap-y-1">
                    <LegendMarker />
                    <LegendLabel />
                    <LegendValue showPercentage formatValue={formatNumber} />
                    <div className="col-span-full">
                        <LegendProgress />
                    </div>
                </LegendItem>
            </Legend>
        </div>
    );
}

function OrganizationList({ title, items }: { title: string; items: OrganizationPoint[] }) {
    return (
        <div className="space-y-3">
            <p className="text-sm font-medium">{title}</p>
            {items.length > 0 ? (
                items.map((item, index) => (
                    <div key={`${item.id ?? "unassigned"}-${item.name}`} className="space-y-2">
                        <div className="flex items-center justify-between gap-3 text-sm">
                            <div className="flex min-w-0 items-center gap-2">
                                <span className="size-2 rounded-full" style={{ backgroundColor: chartPalette[index % chartPalette.length] }} />
                                <span className="truncate">{item.name}</span>
                            </div>
                            <span className="text-muted-foreground tabular-nums">{formatNumber(item.count)}</span>
                        </div>
                        <Progress value={item.percentage} className="h-2" />
                    </div>
                ))
            ) : (
                <EmptyPanel label="No organization distribution data is available yet." />
            )}
        </div>
    );
}

function TopActiveUsers({ users }: { users: ActiveUser[] }) {
    if (users.length === 0) {
        return <EmptyPanel label="No Pulse user-request activity has been recorded in the last hour." />;
    }

    return (
        <div className="space-y-4">
            {users.map((user, index) => (
                <div key={user.id} className="flex items-center justify-between gap-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="relative">
                            <Avatar className="size-10 border">
                                <AvatarImage src={user.avatar || undefined} alt={user.name} />
                                <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                            </Avatar>
                            <span className="bg-background text-muted-foreground ring-border absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full text-[10px] font-semibold ring-1">
                                {index + 1}
                            </span>
                        </div>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">{user.name}</p>
                            <p className="text-muted-foreground truncate text-xs">{user.email || "No email recorded"}</p>
                        </div>
                    </div>
                    <Badge variant={index === 0 ? "default" : "secondary"} className="rounded-md font-mono text-xs">
                        {formatNumber(user.requests)}
                    </Badge>
                </div>
            ))}
        </div>
    );
}

function RecentUsers({ users }: { users: RecentUser[] }) {
    if (users.length === 0) {
        return <EmptyPanel label="Recently created users will appear here." />;
    }

    return (
        <div className="space-y-4">
            {users.map((user) => (
                <div key={user.id} className="flex items-start justify-between gap-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <Avatar className="size-10 border">
                            <AvatarImage src={user.avatar || undefined} alt={user.name} />
                            <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">{user.name}</p>
                            <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                <Badge variant="outline" className="rounded-md">
                                    {user.role_label}
                                </Badge>
                                {user.verified ? (
                                    <Badge variant="secondary" className="rounded-md">
                                        Verified
                                    </Badge>
                                ) : null}
                            </div>
                        </div>
                    </div>
                    <p className="text-muted-foreground shrink-0 text-right text-xs">{formatDateTime(user.created_at)}</p>
                </div>
            ))}
        </div>
    );
}

export function UserAnalytics({ stats }: { stats: AnalyticsData }) {
    const [data, setData] = useState(stats);

    useEffect(() => {
        setData(stats);
    }, [stats]);

    useEffect(() => {
        if (!window.Echo) {
            return;
        }

        const channel = window.Echo.private("administrators");

        channel.listen(".UserCreated", () => {
            setData((previous) => {
                const registrationsChart = updateRegistrationChart(previous.registrations_chart);
                const totalUsers = previous.total_users + 1;

                return {
                    ...previous,
                    total_users: totalUsers,
                    all_time_users: previous.all_time_users + 1,
                    new_users_today: previous.new_users_today + 1,
                    new_users_30_days: previous.new_users_30_days + 1,
                    unverified_users: previous.unverified_users + 1,
                    verification_rate: totalUsers > 0 ? Number(((previous.verified_users / totalUsers) * 100).toFixed(1)) : 0,
                    online_rate: totalUsers > 0 ? Number(((previous.online_users / totalUsers) * 100).toFixed(1)) : 0,
                    two_factor_rate: totalUsers > 0 ? Number(((previous.two_factor_enabled_users / totalUsers) * 100).toFixed(1)) : 0,
                    assignment_rate: totalUsers > 0 ? Number(((previous.assigned_users / totalUsers) * 100).toFixed(1)) : 0,
                    registrations_chart: registrationsChart,
                    last_updated_at: new Date().toISOString(),
                };
            });
        });

        return () => {
            window.Echo?.leave("administrators");
        };
    }, []);

    const latestRegistrationPoint = data.registrations_chart.at(-1);

    return (
        <div className="space-y-6">
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    title="Total users"
                    value={formatNumber(data.total_users)}
                    description={`${formatNumber(data.new_users_30_days)} new in 30 days, ${formatNumber(data.trashed_users)} deleted records retained.`}
                    icon={Users}
                    tone="bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    progress={Math.min(100, Math.max(0, data.growth_rate))}
                    detail={`${formatPercent(data.growth_rate)} vs previous 30 days`}
                />
                <MetricCard
                    title="New today"
                    value={formatNumber(data.new_users_today)}
                    description={`${formatNumber(latestRegistrationPoint?.count ?? 0)} captured on the latest chart day.`}
                    icon={Sparkles}
                    tone="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                />
                <MetricCard
                    title="Verified"
                    value={formatPercent(data.verification_rate)}
                    description={`${formatNumber(data.verified_users)} verified, ${formatNumber(data.unverified_users)} waiting.`}
                    icon={UserRoundCheck}
                    tone="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                    progress={data.verification_rate}
                    detail={`${formatNumber(data.verified_users)} / ${formatNumber(data.total_users)} users`}
                />
                <MetricCard
                    title="Online now"
                    value={formatNumber(data.online_users)}
                    description="Active sessions inside the platform activity window."
                    icon={Radio}
                    tone="bg-lime-500/10 text-lime-600 dark:text-lime-400"
                    progress={data.online_rate}
                    detail={`${formatPercent(data.online_rate)} of active users`}
                />
            </section>

            <section className="grid gap-4 md:grid-cols-2">
                <MetricCard
                    title="2FA coverage"
                    value={formatPercent(data.two_factor_rate)}
                    description={`${formatNumber(data.two_factor_enabled_users)} users have the account security flag enabled.`}
                    icon={ShieldCheck}
                    tone="bg-violet-500/10 text-violet-600 dark:text-violet-400"
                    progress={data.two_factor_rate}
                    detail={`${formatNumber(data.two_factor_enabled_users)} protected accounts`}
                />
                <MetricCard
                    title="Organization assignment"
                    value={formatPercent(data.assignment_rate)}
                    description={`${formatNumber(data.assigned_users)} users are tied to a school or department.`}
                    icon={Building2}
                    tone="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    progress={data.assignment_rate}
                    detail={`${formatNumber(data.total_users - data.assigned_users)} unassigned users`}
                />
            </section>

            <section className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(360px,0.85fr)]">
                <Card className="border-border/60 bg-card/80 overflow-hidden">
                    <CardHeader className="border-border/60 border-b">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock3 className="text-primary size-4" />
                                    Registration Growth
                                </CardTitle>
                                <CardDescription>Daily registrations and cumulative user count over the last 30 days.</CardDescription>
                            </div>
                            <Badge variant="outline" className="w-fit rounded-md">
                                Updated {formatDateTime(data.last_updated_at)}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="p-5">
                        <RegistrationChart data={data.registrations_chart} />
                    </CardContent>
                </Card>

                <Card className="border-border/60 bg-card/80 overflow-hidden">
                    <CardHeader className="border-border/60 border-b">
                        <CardTitle className="flex items-center gap-2">
                            <CheckCircle2 className="text-primary size-4" />
                            Identity Coverage
                        </CardTitle>
                        <CardDescription>Verification and two-factor coverage across current users.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-5">
                        <StatusRing data={data} />
                    </CardContent>
                </Card>
            </section>

            <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.9fr)]">
                <Card className="border-border/60 bg-card/80 overflow-hidden">
                    <CardHeader className="border-border/60 border-b">
                        <CardTitle className="flex items-center gap-2">
                            <Users className="text-primary size-4" />
                            Role Mix
                        </CardTitle>
                        <CardDescription>Current users grouped by primary role.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-5">
                        <RoleChart data={data.role_distribution} />
                    </CardContent>
                </Card>

                <Card className="border-border/60 bg-card/80 overflow-hidden">
                    <CardHeader className="border-border/60 border-b">
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="text-primary size-4" />
                            Organization Spread
                        </CardTitle>
                        <CardDescription>Top schools and departments by assigned users.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6 p-5">
                        <OrganizationList title="Schools" items={data.school_distribution} />
                        <OrganizationList title="Departments" items={data.department_distribution} />
                    </CardContent>
                </Card>
            </section>

            <section className="grid gap-6 xl:grid-cols-2">
                <Card className="border-border/60 bg-card/80 overflow-hidden">
                    <CardHeader className="border-border/60 border-b">
                        <CardTitle className="flex items-center gap-2">
                            <Zap className="text-primary size-4" />
                            Top Active Users
                        </CardTitle>
                        <CardDescription>User request leaders recorded by Pulse in the last hour.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-5">
                        <TopActiveUsers users={data.top_active_users} />
                    </CardContent>
                </Card>

                <Card className="border-border/60 bg-card/80 overflow-hidden">
                    <CardHeader className="border-border/60 border-b">
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="text-primary size-4" />
                            Latest Users
                        </CardTitle>
                        <CardDescription>The newest accounts created in user management.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-5">
                        <RecentUsers users={data.recent_users} />
                    </CardContent>
                </Card>
            </section>
        </div>
    );
}
