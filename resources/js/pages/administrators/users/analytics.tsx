import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { Activity, ArrowUpRight, ShieldCheck, Signal, UserPlus, Users, Zap } from "lucide-react";
import { useEffect, useState } from "react";
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

export interface AnalyticsData {
    total_users: number;
    new_users_today: number;
    verified_users: number;
    online_users: number;
    top_active_users: {
        id: string;
        name: string;
        email: string;
        requests: number;
        avatar?: string | null;
    }[];
    registrations_chart: { date: string; count: number }[];
}

export function UserAnalytics({ stats }: { stats: AnalyticsData }) {
    const [data, setData] = useState(stats);

    useEffect(() => {
        setData(stats);
    }, [stats]);

    useEffect(() => {
        if (!window.Echo) return;

        const channel = window.Echo.private("administrators");

        channel.listen(".UserCreated", (e: any) => {
            const today = new Date().toISOString().split("T")[0];

            setData((prev) => {
                const newChart = [...prev.registrations_chart];
                const todayIndex = newChart.findIndex((item) => item.date === today);

                if (todayIndex >= 0) {
                    newChart[todayIndex].count += 1;
                } else {
                    newChart.push({ date: today, count: 1 });
                }

                return {
                    ...prev,
                    total_users: prev.total_users + 1,
                    new_users_today: prev.new_users_today + 1,
                    registrations_chart: newChart,
                };
            });
        });

        return () => {
            window.Echo?.leave("administrators");
        };
    }, []);

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatsCard
                    title="Total Users"
                    value={data.total_users}
                    description="Active accounts"
                    icon={Users}
                    trend="+2.5% from last month"
                    trendUp={true}
                />
                <StatsCard
                    title="Online Now"
                    value={data.online_users}
                    description="Active sessions"
                    icon={Signal}
                    className="border-green-500/50 dark:border-green-500/20"
                    iconClassName="text-green-500 animate-pulse"
                />
                <StatsCard title="New Today" value={data.new_users_today} description="Registrations" icon={UserPlus} />
                <StatsCard
                    title="Verified"
                    value={`${Math.round((data.verified_users / (data.total_users || 1)) * 100)}%`}
                    description="Email verified"
                    icon={ShieldCheck}
                />
            </div>

            <div className="grid gap-4 md:grid-cols-7">
                <Card className="from-card to-card/50 col-span-4 bg-gradient-to-br">
                    <CardHeader>
                        <CardTitle>Registration Trends</CardTitle>
                        <CardDescription>User growth over the last 30 days</CardDescription>
                    </CardHeader>
                    <CardContent className="pl-2">
                        <div className="h-[240px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={data.registrations_chart} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="colorCount" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                                            <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" className="stroke-muted" vertical={false} />
                                    <XAxis
                                        dataKey="date"
                                        stroke="#888888"
                                        fontSize={12}
                                        tickLine={false}
                                        axisLine={false}
                                        tickFormatter={(value) => {
                                            const date = new Date(value);
                                            return `${date.getMonth() + 1}/${date.getDate()}`;
                                        }}
                                        minTickGap={32}
                                    />
                                    <YAxis stroke="#888888" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `${value}`} />
                                    <Tooltip
                                        content={({ active, payload }) => {
                                            if (active && payload && payload.length) {
                                                return (
                                                    <div className="bg-background rounded-lg border p-2 shadow-sm">
                                                        <div className="grid grid-cols-2 gap-2">
                                                            <div className="flex flex-col">
                                                                <span className="text-muted-foreground text-[0.70rem] uppercase">New Users</span>
                                                                <span className="font-bold">{payload[0].value}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            }
                                            return null;
                                        }}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="count"
                                        stroke="hsl(var(--primary))"
                                        fillOpacity={1}
                                        fill="url(#colorCount)"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                    </CardContent>
                </Card>

                <Card className="col-span-3 flex flex-col">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Zap className="h-4 w-4 text-amber-500" />
                            Top Active Users
                        </CardTitle>
                        <CardDescription>Users with most requests (1h)</CardDescription>
                    </CardHeader>
                    <CardContent className="flex-1">
                        <div className="space-y-4">
                            {data.top_active_users.length === 0 ? (
                                <div className="text-muted-foreground flex h-full flex-col items-center justify-center space-y-2 text-center">
                                    <Activity className="h-8 w-8 opacity-20" />
                                    <p className="text-sm">No activity recorded recently</p>
                                </div>
                            ) : (
                                data.top_active_users.map((user, i) => (
                                    <div key={user.id} className="flex items-center justify-between gap-4">
                                        <div className="flex items-center gap-3 overflow-hidden">
                                            <div className="relative">
                                                <Avatar className="h-9 w-9 border">
                                                    <AvatarImage src={user.avatar || undefined} alt={user.name} />
                                                    <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                                                </Avatar>
                                                <span className="bg-background text-muted-foreground ring-border absolute -right-1 -bottom-1 flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold ring-1">
                                                    {i + 1}
                                                </span>
                                            </div>
                                            <div className="grid gap-0.5 overflow-hidden">
                                                <p className="truncate text-sm leading-none font-medium">{user.name}</p>
                                                <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="secondary" className="font-mono text-xs">
                                                {user.requests.toLocaleString()}
                                            </Badge>
                                            {i === 0 && <ArrowUpRight className="h-3 w-3 text-green-500" />}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function StatsCard({
    title,
    value,
    description,
    icon: Icon,
    className,
    iconClassName,
    trend,
    trendUp,
}: {
    title: string;
    value: string | number;
    description: string;
    icon: any;
    className?: string;
    iconClassName?: string;
    trend?: string;
    trendUp?: boolean;
}) {
    return (
        <Card className={cn("overflow-hidden transition-all hover:shadow-md", className)}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-muted-foreground text-sm font-medium">{title}</CardTitle>
                <Icon className={cn("text-muted-foreground h-4 w-4", iconClassName)} />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
                <div className="text-muted-foreground flex items-center text-xs">
                    {description}
                    {trend && (
                        <span className={cn("ml-2 flex items-center gap-0.5", trendUp ? "text-green-500" : "text-red-500")}>
                            {trendUp ? <ArrowUpRight className="h-3 w-3" /> : null}
                            {trend}
                        </span>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
