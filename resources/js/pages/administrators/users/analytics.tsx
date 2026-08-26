import { Area, AreaChart, ChartTooltip, Grid, XAxis, chartCssVars } from "@/components/charts";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Activity, Radio, ShieldCheck, UserPlus, UserRoundCheck, Users, type LucideIcon } from "lucide-react";
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
    [key: string]: unknown;
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

    return [
        ...nextChart.slice(-29),
        {
            date: today,
            label: new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric" }).format(new Date()),
            count: 1,
            cumulative: (nextChart.at(-1)?.cumulative ?? 0) + 1,
        },
    ];
}

function EmptyPanel({ label }: { label: string }) {
    return (
        <div className="text-muted-foreground flex min-h-32 flex-col items-center justify-center rounded-lg border border-dashed p-5 text-center">
            <Activity aria-hidden="true" className="mb-2 size-5 opacity-50" />
            <p className="text-sm">{label}</p>
        </div>
    );
}

function MetricTile({
    title,
    value,
    description,
    icon: Icon,
    tone,
}: {
    title: string;
    value: string;
    description: string;
    icon: LucideIcon;
    tone: string;
}) {
    return (
        <div className="bg-card rounded-xl border px-4 py-3 shadow-xs">
            <div className="flex items-center justify-between gap-3">
                <p className="text-muted-foreground text-xs font-semibold tracking-[0.12em] uppercase">{title}</p>
                <Icon aria-hidden="true" className={`size-4 ${tone}`} />
            </div>
            <p className="mt-2 text-2xl font-semibold tracking-tight tabular-nums">{value}</p>
            <p className="text-muted-foreground mt-1 text-xs">{description}</p>
        </div>
    );
}

function RegistrationChart({ data }: { data: RegistrationPoint[] }) {
    if (!data.some((item) => item.cumulative > 0)) {
        return <EmptyPanel label="Registration data will appear once users are created." />;
    }

    return (
        <div role="img" aria-label="User registrations over the last 30 days">
            <AreaChart data={data} xDataKey="date" className="h-56 w-full" aspectRatio="16 / 7" margin={{ left: 28, right: 16, top: 18, bottom: 34 }}>
                <Grid horizontal />
                <Area dataKey="cumulative" fill={chartCssVars.lineSecondary} fillOpacity={0.12} stroke={chartCssVars.lineSecondary} strokeWidth={2} />
                <Area
                    dataKey="count"
                    fill={chartCssVars.linePrimary}
                    fillOpacity={0.28}
                    stroke={chartCssVars.linePrimary}
                    strokeWidth={2}
                    showMarkers
                />
                <XAxis tickMode="data" />
                <ChartTooltip
                    showDatePill
                    rows={(point) => [
                        { color: chartCssVars.linePrimary, label: "New users", value: formatNumber(Number(point.count ?? 0)) },
                        { color: chartCssVars.lineSecondary, label: "Running total", value: formatNumber(Number(point.cumulative ?? 0)) },
                    ]}
                />
            </AreaChart>
        </div>
    );
}

function CoverageRow({ label, count, total, percentage }: { label: string; count: number; total: number; percentage: number }) {
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-3 text-sm">
                <span>{label}</span>
                <span className="text-muted-foreground tabular-nums">
                    {formatNumber(count)} / {formatNumber(total)} <span className="font-medium">({formatPercent(percentage)})</span>
                </span>
            </div>
            <Progress value={Math.max(0, Math.min(percentage, 100))} className="h-1.5" />
        </div>
    );
}

function RoleMix({ data }: { data: DistributionPoint[] }) {
    if (data.length === 0) {
        return <EmptyPanel label="Role distribution will appear once users are created." />;
    }

    return (
        <div className="space-y-4">
            {data.slice(0, 6).map((item) => (
                <div key={item.role} className="space-y-2">
                    <div className="flex items-center justify-between gap-3 text-sm">
                        <span className="truncate">{item.label}</span>
                        <span className="text-muted-foreground shrink-0 tabular-nums">
                            {formatNumber(item.count)} · {formatPercent(item.percentage)}
                        </span>
                    </div>
                    <Progress value={item.percentage} className="h-1.5" />
                </div>
            ))}
        </div>
    );
}

