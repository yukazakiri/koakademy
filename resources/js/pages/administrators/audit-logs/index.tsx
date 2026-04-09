import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Head, router } from "@inertiajs/react";
import { Activity, Eye, Filter, Minus, Plus, RefreshCw, Search, ShieldCheck, Sparkles, Target, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { useDebouncedCallback } from "use-debounce";

interface AuditLogEntry {
    id: number;
    description: string;
    event: string | null;
    log_name: string | null;
    subject_type: string | null;
    subject_id: number | null;
    properties: Record<string, any> | null;
    causer: {
        id: number;
        name: string;
        email: string | null;
        avatar: string | null;
    } | null;
    created_at: string | null;
    created_at_human: string;
}

interface AuditLogAnalytics {
    total: number;
    filtered: number;
    unique_actors: number;
    action_breakdown: { event: string; count: number }[];
    trend: { date: string; count: number }[];
    last_updated_at: string;
}

interface AuditLogIndexProps {
    user: {
        name: string;
        email: string;
        avatar: string | null;
        role: string;
    };
    logs: {
        data: AuditLogEntry[];
        total: number;
        current_page: number;
        last_page: number;
        per_page: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search: string | null;
        event: string | null;
        log_name: string | null;
        subject_type: string | null;
        causer_id: string | null;
        range: string | null;
    };
    options: {
        events: string[];
        log_names: string[];
        subject_types: { value: string; label: string }[];
        causers: { id: number; name: string; email: string | null; avatar: string | null }[];
        ranges: { value: string; label: string }[];
    };
    analytics: AuditLogAnalytics;
}

interface DiffEntry {
    field: string;
    oldValue: string;
    newValue: string;
}

export default function AdministratorAuditLogsIndex({ user, logs, filters, options, analytics }: AuditLogIndexProps) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [selectedLog, setSelectedLog] = useState<AuditLogEntry | null>(null);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.audit-logs.index"), { ...filters, search: term }, { preserveState: true, replace: true });
    }, 300);

    const handleFilterChange = (key: string, value: string) => {
        router.get(
            route("administrators.audit-logs.index"),
            { ...filters, [key]: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get(route("administrators.audit-logs.index"), { search: filters.search }, { preserveState: true, replace: true });
    };

    const activeFilterCount = useMemo(() => {
        return Object.keys(filters).filter((key) => key !== "search" && filters[key as keyof typeof filters]).length;
    }, [filters]);

    const eventTone = (event: string | null) => {
        switch (event) {
            case "created":
                return "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300";
            case "updated":
                return "bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300";
            case "deleted":
                return "bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300";
            case "restored":
                return "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300";
            default:
                return "bg-muted text-muted-foreground";
        }
    };

    const buildDiffEntries = (log: AuditLogEntry | null): DiffEntry[] => {
        if (!log?.properties) return [];

        const attributes = (log.properties.attributes ?? {}) as Record<string, unknown>;
        const oldValues = (log.properties.old ?? {}) as Record<string, unknown>;
        const allFields = new Set([...Object.keys(attributes), ...Object.keys(oldValues)]);

        return Array.from(allFields).map((field) => {
            const oldValue = oldValues[field] ?? "-";
            const newValue = attributes[field] ?? "-";

            return {
                field,
                oldValue: formatValue(oldValue),
                newValue: formatValue(newValue),
            };
        });
    };

    const formatValue = (value: unknown): string => {
        if (value === null || value === undefined) return "-";
        if (typeof value === "string") return value;
        if (typeof value === "number" || typeof value === "boolean") return String(value);
        return JSON.stringify(value);
    };

    return (
        <AdminLayout user={user} title="Audit Logs">
            <Head title="Administrators • Audit Logs" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-3xl font-bold tracking-tight">Audit Logs</h2>
                        <p className="text-muted-foreground">Track sensitive actions, system changes, and operational events.</p>
                    </div>
                    <Button variant="outline" className="gap-2" onClick={() => router.reload({ only: ["logs", "analytics"] })}>
                        <RefreshCw className="h-4 w-4" /> Refresh
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="bg-primary/10 rounded-full p-3">
                                <Activity className="text-primary h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">Total Events</p>
                                <p className="text-2xl font-semibold">{analytics.total}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-sky-100 p-3 dark:bg-sky-900/30">
                                <Filter className="h-5 w-5 text-sky-600 dark:text-sky-300" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">Filtered Results</p>
                                <p className="text-2xl font-semibold">{analytics.filtered}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-amber-100 p-3 dark:bg-amber-900/30">
                                <ShieldCheck className="h-5 w-5 text-amber-600 dark:text-amber-300" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">Unique Actors</p>
                                <p className="text-2xl font-semibold">{analytics.unique_actors}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-4 p-5">
                            <div className="rounded-full bg-emerald-100 p-3 dark:bg-emerald-900/30">
                                <Sparkles className="h-5 w-5 text-emerald-600 dark:text-emerald-300" />
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">Last Updated</p>
                                <p className="text-sm font-semibold">{new Date(analytics.last_updated_at).toLocaleString()}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-7">
                    <Card className="lg:col-span-4">
                        <CardHeader>
                            <CardTitle>Audit Activity Trend</CardTitle>
                            <CardDescription>Events recorded over the selected timeframe</CardDescription>
                        </CardHeader>
                        <CardContent className="h-[240px]">
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={analytics.trend} margin={{ left: 0, right: 12, top: 10, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="auditTrend" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                                            <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" className="stroke-muted" vertical={false} />
                                    <XAxis
                                        dataKey="date"
                                        tickLine={false}
                                        axisLine={false}
                                        fontSize={12}
                                        tickFormatter={(value) => {
                                            const date = new Date(value);
                                            return `${date.getMonth() + 1}/${date.getDate()}`;
                                        }}
                                    />
                                    <YAxis tickLine={false} axisLine={false} fontSize={12} />
                                    <Tooltip
                                        content={({ active, payload }) => {
                                            if (!active || !payload?.length) return null;
                                            return (
                                                <div className="bg-background rounded-lg border p-2 shadow-sm">
                                                    <p className="text-muted-foreground text-xs">Events</p>
                                                    <p className="text-sm font-semibold">{payload[0].value}</p>
                                                </div>
                                            );
                                        }}
                                    />
                                    <Area type="monotone" dataKey="count" stroke="hsl(var(--primary))" fill="url(#auditTrend)" strokeWidth={2} />
                                </AreaChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Target className="h-4 w-4 text-emerald-500" />
                                Action Breakdown
                            </CardTitle>
                            <CardDescription>Top actions by volume</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {analytics.action_breakdown.length === 0 ? (
                                    <div className="text-muted-foreground flex h-32 flex-col items-center justify-center">
                                        <Activity className="h-6 w-6 opacity-40" />
                                        <p className="text-sm">No activity recorded.</p>
                                    </div>
                                ) : (
                                    analytics.action_breakdown.map((item) => (
                                        <div key={item.event} className="flex items-center justify-between">
                                            <Badge className={eventTone(item.event)} variant="secondary">
                                                {item.event}
                                            </Badge>
                                            <span className="text-sm font-semibold">{item.count}</span>
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="space-y-4">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle>Audit Log Feed</CardTitle>
                                <CardDescription>Search, filter, and review every tracked action.</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" onClick={clearFilters} className="gap-2">
                                <Trash2 className="h-4 w-4" /> Clear Filters
                            </Button>
                        </div>

                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                            <div className="relative flex-1">
                                <Search className="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                                <Input
                                    placeholder="Search description, log name, subject..."
                                    value={search}
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        handleSearch(event.target.value);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Select value={filters.event ?? "all"} onValueChange={(value) => handleFilterChange("event", value)}>
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Event" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Events</SelectItem>
                                        {options.events.map((event) => (
                                            <SelectItem key={event} value={event}>
                                                {event}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={filters.log_name ?? "all"} onValueChange={(value) => handleFilterChange("log_name", value)}>
                                    <SelectTrigger className="w-[170px]">
                                        <SelectValue placeholder="Log" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Logs</SelectItem>
                                        {options.log_names.map((name) => (
                                            <SelectItem key={name} value={name}>
                                                {name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={filters.subject_type ?? "all"} onValueChange={(value) => handleFilterChange("subject_type", value)}>
                                    <SelectTrigger className="w-[170px]">
                                        <SelectValue placeholder="Subject" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Subjects</SelectItem>
                                        {options.subject_types.map((subject) => (
                                            <SelectItem key={subject.value} value={subject.value}>
                                                {subject.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={filters.causer_id ?? "all"} onValueChange={(value) => handleFilterChange("causer_id", value)}>
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue placeholder="Actor" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Actors</SelectItem>
                                        {options.causers.map((causer) => (
                                            <SelectItem key={causer.id} value={String(causer.id)}>
                                                {causer.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={filters.range ?? "30d"} onValueChange={(value) => handleFilterChange("range", value)}>
                                    <SelectTrigger className="w-[170px]">
                                        <SelectValue placeholder="Range" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.ranges.map((range) => (
                                            <SelectItem key={range.value} value={range.value}>
                                                {range.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {activeFilterCount > 0 && (
                                    <Badge variant="secondary" className="h-9 px-3 text-xs">
                                        {activeFilterCount} filters
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <ScrollArea className="h-[520px]">
                            <Table>
                                <TableHeader className="bg-card sticky top-0 z-10">
                                    <TableRow>
                                        <TableHead>Actor</TableHead>
                                        <TableHead>Action</TableHead>
                                        <TableHead>Subject</TableHead>
                                        <TableHead>Log</TableHead>
                                        <TableHead>Time</TableHead>
                                        <TableHead className="w-[120px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {logs.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={5} className="text-muted-foreground h-24 text-center">
                                                No audit events found for the selected filters.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        logs.data.map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="h-8 w-8">
                                                            <AvatarImage src={log.causer?.avatar ?? undefined} alt={log.causer?.name} />
                                                            <AvatarFallback>{log.causer?.name?.charAt(0) ?? "S"}</AvatarFallback>
                                                        </Avatar>
                                                        <div className="flex flex-col">
                                                            <span className="text-sm font-medium">{log.causer?.name ?? "System"}</span>
                                                            <span className="text-muted-foreground text-xs">{log.causer?.email ?? ""}</span>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col gap-1">
                                                        <Badge className={eventTone(log.event)} variant="secondary">
                                                            {log.event ?? "system"}
                                                        </Badge>
                                                        <span className="text-sm font-medium">{log.description || "Activity logged"}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col text-sm">
                                                        <span className="font-medium">{log.subject_type ?? "-"}</span>
                                                        <span className="text-muted-foreground text-xs">ID: {log.subject_id ?? "-"}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{log.log_name ?? "default"}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col text-sm">
                                                        <span className="font-medium">{log.created_at_human}</span>
                                                        <span className="text-muted-foreground text-xs">
                                                            {log.created_at ? new Date(log.created_at).toLocaleString() : ""}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Button variant="outline" size="sm" className="gap-2" onClick={() => setSelectedLog(log)}>
                                                        <Eye className="h-4 w-4" />
                                                        View
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </ScrollArea>
                        <Separator />
                        <div className="text-muted-foreground flex items-center justify-between p-4 text-sm">
                            <span>
                                Showing {logs.from ?? 0} - {logs.to ?? 0} of {logs.total}
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            route("administrators.audit-logs.index"),
                                            { ...filters, page: logs.current_page - 1 },
                                            { preserveState: true },
                                        )
                                    }
                                    disabled={logs.current_page <= 1}
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.get(
                                            route("administrators.audit-logs.index"),
                                            { ...filters, page: logs.current_page + 1 },
                                            { preserveState: true },
                                        )
                                    }
                                    disabled={logs.current_page >= logs.last_page}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Sheet open={!!selectedLog} onOpenChange={(open) => !open && setSelectedLog(null)}>
                <SheetContent side="right" className="sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Audit Log Details</SheetTitle>
                        <SheetDescription>Review the exact changes captured in this audit event.</SheetDescription>
                    </SheetHeader>

                    <div className="space-y-6 px-4 pb-6">
                        <div className="bg-muted/40 rounded-lg border p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-muted-foreground text-xs">Event</p>
                                    <p className="text-sm font-semibold">{selectedLog?.event ?? "system"}</p>
                                </div>
                                <Badge className={eventTone(selectedLog?.event ?? null)} variant="secondary">
                                    {selectedLog?.log_name ?? "default"}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground mt-3 text-sm">{selectedLog?.description || "Activity logged"}</p>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h4 className="text-sm font-semibold">Changes</h4>
                                <span className="text-muted-foreground text-xs">
                                    {selectedLog?.created_at ? new Date(selectedLog.created_at).toLocaleString() : ""}
                                </span>
                            </div>

                            {buildDiffEntries(selectedLog).length === 0 ? (
                                <div className="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-sm">
                                    No property changes recorded for this event.
                                </div>
                            ) : (
                                <div className="overflow-hidden rounded-lg border">
                                    <div className="bg-muted/60 text-muted-foreground grid grid-cols-12 gap-0 border-b px-4 py-2 text-xs font-semibold uppercase">
                                        <span className="col-span-3">Field</span>
                                        <span className="col-span-4">Before</span>
                                        <span className="col-span-5">After</span>
                                    </div>
                                    {buildDiffEntries(selectedLog).map((diff) => (
                                        <div key={diff.field} className="grid grid-cols-12 gap-0 border-b px-4 py-3 last:border-b-0">
                                            <div className="text-muted-foreground col-span-3 text-sm font-medium">{diff.field}</div>
                                            <div className="col-span-4 text-sm">
                                                <div className="flex items-start gap-2 rounded-md bg-rose-50 px-2 py-1 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">
                                                    <Minus className="mt-0.5 h-3.5 w-3.5" />
                                                    <span className="break-all">{diff.oldValue}</span>
                                                </div>
                                            </div>
                                            <div className="col-span-5 text-sm">
                                                <div className="flex items-start gap-2 rounded-md bg-emerald-50 px-2 py-1 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">
                                                    <Plus className="mt-0.5 h-3.5 w-3.5" />
                                                    <span className="break-all">{diff.newValue}</span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="bg-muted/30 text-muted-foreground rounded-lg border p-4 text-xs">
                            <p className="text-foreground font-semibold">Metadata</p>
                            <div className="mt-2 space-y-1">
                                <p>
                                    Subject: {selectedLog?.subject_type ?? "-"} #{selectedLog?.subject_id ?? "-"}
                                </p>
                                <p>Actor: {selectedLog?.causer?.name ?? "System"}</p>
                                <p>Actor Email: {selectedLog?.causer?.email ?? "-"}</p>
                            </div>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AdminLayout>
    );
}
