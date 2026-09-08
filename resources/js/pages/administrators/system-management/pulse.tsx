import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import axios from "axios";
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Database,
    HardDrive,
    Loader2,
    RefreshCw,
    Server,
    Timer,
    Users,
    Zap,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import type { PulseData, SystemManagementPageProps } from "./types";

function formatPercent(value: number): string {
    return `${Math.round(value)}%`;
}

export default function SystemManagementPulsePage({ user, access }: SystemManagementPageProps) {
    const [pulseData, setPulseData] = useState<PulseData | null>(null);
    const [loadingPulse, setLoadingPulse] = useState(false);
    const [lastUpdated, setLastUpdated] = useState<Date | null>(null);

    const fetchPulse = useCallback(async (showToast = false) => {
        setLoadingPulse(true);
        try {
            const response = await axios.get("/api/pulse");
            const responseData = response.data.data || response.data;
            setPulseData(responseData as PulseData);
            setLastUpdated(new Date());
            if (showToast) {
                toast.success("Telemetry updated.");
            }
        } catch (error) {
            console.error("Failed to load pulse data", error);
            if (showToast) {
                toast.error("Failed to load system pulse telemetry.");
            }
        } finally {
            setLoadingPulse(false);
        }
    }, []);

    useEffect(() => {
        fetchPulse(false);
        const intervalId = setInterval(() => fetchPulse(false), 10000);

        return () => clearInterval(intervalId);
    }, [fetchPulse]);

    const summary = useMemo(() => {
        if (!pulseData) return null;

        const servers = Object.values(pulseData.servers.servers);
        const queuePending = pulseData.queues.queues.reduce((total, queue) => total + queue.size, 0);
        const queueFailed = pulseData.queues.queues.reduce((total, queue) => total + queue.failed, 0);
        const slowRequests = pulseData.slow_requests.slowRequests.length;
        const activeUsers = pulseData.usage.userRequestCounts.length;

        return {
            serverCount: servers.length,
            queuePending,
            queueFailed,
            slowRequests,
            activeUsers,
            cacheHits: Number(pulseData.cache.allCacheInteractions?.hits || 0),
            cacheMisses: Number(pulseData.cache.allCacheInteractions?.misses || 0),
        };
    }, [pulseData]);

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="pulse"
            heading="System Health & Pulse"
            description="Continuous telemetry monitoring server resources, queue queues, cache efficiency, and slow queries."
        >
            <div className="space-y-6">
                {/* Real-time Status & Action Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-border/60 bg-card/60 p-4 shadow-xs">
                    <div className="flex items-center gap-2.5">
                        <span className="relative flex size-3">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex size-3 rounded-full bg-emerald-500" />
                        </span>
                        <div>
                            <p className="text-xs font-semibold text-foreground">Live Telemetry Stream Active</p>
                            <p className="text-[11px] text-muted-foreground">
                                Auto-refreshing every 10s{" "}
                                {lastUpdated && (
                                    <span>• Last sync {lastUpdated.toLocaleTimeString()}</span>
                                )}
                            </p>
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => fetchPulse(true)}
                        disabled={loadingPulse}
                        className="h-8 gap-1.5 text-xs self-start sm:self-center bg-background/80"
                    >
                        <RefreshCw className={cn("size-3.5", loadingPulse && "animate-spin")} />
                        <span>Refresh Metrics</span>
                    </Button>
                </div>

                {!pulseData && (
                    <Card className="border-border/60 bg-card/70 shadow-xs">
                        <CardContent className="flex min-h-48 items-center justify-center">
                            {loadingPulse ? (
                                <div className="text-muted-foreground flex items-center gap-2.5 text-xs font-medium">
                                    <Loader2 className="size-4 animate-spin text-primary" />
                                    Connecting to telemetry stream...
                                </div>
                            ) : (
                                <div className="text-muted-foreground text-xs">No telemetry data recorded yet.</div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Key Telemetry Metrics Row */}
                {summary && (
                    <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                        <Card className="border-border/60 bg-card/70 shadow-xs p-4 flex flex-col justify-between">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-muted-foreground">Cluster Servers</span>
                                <div className="flex size-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400">
                                    <Server className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2">
                                <span className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                    {summary.serverCount}
                                </span>
                                <span className="ml-2 text-xs text-muted-foreground">online</span>
                            </div>
                        </Card>

                        <Card className="border-border/60 bg-card/70 shadow-xs p-4 flex flex-col justify-between">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-muted-foreground">Background Jobs</span>
                                <div className="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                    <Zap className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2 flex items-baseline justify-between">
                                <div>
                                    <span className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                        {summary.queuePending.toLocaleString()}
                                    </span>
                                    <span className="ml-1 text-xs text-muted-foreground">queued</span>
                                </div>
                                <Badge
                                    variant={summary.queueFailed > 0 ? "destructive" : "outline"}
                                    className="text-[11px] font-mono h-5"
                                >
                                    {summary.queueFailed} failed
                                </Badge>
                            </div>
                        </Card>

                        <Card className="border-border/60 bg-card/70 shadow-xs p-4 flex flex-col justify-between">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-muted-foreground">Slow HTTP Requests</span>
                                <div className="flex size-8 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                                    <Timer className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2">
                                <span className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                    {summary.slowRequests}
                                </span>
                                <span className="ml-2 text-xs text-muted-foreground">&gt; 1,000ms</span>
                            </div>
                        </Card>

                        <Card className="border-border/60 bg-card/70 shadow-xs p-4 flex flex-col justify-between">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-muted-foreground">Active Session Users</span>
                                <div className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    <Users className="size-4" />
                                </div>
                            </div>
                            <div className="mt-2">
                                <span className="text-2xl font-bold tracking-tight text-foreground font-mono">
                                    {summary.activeUsers}
                                </span>
                                <span className="ml-2 text-xs text-muted-foreground">authenticated</span>
                            </div>
                        </Card>
                    </div>
                )}

                {/* Server Resource Consumption */}
                {pulseData && (
                    <div className="grid gap-5 xl:grid-cols-2">
                        {Object.entries(pulseData.servers.servers).map(([serverSlug, server]) => {
                            const memoryPercent =
                                server.memory_total > 0 ? (server.memory_current / server.memory_total) * 100 : 0;
                            return (
                                <Card key={serverSlug} className="border-border/60 bg-card/70 shadow-xs">
                                    <CardHeader className="pb-3 border-b border-border/40">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                                <Server className="size-4 text-primary" />
                                                <span>{server.name}</span>
                                            </CardTitle>
                                            <span className="text-[11px] font-mono text-muted-foreground">
                                                {server.updated_at}
                                            </span>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="pt-4 space-y-4">
                                        {/* CPU Usage */}
                                        <div className="space-y-1.5">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-medium text-muted-foreground">CPU Core Load</span>
                                                <span
                                                    className={cn(
                                                        "font-mono font-semibold",
                                                        server.cpu_current > 85 ? "text-destructive" : "text-foreground",
                                                    )}
                                                >
                                                    {formatPercent(server.cpu_current)}
                                                </span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted/60">
                                                <div
                                                    className={cn(
                                                        "h-full rounded-full transition-all duration-500",
                                                        server.cpu_current > 85 ? "bg-destructive" : "bg-primary",
                                                    )}
                                                    style={{ width: `${Math.min(server.cpu_current, 100)}%` }}
                                                />
                                            </div>
                                        </div>

                                        {/* RAM Usage */}
                                        <div className="space-y-1.5">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-medium text-muted-foreground">Memory RAM</span>
                                                <span
                                                    className={cn(
                                                        "font-mono font-semibold",
                                                        memoryPercent > 90 ? "text-destructive" : "text-foreground",
                                                    )}
                                                >
                                                    {formatPercent(memoryPercent)}
                                                </span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted/60">
                                                <div
                                                    className={cn(
                                                        "h-full rounded-full transition-all duration-500",
                                                        memoryPercent > 90 ? "bg-destructive" : "bg-primary",
                                                    )}
                                                    style={{ width: `${Math.min(memoryPercent, 100)}%` }}
                                                />
                                            </div>
                                        </div>

                                        {/* Disk Storage */}
                                        <div className="space-y-2 pt-1 border-t border-border/30">
                                            <div className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                                <HardDrive className="size-3.5" />
                                                <span>Mounted Disks</span>
                                            </div>
                                            {server.storage.map((disk) => {
                                                const usagePercent = disk.total > 0 ? (disk.used / disk.total) * 100 : 0;
                                                return (
                                                    <div key={disk.directory} className="space-y-1">
                                                        <div className="flex items-center justify-between text-[11px]">
                                                            <span className="font-mono text-muted-foreground">{disk.directory}</span>
                                                            <span
                                                                className={cn(
                                                                    "font-mono font-medium",
                                                                    usagePercent > 90 ? "text-destructive" : "text-foreground",
                                                                )}
                                                            >
                                                                {formatPercent(usagePercent)}
                                                            </span>
                                                        </div>
                                                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted/60">
                                                            <div
                                                                className={cn(
                                                                    "h-full rounded-full transition-all duration-500",
                                                                    usagePercent > 90 ? "bg-destructive" : "bg-sky-500",
                                                                )}
                                                                style={{ width: `${Math.min(usagePercent, 100)}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {/* Slow Requests & Cache Performance */}
                {pulseData && (
                    <div className="grid gap-5 xl:grid-cols-2">
                        {/* Slow Queries */}
                        <Card className="border-border/60 bg-card/70 shadow-xs">
                            <CardHeader className="pb-3 border-b border-border/40">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle className="size-4 text-amber-500" />
                                    <CardTitle className="text-sm font-semibold">Slow Endpoints & Queries</CardTitle>
                                </div>
                                <CardDescription className="text-xs">Requests exceeding 1,000ms latency</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-4 space-y-2">
                                {pulseData.slow_requests.slowRequests.length === 0 ? (
                                    <div className="flex items-center gap-2 py-4 text-xs text-muted-foreground">
                                        <CheckCircle2 className="size-4 text-emerald-500" />
                                        <span>All endpoints performing well within acceptable thresholds.</span>
                                    </div>
                                ) : (
                                    pulseData.slow_requests.slowRequests.slice(0, 8).map((request) => (
                                        <div
                                            key={`${request.method}-${request.uri}`}
                                            className="flex items-center justify-between gap-3 rounded-lg border border-border/40 bg-background/50 px-3 py-2 text-xs"
                                        >
                                            <div className="flex min-w-0 items-center gap-2">
                                                <Badge variant="outline" className="font-mono text-[10px] px-1.5 h-5">
                                                    {request.method}
                                                </Badge>
                                                <span className="truncate font-mono text-[11px] text-foreground">
                                                    {request.uri}
                                                </span>
                                            </div>
                                            <span className="font-mono font-semibold text-amber-600 dark:text-amber-400 shrink-0">
                                                {Number(request.slowest).toLocaleString()}ms
                                            </span>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        {/* Cache Telemetry */}
                        <Card className="border-border/60 bg-card/70 shadow-xs">
                            <CardHeader className="pb-3 border-b border-border/40">
                                <div className="flex items-center gap-2">
                                    <Database className="size-4 text-indigo-500" />
                                    <CardTitle className="text-sm font-semibold">Cache & Telemetry Activity</CardTitle>
                                </div>
                                <CardDescription className="text-xs">Redis & Octane in-memory cache statistics</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-4 space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="rounded-xl border border-border/40 bg-background/50 p-3">
                                        <p className="text-[11px] font-medium text-muted-foreground">Cache Hits</p>
                                        <p className="mt-1 text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                                            {summary?.cacheHits.toLocaleString() || 0}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-border/40 bg-background/50 p-3">
                                        <p className="text-[11px] font-medium text-muted-foreground">Cache Misses</p>
                                        <p className="mt-1 text-xl font-bold font-mono text-muted-foreground">
                                            {summary?.cacheMisses.toLocaleString() || 0}
                                        </p>
                                    </div>
                                </div>

                                <div className="space-y-2 pt-1 border-t border-border/30">
                                    <p className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
                                        <Activity className="size-3.5" />
                                        <span>Highest Request Generators</span>
                                    </p>
                                    {pulseData.usage.userRequestCounts.length === 0 ? (
                                        <p className="text-xs text-muted-foreground">No recent user activity.</p>
                                    ) : (
                                        pulseData.usage.userRequestCounts.slice(0, 5).map((usage) => (
                                            <div
                                                key={usage.key}
                                                className="flex items-center justify-between rounded-lg bg-background/40 px-3 py-1.5 text-xs"
                                            >
                                                <span className="truncate text-foreground font-medium">{usage.user.name}</span>
                                                <Badge variant="secondary" className="font-mono text-[10px] h-5">
                                                    {usage.count} reqs
                                                </Badge>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </SystemManagementLayout>
    );
}
