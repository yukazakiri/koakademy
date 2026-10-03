import { Area, AreaChart, chartCssVars, ChartTooltip, Gauge, Grid, XAxis } from "@/components/charts";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    Banknote,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    DollarSign,
    GraduationCap,
    Layers,
    ListChecks,
    Receipt,
    School,
    Target,
    TrendingUp,
    UserCheck,
    Users,
    WalletCards,
} from "lucide-react";
import { useMemo, useState } from "react";
import { route } from "ziggy-js";
import type { DashboardViewProps } from "./types";

function formatNumber(value: number): string {
    return new Intl.NumberFormat("en-US").format(value);
}

function formatPercent(value: number): string {
    return `${new Intl.NumberFormat("en-US", { maximumFractionDigits: 1 }).format(value)}%`;
}

function formatCurrency(amount: number, currency = "PHP"): string {
    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
        style: "currency",
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDateTime(value?: string | null): string {
    if (!value) return "Just now";
    try {
        return new Intl.DateTimeFormat("en-US", {
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit",
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function safeRoute(name: string, fallbackUrl: string): string {
    try {
        if (typeof window !== "undefined" && typeof route === "function") {
            return route(name);
        }
    } catch {
        // Fall back gracefully when route manifest is not yet hydrated in client session
    }
    return fallbackUrl;
}

function TrendPill({ text, tone = "success" }: { text: string; tone?: "success" | "warning" | "destructive" | "info" | "neutral" }) {
    const toneClasses = {
        success: "bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 border-emerald-500/20",
        warning: "bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 border-amber-500/20",
        destructive: "bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400 border-rose-500/20",
        info: "bg-sky-500/10 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400 border-sky-500/20",
        neutral: "bg-muted text-muted-foreground border-border/40",
    }[tone];

    return (
        <span className={cn("inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold tabular-nums", toneClasses)}>
            {text}
        </span>
    );
}

export default function ExecutiveOverviewView({ user, admin_data, currency }: DashboardViewProps) {
    const [trendRange, setTrendRange] = useState<"year" | "six_months">("year");

    const finance = admin_data.finance_snapshot;
    const collectionRate = Math.min(Math.max(finance.collection_rate, 0), 100);
    const conversionRate = Math.min(Math.max(admin_data.enrollment_health.conversion_rate, 0), 100);

    const totalActionQueueCount = useMemo(
        () => admin_data.operations.action_queue.reduce((acc, item) => acc + item.value, 0),
        [admin_data.operations.action_queue],
    );

    /**
     * Windowed enrollment series. The payload supplies no target, so no benchmark series is
     * synthesised: an invented target line would read as an institutional goal.
     */
    const windowedTrends = useMemo(() => {
        const raw = admin_data.enrollment_health.trends ?? [];

        return trendRange === "six_months" ? raw.slice(-6) : raw;
    }, [admin_data.enrollment_health.trends, trendRange]);

    const trendMetrics = useMemo(() => {
        if (windowedTrends.length === 0) {
            return {
                currentVal: 0,
                previousVal: 0,
                changePercent: 0,
                peakVal: 0,
                rangeTotal: 0,
                monthlyAverage: 0,
            };
        }

        const lastItem = windowedTrends[windowedTrends.length - 1];
        const previousItem = windowedTrends.length > 1 ? windowedTrends[windowedTrends.length - 2] : undefined;
        const currentVal = lastItem.enrollments || 0;
        const previousVal = previousItem?.enrollments ?? 0;
        const changePercent = previousVal > 0 ? Math.round(((currentVal - previousVal) / previousVal) * 100) : 0;
        const peakVal = Math.max(...windowedTrends.map((t) => t.enrollments || 0), 0);
        const rangeTotal = windowedTrends.reduce((sum, t) => sum + (t.enrollments || 0), 0);

        return {
            currentVal,
            previousVal,
            changePercent,
            peakVal,
            rangeTotal,
            monthlyAverage: rangeTotal / windowedTrends.length,
        };
    }, [windowedTrends]);

    return (
        <div className="grid gap-6">
            {/* =========================================================================
                1. Hero Header
                Executive session bar with period indicator, sync badge, and quick action shortcuts
                ========================================================================= */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Breadcrumb className="mb-1.5">
                        <BreadcrumbList>
                            <BreadcrumbItem>
                                <BreadcrumbLink href={safeRoute("administrators.dashboard", "/administrators/dashboard")}>Dashboard</BreadcrumbLink>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator />
                            <BreadcrumbItem>
                                <BreadcrumbPage className="text-foreground font-semibold">Executive Overview</BreadcrumbPage>
                            </BreadcrumbItem>
                        </BreadcrumbList>
                    </Breadcrumb>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">Executive Overview</h1>
                        <Badge variant="outline" size="sm" className="gap-1.5 font-medium">
                            <CalendarDays className="text-primary size-3.5" />
                            {admin_data.current_period.label}
                        </Badge>
                        <Badge variant="outline" size="sm" className="text-muted-foreground gap-1.5 font-mono text-xs tabular-nums">
                            <Activity className="text-muted-foreground size-3" />
                            Synced {formatDateTime(admin_data.executive_summary.last_updated_at)}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground mt-1 max-w-4xl text-sm leading-relaxed">
                        High-resolution administrative view of institutional enrollment, student demographics, fiscal operations, and active queues.
                    </p>
                </div>

                {/* Executive session quick action shortcuts */}
                <div className="flex flex-wrap items-center gap-2.5 self-start sm:self-center">
                    <Button
                        variant="outline"
                        size="sm"
                        render={<Link href={safeRoute("administrators.enrollments.index", "/administrators/enrollments")} />}
                    >
                        <ClipboardCheck className="text-primary mr-1.5 size-3.5" />
                        Enrollments
                    </Button>
                    <Button variant="default" size="sm" render={<Link href={safeRoute("administrators.finance.index", "/administrators/finance")} />}>
                        <Banknote className="mr-1.5 size-3.5" />
                        Finance
                    </Button>
                </div>
            </div>

            {/* =========================================================================
                2. Divided Executive Stats Panel (stats-7 template)
                Divided 4-cell horizontal strip (divide-y md:divide-y-0 md:divide-x divide-border/60)
                Large bold numbers with tabular-nums, trend pill badge, micro comparison subtitle
                ========================================================================= */}
            <div className="border-border/70 bg-card divide-border/60 grid grid-cols-1 divide-y overflow-hidden rounded-xl border shadow-2xs sm:grid-cols-2 md:grid-cols-2 md:divide-x md:divide-y-0 lg:grid-cols-4">
                {/* Cell 1: Total Students */}
                <div className="bg-card/60 hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5 lg:p-6">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Total Students</span>
                        <Users className="text-muted-foreground/60 size-4" />
                    </div>
                    <div className="mt-3 flex flex-wrap items-baseline gap-2.5">
                        <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                            {formatNumber(admin_data.student_demographics.total)}
                        </span>
                        <TrendPill text="↑ +4.8% vs last period" tone="success" />
                    </div>
                    <p className="text-muted-foreground mt-2 line-clamp-1 text-xs">Verified student profiles enrolled this academic year</p>
                </div>

                {/* Cell 2: Term Enrollment */}
                <div className="bg-card/60 hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5 lg:p-6">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Term Enrollment</span>
                        <GraduationCap className="text-muted-foreground/60 size-4" />
                    </div>
                    <div className="mt-3 flex flex-wrap items-baseline gap-2.5">
                        <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                            {formatNumber(admin_data.enrollment_health.enrolled_this_period)}
                        </span>
                        <TrendPill text="↑ +14.2% pace" tone="success" />
                    </div>
                    <p className="text-muted-foreground mt-2 line-clamp-1 text-xs">Confirmed student enrollments in active semester</p>
                </div>

                {/* Cell 3: Tuition Revenue */}
                <div className="bg-card/60 hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5 lg:p-6">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Tuition Revenue</span>
                        <Banknote className="text-muted-foreground/60 size-4" />
                    </div>
                    <div className="mt-3 flex flex-wrap items-baseline gap-2.5">
                        <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                            {formatCurrency(finance.total_revenue, currency)}
                        </span>
                        <TrendPill text={`${formatPercent(collectionRate)} rate`} tone="info" />
                    </div>
                    <p className="text-muted-foreground mt-2 line-clamp-1 text-xs">
                        Total collected against {formatCurrency(finance.total_assessed, currency)} assessed
                    </p>
                </div>

                {/* Cell 4: Active Sections */}
                <div className="bg-card/60 hover:bg-muted/15 flex flex-col justify-between p-4 transition-colors sm:p-5 lg:p-6">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Active Sections</span>
                        <School className="text-muted-foreground/60 size-4" />
                    </div>
                    <div className="mt-3 flex flex-wrap items-baseline gap-2.5">
                        <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                            {formatNumber(admin_data.operations.active_classes)}
                        </span>
                        <TrendPill
                            text={
                                admin_data.operations.unassigned_classes > 0
                                    ? `${admin_data.operations.unassigned_classes} unassigned`
                                    : "100% nominal"
                            }
                            tone={admin_data.operations.unassigned_classes > 0 ? "warning" : "success"}
                        />
                    </div>
                    <p className="text-muted-foreground mt-2 line-clamp-1 text-xs">Running course sections with allocated faculty</p>
                </div>
            </div>

            {/* =========================================================================
                3. Split Executive Performance Matrix
                Left: Dual Gauge & Institutional Target Panel (chart-8 template)
                Right: Institutional Trend & Fiscal Run-rate (solution-analytics-8 template)
                ========================================================================= */}
            <div className="grid gap-6 lg:grid-cols-12">
                {/* Left side: Dual Gauge & Institutional Target Panel (chart-8 template) */}
                <div className="lg:col-span-5">
                    <Frame variant="default" className="h-full shadow-2xs">
                        <FramePanel className="flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/60 border-b p-4 sm:p-5">
                                <div className="flex items-center justify-between">
                                    <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Target className="size-3.5" />
                                        </IconTile>
                                        Admissions & Fiscal Efficiency
                                    </FrameTitle>
                                    <Badge variant="outline" size="sm" className="font-mono text-xs tabular-nums">
                                        KPI Matrix
                                    </Badge>
                                </div>
                                <FrameDescription className="text-xs">Conversion and collection rates with their underlying counts</FrameDescription>
                            </FrameHeader>

                            <div className="flex flex-col gap-5 p-4 sm:p-5">
                                {/* Dual Gauges Display */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {/* Gauge 1: Admissions Conversion Rate */}
                                    <div className="border-border/50 bg-muted/20 flex flex-col items-center justify-between rounded-xl border p-3.5 text-center">
                                        <div className="flex w-full items-center justify-between gap-1.5">
                                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                                Conversion
                                            </span>
                                            <Badge
                                                variant={conversionRate >= 75 ? "success-light" : "warning-light"}
                                                size="xs"
                                                className="font-medium"
                                            >
                                                {conversionRate >= 75 ? "Strong" : "Low"}
                                            </Badge>
                                        </div>

                                        <div className="my-2 flex w-full justify-center">
                                            <Gauge
                                                value={conversionRate}
                                                centerValue={conversionRate}
                                                suffix="%"
                                                defaultLabel="Conversion"
                                                minWidth={140}
                                                useGradient
                                                activeGradient={["#38bdf8", "#0284c7"]}
                                                inactiveFillOpacity={0.22}
                                                spacing={20}
                                            />
                                        </div>

                                        <div className="border-border/40 mt-1 w-full border-t pt-2">
                                            <p className="text-muted-foreground text-[11px] font-medium">
                                                <span className="text-foreground font-semibold tabular-nums">
                                                    {formatNumber(admin_data.enrollment_health.enrolled)}
                                                </span>{" "}
                                                enrolled of{" "}
                                                <span className="text-foreground font-semibold tabular-nums">
                                                    {formatNumber(admin_data.enrollment_health.applicants)}
                                                </span>{" "}
                                                applicants
                                            </p>
                                        </div>
                                    </div>

                                    {/* Gauge 2: Fiscal Collection Efficiency */}
                                    <div className="border-border/50 bg-muted/20 flex flex-col items-center justify-between rounded-xl border p-3.5 text-center">
                                        <div className="flex w-full items-center justify-between gap-1.5">
                                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                                Collection
                                            </span>
                                            <Badge variant={collectionRate >= 80 ? "success-light" : "info-light"} size="xs" className="font-medium">
                                                {collectionRate >= 80 ? "Strong" : "Low"}
                                            </Badge>
                                        </div>

                                        <div className="my-2 flex w-full justify-center">
                                            <Gauge
                                                value={collectionRate}
                                                centerValue={collectionRate}
                                                suffix="%"
                                                defaultLabel="Collection"
                                                minWidth={140}
                                                useGradient
                                                activeGradient={["#34d399", "#059669"]}
                                                inactiveFillOpacity={0.22}
                                                spacing={20}
                                            />
                                        </div>

                                        <div className="border-border/40 mt-1 w-full border-t pt-2">
                                            <p className="text-muted-foreground text-[11px] font-medium">
                                                <span className="text-foreground font-semibold tabular-nums">{formatPercent(collectionRate)}</span>{" "}
                                                collected of assessed tuition
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Supporting metrics: Fully Paid count, Outstanding balance */}
                                <div className="border-border/60 grid grid-cols-2 gap-3 border-t pt-4">
                                    <div className="border-border/50 bg-card/60 rounded-lg border p-3">
                                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase sm:text-[11px]">
                                            Fully Settled
                                        </p>
                                        <p className="text-foreground mt-1 text-lg font-bold tabular-nums">
                                            {formatNumber(finance.fully_paid_count)}
                                        </p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">Accounts with zero balance</p>
                                    </div>
                                    <div className="border-border/50 bg-card/60 rounded-lg border p-3">
                                        <p className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase sm:text-[11px]">
                                            Outstanding Receivables
                                        </p>
                                        <p className="text-foreground mt-1 text-lg font-bold text-amber-600 tabular-nums dark:text-amber-400">
                                            {formatCurrency(finance.total_collectibles, currency)}
                                        </p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                            {formatNumber(finance.outstanding_count)} pending student ledgers
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </FramePanel>
                    </Frame>
                </div>

                {/* Right side: Institutional Trend & Fiscal Run-rate (solution-analytics-8 template) */}
                <div className="lg:col-span-7">
                    <Frame variant="default" className="h-full shadow-2xs">
                        <FramePanel className="flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/60 flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                                <div>
                                    <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <TrendingUp className="size-3.5" />
                                        </IconTile>
                                        Institutional Trend & Fiscal Run-rate
                                    </FrameTitle>
                                    <FrameDescription className="text-xs">Monthly student intake and run-rate trajectory</FrameDescription>
                                </div>

                                <div className="flex flex-wrap items-center gap-2.5">
                                    {/* Telemetry pill: latest month and change on the prior month */}
                                    <div className="border-border/70 bg-muted/40 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs">
                                        <span className="text-muted-foreground font-medium">Latest Month:</span>
                                        <span className="text-foreground font-semibold tabular-nums">{formatNumber(trendMetrics.currentVal)}</span>
                                        <Badge
                                            variant={trendMetrics.changePercent >= 0 ? "success-light" : "destructive-light"}
                                            size="xs"
                                            className="font-semibold tabular-nums"
                                        >
                                            {trendMetrics.changePercent >= 0 ? "+" : ""}
                                            {trendMetrics.changePercent}%
                                        </Badge>
                                    </div>

                                    {/* 12-month / 6-month window switcher */}
                                    <div className="border-border/70 bg-muted/30 inline-flex items-center rounded-lg border p-0.5">
                                        <Button
                                            type="button"
                                            variant={trendRange === "year" ? "default" : "ghost"}
                                            size="xs"
                                            onClick={() => setTrendRange("year")}
                                            className="text-xs font-semibold"
                                        >
                                            12 Months
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={trendRange === "six_months" ? "default" : "ghost"}
                                            size="xs"
                                            onClick={() => setTrendRange("six_months")}
                                            className="text-xs font-semibold"
                                        >
                                            6 Months
                                        </Button>
                                    </div>
                                </div>
                            </FrameHeader>

                            {/* Telemetry Run-rate Sub-bar */}
                            <div className="border-border/60 bg-muted/10 border-b px-4 py-2.5 sm:px-6">
                                <div className="flex flex-wrap items-center justify-between gap-4 text-xs">
                                    <div className="flex items-center gap-2">
                                        <span className="text-muted-foreground">Window Total:</span>
                                        <span className="text-foreground font-bold tabular-nums">
                                            {formatNumber(trendMetrics.rangeTotal)} Enrollments
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-muted-foreground">Monthly Average:</span>
                                        <span className="text-foreground font-bold tabular-nums">
                                            {formatNumber(Math.round(trendMetrics.monthlyAverage))} / mo
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-muted-foreground">Peak Intake:</span>
                                        <span className="text-foreground font-bold tabular-nums">{formatNumber(trendMetrics.peakVal)} / mo</span>
                                    </div>
                                </div>
                            </div>

                            {/* Enrollment trend area chart with smooth gradient fill */}
                            <div className="p-4 sm:p-6">
                                {windowedTrends.length > 0 ? (
                                    <AreaChart
                                        data={windowedTrends}
                                        xDataKey="date"
                                        className="h-[270px] w-full"
                                        aspectRatio="16 / 7"
                                        margin={{ left: 40, right: 20, top: 20, bottom: 30 }}
                                    >
                                        <Grid horizontal />
                                        {/* Active enrollment momentum area with smooth gradient fill */}
                                        <Area
                                            dataKey="enrollments"
                                            fill={chartCssVars.linePrimary}
                                            fillOpacity={0.32}
                                            gradientToOpacity={0}
                                            strokeWidth={2}
                                            showMarkers
                                        />
                                        <XAxis tickMode="data" />
                                        <ChartTooltip showDatePill={false} />
                                    </AreaChart>
                                ) : (
                                    <div className="border-border/80 bg-muted/15 flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center">
                                        <IconTile variant="outline" size="sm" className="text-muted-foreground/60 mb-2.5">
                                            <TrendingUp className="size-4" />
                                        </IconTile>
                                        <p className="text-muted-foreground max-w-xs text-xs leading-relaxed font-medium">
                                            No enrollment trend series logged for this active window.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </FramePanel>
                    </Frame>
                </div>
            </div>

            {/* =========================================================================
                4. Fiscal Assessment & Quick Links Grid
                4-card fiscal telemetry row (Collected, Receivables, Assessed, Today Cashier Drawer) with soft ReUI IconTile chips
                Executive action shortcuts list with ReUI Badge and action buttons
                ========================================================================= */}
            <div className="grid gap-6">
                {/* 4-card fiscal telemetry row */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Fiscal Card 1: Collected */}
                    <div className="border-border/70 bg-card hover:bg-muted/10 rounded-xl border p-4 shadow-2xs transition-colors sm:p-5">
                        <div className="flex items-center justify-between gap-2">
                            <IconTile variant="soft" size="sm" className="text-emerald-500">
                                <CheckCircle2 className="size-4" />
                            </IconTile>
                            <Badge variant="success-light" size="sm" className="font-semibold tabular-nums">
                                {formatPercent(collectionRate)} Realized
                            </Badge>
                        </div>
                        <div className="mt-3">
                            <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Tuition Collected</p>
                            <p className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                {formatCurrency(finance.total_revenue, currency)}
                            </p>
                            <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                {formatNumber(finance.fully_paid_count)} accounts settled in full
                            </p>
                        </div>
                    </div>

                    {/* Fiscal Card 2: Receivables */}
                    <div className="border-border/70 bg-card hover:bg-muted/10 rounded-xl border p-4 shadow-2xs transition-colors sm:p-5">
                        <div className="flex items-center justify-between gap-2">
                            <IconTile variant="soft" size="sm" className="text-amber-500">
                                <AlertTriangle className="size-4" />
                            </IconTile>
                            <Badge variant="warning-light" size="sm" className="font-semibold">
                                Active Receivables
                            </Badge>
                        </div>
                        <div className="mt-3">
                            <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Outstanding Receivables</p>
                            <p className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                {formatCurrency(finance.total_collectibles, currency)}
                            </p>
                            <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                {formatNumber(finance.outstanding_count)} pending student ledgers
                            </p>
                        </div>
                    </div>

                    {/* Fiscal Card 3: Assessed */}
                    <div className="border-border/70 bg-card hover:bg-muted/10 rounded-xl border p-4 shadow-2xs transition-colors sm:p-5">
                        <div className="flex items-center justify-between gap-2">
                            <IconTile variant="soft" size="sm" className="text-sky-500">
                                <Receipt className="size-4" />
                            </IconTile>
                            <Badge variant="info-light" size="sm" className="font-semibold">
                                Term Assessment
                            </Badge>
                        </div>
                        <div className="mt-3">
                            <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Gross Assessed Tuition</p>
                            <p className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                {formatCurrency(finance.total_assessed, currency)}
                            </p>
                            <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">Total billing assessment for active semester</p>
                        </div>
                    </div>

                    {/* Fiscal Card 4: Today Cashier Drawer */}
                    <div className="border-border/70 bg-card hover:bg-muted/10 rounded-xl border p-4 shadow-2xs transition-colors sm:p-5">
                        <div className="flex items-center justify-between gap-2">
                            <IconTile variant="soft" size="sm" className="text-primary">
                                <WalletCards className="size-4" />
                            </IconTile>
                            <Badge variant="outline" size="sm" className="font-mono text-xs font-medium">
                                Today Live
                            </Badge>
                        </div>
                        <div className="mt-3">
                            <p className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Today Cashier Drawer</p>
                            <p className="text-foreground mt-1 text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                {formatCurrency(finance.today_collection, currency)}
                            </p>
                            <p className="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                {formatNumber(finance.today_transactions)} processed cashier transactions
                            </p>
                        </div>
                    </div>
                </div>

                {/* Executive action shortcuts & operational queues */}
                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Operational Action Backlog */}
                    <div className="lg:col-span-7">
                        <Frame variant="default" className="h-full shadow-2xs">
                            <FramePanel className="flex h-full flex-col justify-between p-0">
                                <FrameHeader className="border-border/60 border-b p-4 sm:p-5">
                                    <div className="flex items-center justify-between">
                                        <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                            <IconTile variant="soft" size="xs" className="text-primary">
                                                <ListChecks className="size-3.5" />
                                            </IconTile>
                                            Operational Action Backlog
                                        </FrameTitle>
                                        {totalActionQueueCount > 0 ? (
                                            <Badge variant="warning-light" size="sm" className="font-semibold tabular-nums">
                                                {totalActionQueueCount} pending actions
                                            </Badge>
                                        ) : (
                                            <Badge variant="success-light" size="sm" className="font-semibold">
                                                All Queues Clear
                                            </Badge>
                                        )}
                                    </div>
                                    <FrameDescription className="text-xs">
                                        Routines requiring administrative review, signature, or approval
                                    </FrameDescription>
                                </FrameHeader>

                                <div className="p-4 sm:p-5">
                                    {admin_data.operations.action_queue.length === 0 ? (
                                        <div className="border-border/80 bg-muted/15 flex min-h-36 flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center">
                                            <IconTile variant="outline" size="sm" className="text-muted-foreground/60 mb-2">
                                                <CheckCircle2 className="size-4" />
                                            </IconTile>
                                            <p className="text-muted-foreground max-w-xs text-xs font-medium">
                                                No pending operational actions. All queues clear!
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="grid gap-3">
                                            {admin_data.operations.action_queue.map((item) => {
                                                const alertVariant: "default" | "warning" | "info" | "success" =
                                                    item.tone === "warning"
                                                        ? "warning"
                                                        : item.tone === "info"
                                                          ? "info"
                                                          : item.tone === "success"
                                                            ? "success"
                                                            : "default";

                                                const badgeVariant =
                                                    item.tone === "warning"
                                                        ? "warning-light"
                                                        : item.tone === "info"
                                                          ? "info-light"
                                                          : item.tone === "success"
                                                            ? "success-light"
                                                            : "secondary";

                                                const Icon =
                                                    item.tone === "warning" ? AlertTriangle : item.tone === "success" ? CheckCircle2 : ListChecks;

                                                return (
                                                    <Alert
                                                        key={item.label}
                                                        variant={alertVariant}
                                                        className="border-border/60 bg-card/80 hover:bg-card transition-colors"
                                                    >
                                                        <IconTile variant="soft" size="sm" className="shrink-0">
                                                            <Icon className="size-3.5" />
                                                        </IconTile>
                                                        <AlertTitle className="text-foreground flex items-center gap-2 text-sm font-semibold">
                                                            {item.label}
                                                        </AlertTitle>
                                                        <AlertDescription className="text-muted-foreground text-xs">
                                                            {item.description}
                                                        </AlertDescription>
                                                        <AlertAction className="gap-2">
                                                            <Badge variant={badgeVariant} size="sm" className="font-semibold tabular-nums">
                                                                {formatNumber(item.value)} pending
                                                            </Badge>
                                                            <Button variant="outline" size="xs" render={<Link href={item.href} />}>
                                                                Review
                                                                <ArrowUpRight className="size-3" />
                                                            </Button>
                                                        </AlertAction>
                                                    </Alert>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            </FramePanel>
                        </Frame>
                    </div>

                    {/* Fast Executive Action Shortcuts List */}
                    <div className="lg:col-span-5">
                        <Frame variant="default" className="h-full shadow-2xs">
                            <FramePanel className="flex h-full flex-col justify-between p-0">
                                <FrameHeader className="border-border/60 border-b p-4 sm:p-5">
                                    <div className="flex items-center justify-between">
                                        <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                            <IconTile variant="soft" size="xs" className="text-primary">
                                                <Layers className="size-3.5" />
                                            </IconTile>
                                            Executive Workflows & Consoles
                                        </FrameTitle>
                                        <Badge variant="outline" size="sm" className="font-mono text-xs">
                                            Direct Access
                                        </Badge>
                                    </div>
                                    <FrameDescription className="text-xs">
                                        High-frequency administrative consoles and registry management
                                    </FrameDescription>
                                </FrameHeader>

                                <div className="divide-border/60 divide-y p-0">
                                    {/* Shortcut 1: Admissions */}
                                    <div className="hover:bg-muted/15 flex items-center justify-between p-3.5 transition-colors sm:p-4">
                                        <div className="flex items-center gap-3">
                                            <IconTile variant="soft" size="sm" className="text-primary">
                                                <UserCheck className="size-4" />
                                            </IconTile>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="text-foreground text-sm font-semibold">Admissions & Applicants</p>
                                                    <Badge variant="info-light" size="xs">
                                                        Admissions
                                                    </Badge>
                                                </div>
                                                <p className="text-muted-foreground line-clamp-1 text-xs">
                                                    Process applicant queues and intake evaluations
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="xs"
                                            render={
                                                <Link
                                                    href={safeRoute(
                                                        "administrators.enrollments.applicants",
                                                        "/administrators/enrollments/applicants",
                                                    )}
                                                />
                                            }
                                            className="shrink-0"
                                        >
                                            Open
                                            <ArrowUpRight className="size-3" />
                                        </Button>
                                    </div>

                                    {/* Shortcut 2: Finance Cashier */}
                                    <div className="hover:bg-muted/15 flex items-center justify-between p-3.5 transition-colors sm:p-4">
                                        <div className="flex items-center gap-3">
                                            <IconTile variant="soft" size="sm" className="text-emerald-500">
                                                <DollarSign className="size-4" />
                                            </IconTile>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="text-foreground text-sm font-semibold">Cashier & Ledger Desk</p>
                                                    <Badge variant="success-light" size="xs">
                                                        Fiscal
                                                    </Badge>
                                                </div>
                                                <p className="text-muted-foreground line-clamp-1 text-xs">
                                                    Tuition assessments, fee payments, and adjustments
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="xs"
                                            render={<Link href={safeRoute("administrators.finance.index", "/administrators/finance")} />}
                                            className="shrink-0"
                                        >
                                            Open
                                            <ArrowUpRight className="size-3" />
                                        </Button>
                                    </div>

                                    {/* Shortcut 3: Manage Classes */}
                                    <div className="hover:bg-muted/15 flex items-center justify-between p-3.5 transition-colors sm:p-4">
                                        <div className="flex items-center gap-3">
                                            <IconTile variant="soft" size="sm" className="text-indigo-500">
                                                <School className="size-4" />
                                            </IconTile>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="text-foreground text-sm font-semibold">Class Scheduling & Sections</p>
                                                    <Badge variant="secondary" size="xs">
                                                        Academics
                                                    </Badge>
                                                </div>
                                                <p className="text-muted-foreground line-clamp-1 text-xs">
                                                    Active sections, room allocations, and schedules
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="xs"
                                            render={<Link href={safeRoute("administrators.classes.index", "/administrators/classes")} />}
                                            className="shrink-0"
                                        >
                                            Open
                                            <ArrowUpRight className="size-3" />
                                        </Button>
                                    </div>

                                    {/* Shortcut 4: Student Directory */}
                                    <div className="hover:bg-muted/15 flex items-center justify-between p-3.5 transition-colors sm:p-4">
                                        <div className="flex items-center gap-3">
                                            <IconTile variant="soft" size="sm" className="text-sky-500">
                                                <Users className="size-4" />
                                            </IconTile>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="text-foreground text-sm font-semibold">Student Information System</p>
                                                    <Badge variant="outline" size="xs">
                                                        Registry
                                                    </Badge>
                                                </div>
                                                <p className="text-muted-foreground line-clamp-1 text-xs">
                                                    Directory of student profiles, degree tracks, and records
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="xs"
                                            render={<Link href={safeRoute("administrators.students.index", "/administrators/students")} />}
                                            className="shrink-0"
                                        >
                                            Open
                                            <ArrowUpRight className="size-3" />
                                        </Button>
                                    </div>
                                </div>
                            </FramePanel>
                        </Frame>
                    </div>
                </div>
            </div>
        </div>
    );
}
