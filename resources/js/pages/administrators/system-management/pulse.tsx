import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import axios from "axios";
import { Activity, AlertTriangle, Database, HardDrive, Loader2, RefreshCw, Server, Timer, Users } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import type { PulseData, SystemManagementPageProps } from "./types";

function formatPercent(value: number): string {
    return `${Math.round(value)}%`;
}

export default function SystemManagementPulsePage({ user, access }: SystemManagementPageProps) {
    const [pulseData, setPulseData] = useState<PulseData | null>(null);
    const [loadingPulse, setLoadingPulse] = useState(false);

    useEffect(() => {
        let intervalId: ReturnType<typeof setInterval> | null = null;

        const fetchPulse = async () => {
            setLoadingPulse(true);
            try {
                const response = await axios.get("/api/pulse");
                const responseData = response.data.data || response.data;
                setPulseData(responseData as PulseData);
            } catch (error) {
                console.error("Failed to load pulse data", error);
                toast.error("Failed to load system pulse data.");
            } finally {
                setLoadingPulse(false);
            }
        };

        fetchPulse();
        intervalId = setInterval(fetchPulse, 10000);

        return () => {
            if (intervalId) {
                clearInterval(intervalId);
            }
        };
    }, []);

    const summary = useMemo(() => {
        if (!pulseData) {
            return null;
        }

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
            heading="System Pulse"
            description="Live operational health from Pulse telemetry endpoints."
        >
            <div className="flex justify-end">
                <Button variant="outline" onClick={() => window.location.reload()} disabled={loadingPulse}>
                    {loadingPulse ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}
                    Refresh
                </Button>
            </div>

            {!pulseData ? (
                <Card>
                    <CardContent className="flex min-h-40 items-center justify-center">
                        {loadingPulse ? (
                            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Loading pulse telemetry...
                            </div>
                        ) : (
                            <div className="text-muted-foreground text-sm">No pulse data available.</div>
                        )}
                    </CardContent>
                </Card>
            ) : null}

            {summary ? (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Servers</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-xl">
                                <Server className="h-5 w-5" />
                                {summary.serverCount}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Queues</CardDescription>
                            <CardTitle className="text-xl">{summary.queuePending.toLocaleString()} pending</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <Badge variant={summary.queueFailed > 0 ? "destructive" : "secondary"}>{summary.queueFailed} failed</Badge>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Slow Requests</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-xl">
                                <Timer className="h-5 w-5" />
                                {summary.slowRequests}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Active Users</CardDescription>
                            <CardTitle className="flex items-center gap-2 text-xl">
                                <Users className="h-5 w-5" />
                                {summary.activeUsers}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>
            ) : null}

            {pulseData ? (
                <div className="grid gap-6 xl:grid-cols-2">
                    {Object.entries(pulseData.servers.servers).map(([serverSlug, server]) => {
                        const memoryPercent = server.memory_total > 0 ? (server.memory_current / server.memory_total) * 100 : 0;
                        return (
                            <Card key={serverSlug}>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Server className="h-4 w-4" />
                                        {server.name}
                                    </CardTitle>
                                    <CardDescription>{server.updated_at}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">CPU</span>
                                            <span className={cn(server.cpu_current > 85 ? "text-destructive font-medium" : "")}>
                                                {formatPercent(server.cpu_current)}
                                            </span>
                                        </div>
                                        <div className="bg-muted h-2 overflow-hidden rounded-full">
                                            <div
                                                className={cn("h-full", server.cpu_current > 85 ? "bg-destructive" : "bg-primary")}
                                                style={{ width: `${Math.min(server.cpu_current, 100)}%` }}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Memory</span>
                                            <span className={cn(memoryPercent > 90 ? "text-destructive font-medium" : "")}>
                                                {formatPercent(memoryPercent)}
                                            </span>
                                        </div>
                                        <div className="bg-muted h-2 overflow-hidden rounded-full">
                                            <div
                                                className={cn("h-full", memoryPercent > 90 ? "bg-destructive" : "bg-primary")}
                                                style={{ width: `${Math.min(memoryPercent, 100)}%` }}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-muted-foreground flex items-center gap-1 text-sm">
                                            <HardDrive className="h-4 w-4" />
                                            Storage
                                        </p>
                                        {server.storage.map((disk) => {
                                            const usagePercent = disk.total > 0 ? (disk.used / disk.total) * 100 : 0;
                                            return (
                                                <div key={disk.directory} className="space-y-1">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="font-mono">{disk.directory}</span>
                                                        <span
                                                            className={cn(
                                                                usagePercent > 90 ? "text-destructive font-medium" : "text-muted-foreground",
                                                            )}
                                                        >
                                                            {formatPercent(usagePercent)}
                                                        </span>
                                                    </div>
                                                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                                        <div
                                                            className={cn("h-full", usagePercent > 90 ? "bg-destructive" : "bg-primary")}
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
            ) : null}

            {pulseData ? (
                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <AlertTriangle className="h-4 w-4 text-amber-500" />
                                Slow Requests
                            </CardTitle>
                            <CardDescription>Recent requests over threshold.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {pulseData.slow_requests.slowRequests.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No slow requests detected.</p>
                            ) : (
                                pulseData.slow_requests.slowRequests.slice(0, 8).map((request) => (
                                    <div key={`${request.method}-${request.uri}`} className="flex items-center justify-between gap-2 text-sm">
                                        <span className="flex min-w-0 items-center gap-2">
                                            <Badge variant="outline" className="text-xs">
                                                {request.method}
                                            </Badge>
                                            <span className="truncate font-mono text-xs">{request.uri}</span>
                                        </span>
                                        <span className="text-amber-600">{Number(request.slowest).toLocaleString()}ms</span>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Database className="h-4 w-4 text-indigo-500" />
                                Cache & Activity
                            </CardTitle>
                            <CardDescription>Cache interactions and top request users.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">Cache Hits</p>
                                    <p className="text-lg font-semibold">{summary?.cacheHits.toLocaleString() || 0}</p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">Cache Misses</p>
                                    <p className="text-lg font-semibold">{summary?.cacheMisses.toLocaleString() || 0}</p>
                                </div>
                            </div>
                            <Separator />
                            <div className="space-y-2">
                                <p className="text-muted-foreground flex items-center gap-1 text-sm">
                                    <Activity className="h-4 w-4" />
                                    Top Users
                                </p>
                                {pulseData.usage.userRequestCounts.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">No activity data.</p>
                                ) : (
                                    pulseData.usage.userRequestCounts.slice(0, 6).map((usage) => (
                                        <div key={usage.key} className="flex items-center justify-between text-sm">
                                            <span className="truncate">{usage.user.name}</span>
                                            <Badge variant="secondary">{usage.count}</Badge>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            ) : null}
        </SystemManagementLayout>
    );
}