function RecentAccounts({ users }: { users: RecentUser[] }) {
    if (users.length === 0) {
        return <EmptyPanel label="Recently created users will appear here." />;
    }

    return (
        <div className="divide-y">
            {users.slice(0, 5).map((user) => (
                <div key={user.id} className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex min-w-0 items-center gap-3">
                        <Avatar className="size-8 border">
                            <AvatarImage src={user.avatar || undefined} alt={user.name} />
                            <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">{user.name}</p>
                            <p className="text-muted-foreground truncate text-xs">{user.role_label}</p>
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1">
                        <Badge variant={user.verified ? "secondary" : "outline"} className="rounded-md text-[11px]">
                            {user.verified ? "Verified" : "Pending"}
                        </Badge>
                        <span className="text-muted-foreground text-[11px]">{formatDateTime(user.created_at)}</span>
                    </div>
                </div>
            ))}
        </div>
    );
}

export function UserAnalytics({ stats, detailed = false }: { stats: AnalyticsData; detailed?: boolean }) {
    const [data, setData] = useState(stats);

    useEffect(() => {
        setData(stats);
    }, [stats]);

    useEffect(() => {
        if (detailed || !window.Echo) {
            return;
        }

        const channel = window.Echo.private("administrators");

        channel.listen(".UserCreated", () => {
            setData((previous) => {
                const totalUsers = previous.total_users + 1;
                const registrationsChart = updateRegistrationChart(previous.registrations_chart);

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
    }, [detailed]);

    return (
        <div className="space-y-4">
            {!detailed ? (
                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricTile
                        title="Total users"
                        value={formatNumber(data.total_users)}
                        description={`${formatNumber(data.new_users_30_days)} new in the last 30 days`}
                        icon={Users}
                        tone="text-primary"
                    />
                    <MetricTile
                        title="New today"
                        value={formatNumber(data.new_users_today)}
                        description={`${formatPercent(data.growth_rate)} change vs. previous period`}
                        icon={UserPlus}
                        tone="text-emerald-600 dark:text-emerald-400"
                    />
                    <MetricTile
                        title="Verified"
                        value={formatPercent(data.verification_rate)}
                        description={`${formatNumber(data.unverified_users)} accounts still pending`}
                        icon={UserRoundCheck}
                        tone="text-blue-600 dark:text-blue-400"
                    />
                    <MetricTile
                        title="Online now"
                        value={formatNumber(data.online_users)}
                        description={`${formatPercent(data.online_rate)} of current accounts`}
                        icon={Radio}
                        tone="text-amber-600 dark:text-amber-400"
                    />
                </section>
            ) : null}

            {detailed ? (
                <>
                    <section className="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
                        <Card size="sm" className="min-w-0">
                            <CardHeader className="border-b">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>Registration trend</CardTitle>
                                        <p className="text-muted-foreground mt-1 text-sm">Daily registrations and the running account total.</p>
                                    </div>
                                    <span className="text-muted-foreground shrink-0 text-xs">Updated {formatDateTime(data.last_updated_at)}</span>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <RegistrationChart data={data.registrations_chart} />
                            </CardContent>
                        </Card>

                        <Card size="sm">
                            <CardHeader className="border-b">
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldCheck aria-hidden="true" className="text-primary size-4" />
                                    Account coverage
                                </CardTitle>
                                <p className="text-muted-foreground mt-1 text-sm">Signals that need occasional admin review.</p>
                            </CardHeader>
                            <CardContent className="space-y-5 pt-4">
                                <CoverageRow
                                    label="Email verified"
                                    count={data.verified_users}
                                    total={data.total_users}
                                    percentage={data.verification_rate}
                                />
                                <CoverageRow
                                    label="Two-factor enabled"
                                    count={data.two_factor_enabled_users}
                                    total={data.total_users}
                                    percentage={data.two_factor_rate}
                                />
                                <CoverageRow
                                    label="Organization assigned"
                                    count={data.assigned_users}
                                    total={data.total_users}
                                    percentage={data.assignment_rate}
                                />
                            </CardContent>
                        </Card>
                    </section>

                    <section className="grid gap-4 xl:grid-cols-2">
                        <Card size="sm">
                            <CardHeader className="border-b">
                                <CardTitle>Role mix</CardTitle>
                                <p className="text-muted-foreground mt-1 text-sm">Current accounts grouped by primary role.</p>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <RoleMix data={data.role_distribution} />
                            </CardContent>
                        </Card>

                        <Card size="sm">
                            <CardHeader className="border-b">
                                <CardTitle>Latest accounts</CardTitle>
                                <p className="text-muted-foreground mt-1 text-sm">The most recently created user records.</p>
                            </CardHeader>
                            <CardContent className="pt-1">
                                <RecentAccounts users={data.recent_users} />
                            </CardContent>
                        </Card>
                    </section>
                </>
            ) : null}
        </div>
    );
}
