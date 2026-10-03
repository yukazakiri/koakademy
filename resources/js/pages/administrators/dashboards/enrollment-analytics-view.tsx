import { Area, AreaChart, ChartTooltip, FunnelChart, Gauge, Grid, XAxis, chartCssVars, type FunnelStage } from "@/components/charts";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { buttonVariants } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowRight,
    ArrowUpRight,
    Calendar,
    ClipboardCheck,
    Clock,
    GraduationCap,
    Sparkles,
    Target,
    TrendingUp,
    UserCheck,
    Users,
    Workflow,
    type LucideIcon,
} from "lucide-react";
import { useMemo, useState } from "react";
import type { DashboardViewProps, TrendPoint } from "./types";

function formatNumber(value: number): string {
    return new Intl.NumberFormat("en-US").format(value);
}

function formatPercent(value: number): string {
    return `${value.toFixed(1)}%`;
}

type FunnelStep = {
    title: string;
    description: string;
    count: number;
    funnelPercentage: number;
    badgeLabel: string;
    badgeTone: "info-light" | "warning-light" | "success-light" | "outline";
    icon: LucideIcon;
    iconColor: string;
    barColor: string;
};

export default function EnrollmentAnalyticsView({ user, admin_data, currency }: DashboardViewProps) {
    const [velocityRange, setVelocityRange] = useState<"12M" | "6M">("12M");
    const [funnelOrientation, setFunnelOrientation] = useState<"vertical" | "horizontal">("vertical");

    // Core admissions metric values
    const applicants = admin_data.enrollment_health?.applicants ?? 0;
    const pending = admin_data.enrollment_health?.pending ?? 0;
    const enrolled = admin_data.enrollment_health?.enrolled ?? admin_data.enrollment_health?.enrolled_this_period ?? 0;
    const onLeave = admin_data.enrollment_health?.on_leave ?? 0;

    const conversionRate = useMemo(() => {
        if (typeof admin_data.enrollment_health?.conversion_rate === "number") {
            return admin_data.enrollment_health.conversion_rate;
        }
        if (applicants > 0) {
            return (enrolled / applicants) * 100;
        }
        return 0;
    }, [admin_data.enrollment_health?.conversion_rate, applicants, enrolled]);

    const currentPeriod = admin_data.current_period ?? {
        school_year: "Current",
        semester: 1,
        label: "Active Academic Term",
    };

    /**
     * No enrolment target is configured in the payload, so rather than invent a quota the panel
     * reports the real applicant split: how much of intake is already matriculated versus still
     * awaiting a decision.
     */
    const pipelineTotals = useMemo(() => {
        const decided = enrolled + onLeave;

        return {
            decided,
            pending,
            decidedShare: applicants > 0 ? Math.round((decided / applicants) * 100) : 0,
            pendingShare: applicants > 0 ? Math.round((pending / applicants) * 100) : 0,
        };
    }, [applicants, enrolled, onLeave, pending]);

    // 4-Stage Admissions Funnel Flow (solution-analytics-3 template)
    const funnelSteps: FunnelStep[] = useMemo(() => {
        const totalBase = Math.max(applicants, enrolled, 1);
        return [
            {
                title: "Applicants Intake",
                description: "Initial submissions & prospective inquiries",
                count: applicants,
                funnelPercentage: applicants > 0 ? 100 : 0,
                badgeLabel: "Stage 01 • Intake",
                badgeTone: "info-light",
                icon: Users,
                iconColor: "text-sky-500",
                barColor: "bg-sky-500",
            },
            {
                title: "Assessment & Review",
                description: "Credential validation & registrar screening",
                count: pending,
                funnelPercentage: applicants > 0 ? (pending / totalBase) * 100 : 0,
                badgeLabel: "Stage 02 • Review",
                badgeTone: "warning-light",
                icon: ClipboardCheck,
                iconColor: "text-amber-500",
                barColor: "bg-amber-500",
            },
            {
                title: "Confirmed Enrolled",
                description: "Verified matriculations & active rosters",
                count: enrolled,
                funnelPercentage: applicants > 0 ? (enrolled / totalBase) * 100 : 0,
                badgeLabel: "Stage 03 • Enrolled",
                badgeTone: "success-light",
                icon: GraduationCap,
                iconColor: "text-emerald-500",
                barColor: "bg-emerald-500",
            },
            {
                title: "On Academic Leave",
                description: "Approved formal leaves of absence",
                count: onLeave,
                funnelPercentage: applicants > 0 ? (onLeave / totalBase) * 100 : 0,
                badgeLabel: "Stage 04 • On Leave",
                badgeTone: "outline",
                icon: Clock,
                iconColor: "text-purple-500",
                barColor: "bg-purple-500",
            },
        ];
    }, [applicants, pending, enrolled, onLeave]);

    // FunnelChart stages data
    const funnelStages: FunnelStage[] = useMemo(() => {
        return [
            {
                label: "Applicants",
                value: Math.max(applicants, 1),
                displayValue: formatNumber(applicants),
                color: "#38bdf8",
            },
            {
                label: "Assessment / Review",
                value: Math.max(pending, 1),
                displayValue: formatNumber(pending),
                color: "#f59e0b",
            },
            {
                label: "Confirmed Enrolled",
                value: Math.max(enrolled, 1),
                displayValue: formatNumber(enrolled),
                color: "#10b981",
            },
            {
                label: "On Academic Leave",
                value: Math.max(onLeave, 1),
                displayValue: formatNumber(onLeave),
                color: "#a855f7",
            },
        ];
    }, [applicants, pending, enrolled, onLeave]);

    const hasPositiveFunnelData = applicants > 0 || pending > 0 || enrolled > 0 || onLeave > 0;

    // Monthly velocity trends. Never synthesised: an empty series would otherwise show fabricated
    // enrolments and feed them into the total, average and peak readouts.
    const trendData: TrendPoint[] = useMemo(() => {
        return admin_data.enrollment_health?.trends?.length
            ? admin_data.enrollment_health.trends
            : admin_data.analytics?.enrollment_trends?.length
              ? admin_data.analytics.enrollment_trends
              : [];
    }, [admin_data.enrollment_health?.trends, admin_data.analytics?.enrollment_trends]);

    const displayedTrends = useMemo(() => {
        if (velocityRange === "6M") {
            return trendData.slice(-6);
        }
        return trendData.slice(-12);
    }, [trendData, velocityRange]);

    const velocityTotal = useMemo(() => {
        return displayedTrends.reduce((sum, p) => sum + (Number(p.enrollments) || 0), 0);
    }, [displayedTrends]);

    const velocityAverage = useMemo(() => {
        return displayedTrends.length > 0 ? velocityTotal / displayedTrends.length : 0;
    }, [displayedTrends, velocityTotal]);

    const velocityPeak = useMemo(() => {
        return displayedTrends.reduce((max, p) => Math.max(max, Number(p.enrollments) || 0), 0);
    }, [displayedTrends]);

    // Student segment breakdown. Never synthesised.
    const studentTypes = useMemo(() => {
        return admin_data.student_demographics?.by_type?.length
            ? admin_data.student_demographics.by_type
            : admin_data.analytics?.student_types?.length
              ? admin_data.analytics.student_types
              : [];
    }, [admin_data.student_demographics?.by_type, admin_data.analytics?.student_types]);

    return (
        <div className="space-y-6">
            {/* =========================================================
                1. HERO HEADER: Pipeline Command Header
                ========================================================= */}
            <Frame variant="default" className="border-border/80 from-card via-card to-muted/20 bg-gradient-to-r shadow-xs">
                <FramePanel className="bg-transparent p-5 sm:p-6">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <Badge
                                    variant="outline"
                                    size="sm"
                                    radius="full"
                                    className="border-primary/30 text-foreground bg-primary/5 gap-1.5 font-medium"
                                >
                                    <Calendar className="text-primary size-3" />
                                    <span>{currentPeriod.label || `SY ${currentPeriod.school_year} • Semester ${currentPeriod.semester}`}</span>
                                </Badge>
                                <Badge
                                    variant={conversionRate >= 70 ? "success-light" : "warning-light"}
                                    size="sm"
                                    radius="full"
                                    className="gap-1 font-semibold tabular-nums"
                                >
                                    <Sparkles className="size-3" />
                                    <span>{formatPercent(conversionRate)} Conversion Yield</span>
                                </Badge>
                                <span className="text-muted-foreground hidden text-xs sm:inline">&bull;</span>
                                <span className="text-muted-foreground hidden font-mono text-xs tracking-wider uppercase sm:inline">
                                    Admissions Command Matrix
                                </span>
                            </div>

                            <div className="space-y-1">
                                <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">Admissions & Enrollment Pipeline</h1>
                                <p className="text-muted-foreground max-w-2xl text-xs sm:text-sm">
                                    Live intake funnel analytics, conversion efficiency indices, and monthly matriculation throughput velocity.
                                </p>
                            </div>
                        </div>

                        {/* Summary Badges and Direct Action Buttons */}
                        <div className="flex flex-wrap items-center gap-2.5 self-start lg:self-center">
                            <div className="border-border/70 bg-card flex items-center gap-2 rounded-lg border px-3 py-2 shadow-2xs">
                                <IconTile variant="soft" size="xs" className="text-sky-500">
                                    <Users className="size-3.5" />
                                </IconTile>
                                <div className="leading-tight">
                                    <span className="text-muted-foreground block text-[10px] font-medium uppercase">Total Applicants</span>
                                    <span className="text-foreground text-sm font-bold tabular-nums">{formatNumber(applicants)}</span>
                                </div>
                            </div>

                            <Link
                                href="/administrators/enrollments/applicants"
                                className={cn(
                                    buttonVariants({ variant: "outline", size: "sm" }),
                                    "hover:bg-muted gap-1.5 text-xs font-semibold shadow-2xs",
                                )}
                            >
                                <ClipboardCheck className="size-3.5 text-amber-500" />
                                <span>Applicants Queue</span>
                                <Badge variant="secondary" size="xs" radius="full" className="ml-1 font-mono tabular-nums">
                                    {formatNumber(pending)}
                                </Badge>
                            </Link>

                            <Link
                                href="/administrators/enrollments"
                                className={cn(buttonVariants({ variant: "default", size: "sm" }), "gap-1.5 text-xs font-semibold shadow-xs")}
                            >
                                <GraduationCap className="size-3.5" />
                                <span>All Enrollments</span>
                                <ArrowUpRight className="size-3" />
                            </Link>
                        </div>
                    </div>
                </FramePanel>
            </Frame>

            {/* =========================================================
                2. VISUAL FUNNEL FLOW TRACKER (solution-analytics-3 template)
                ========================================================= */}
            <div className="space-y-2">
                <div className="flex items-center justify-between px-0.5">
                    <div className="flex items-center gap-2">
                        <IconTile variant="soft" size="xs" className="text-primary">
                            <Workflow className="size-3.5" />
                        </IconTile>
                        <h2 className="text-foreground text-sm font-semibold tracking-tight">Intake & Admission Workflow Stages</h2>
                    </div>
                    <span className="text-muted-foreground font-mono text-xs">
                        {currentPeriod.school_year ? `SY ${currentPeriod.school_year}` : "Current Cycle"}
                    </span>
                </div>

                <div className="relative grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {funnelSteps.map((step, idx) => (
                        <div key={step.title} className="group relative">
                            <Frame variant="default" className="border-border/80 h-full transition-all duration-200 hover:shadow-xs">
                                <FramePanel className="bg-card/70 flex flex-col justify-between p-4 backdrop-blur-xs sm:p-5">
                                    <div>
                                        <div className="mb-3 flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <IconTile variant="soft" size="sm" className={step.iconColor}>
                                                    <step.icon className="size-4" />
                                                </IconTile>
                                                <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                    Stage 0{idx + 1}
                                                </span>
                                            </div>
                                            <Badge variant={step.badgeTone} size="sm" radius="full">
                                                {step.badgeLabel}
                                            </Badge>
                                        </div>
                                        <h3 className="text-foreground text-sm font-semibold tracking-tight">{step.title}</h3>
                                        <p className="text-muted-foreground mt-0.5 line-clamp-1 text-xs">{step.description}</p>
                                    </div>

                                    <div className="border-border/40 mt-4 border-t pt-3">
                                        <div className="flex items-baseline justify-between gap-2">
                                            <span className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                                                {formatNumber(step.count)}
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <span className="text-muted-foreground text-xs font-medium">Share:</span>
                                                <span className="text-foreground text-xs font-semibold tabular-nums">
                                                    {formatPercent(step.funnelPercentage)}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Micro progress bar for stage share */}
                                        <div className="bg-muted/60 mt-2.5 h-1.5 w-full overflow-hidden rounded-full">
                                            <div
                                                className={cn("h-full rounded-full transition-all duration-500", step.barColor)}
                                                style={{ width: `${Math.min(100, Math.max(4, step.funnelPercentage))}%` }}
                                            />
                                        </div>
                                    </div>
                                </FramePanel>
                            </Frame>

                            {/* Visual connector flow arrow (desktop only, between items) */}
                            {idx < funnelSteps.length - 1 && (
                                <div
                                    className="bg-background border-border text-muted-foreground absolute top-1/2 -right-3 z-20 hidden size-6 -translate-y-1/2 items-center justify-center rounded-full border shadow-xs lg:flex"
                                    aria-hidden="true"
                                >
                                    <ArrowRight className="size-3.5" />
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {/* =========================================================
                3. DUAL FUNNEL & VELOCITY MATRIX
                ========================================================= */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Left Column: Funnel Visualizer (FunnelChart) */}
                <div className="lg:col-span-5">
                    <Frame variant="default" className="h-full shadow-xs">
                        <FramePanel className="bg-card flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/50 border-b p-4 sm:p-5">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="space-y-0.5">
                                        <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                            <IconTile variant="soft" size="xs" className="text-primary">
                                                <Workflow className="size-3.5" />
                                            </IconTile>
                                            <span>Admissions Stage Funnel</span>
                                            <Badge variant="outline" size="sm" radius="full" className="font-mono text-[10px] uppercase">
                                                Pipeline
                                            </Badge>
                                        </FrameTitle>
                                        <FrameDescription className="text-muted-foreground text-xs">
                                            Current volume progression across each admissions rung
                                        </FrameDescription>
                                    </div>

                                    {/* Orientation Toggle */}
                                    <div className="border-border/60 bg-muted/30 flex items-center gap-1 rounded-lg border p-0.5">
                                        <button
                                            type="button"
                                            onClick={() => setFunnelOrientation("vertical")}
                                            className={cn(
                                                "rounded-md px-2.5 py-1 text-xs font-medium transition-all",
                                                funnelOrientation === "vertical"
                                                    ? "bg-background text-foreground font-semibold shadow-2xs"
                                                    : "text-muted-foreground hover:text-foreground",
                                            )}
                                        >
                                            Vertical
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setFunnelOrientation("horizontal")}
                                            className={cn(
                                                "rounded-md px-2.5 py-1 text-xs font-medium transition-all",
                                                funnelOrientation === "horizontal"
                                                    ? "bg-background text-foreground font-semibold shadow-2xs"
                                                    : "text-muted-foreground hover:text-foreground",
                                            )}
                                        >
                                            Horizontal
                                        </button>
                                    </div>
                                </div>
                            </FrameHeader>

                            <div className="flex flex-1 flex-col justify-between p-4 sm:p-5">
                                {hasPositiveFunnelData ? (
                                    <div className="relative flex h-[280px] min-h-[280px] w-full items-center justify-center">
                                        <FunnelChart
                                            data={funnelStages}
                                            orientation={funnelOrientation}
                                            color={chartCssVars.linePrimary}
                                            layers={3}
                                            edges="curved"
                                            showPercentage
                                            showValues
                                            showLabels
                                        />
                                    </div>
                                ) : (
                                    <div className="flex h-[280px] flex-col items-center justify-center py-12 text-center">
                                        <IconTile variant="outline" size="lg" className="text-muted-foreground/60 mb-2.5">
                                            <Workflow className="size-5" />
                                        </IconTile>
                                        <p className="text-foreground text-sm font-semibold">No Pipeline Stages Recorded</p>
                                        <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                                            Intake data will display here once student applications are logged.
                                        </p>
                                    </div>
                                )}

                                {/* Stage-to-stage transition metrics */}
                                <div className="border-border/40 mt-4 grid grid-cols-3 gap-2 border-t pt-3 text-center">
                                    <div className="bg-muted/30 border-border/30 rounded-lg border p-2">
                                        <span className="text-muted-foreground block truncate text-[11px]">App &rarr; Review</span>
                                        <span className="text-foreground text-xs font-semibold tabular-nums">
                                            {applicants > 0 ? formatPercent((pending / applicants) * 100) : "0.0%"}
                                        </span>
                                    </div>
                                    <div className="bg-muted/30 border-border/30 rounded-lg border p-2">
                                        <span className="text-muted-foreground block truncate text-[11px]">Review &rarr; Enrolled</span>
                                        <span className="text-foreground text-xs font-semibold tabular-nums">
                                            {pending > 0 ? formatPercent((enrolled / pending) * 100) : "0.0%"}
                                        </span>
                                    </div>
                                    <div className="bg-muted/30 border-border/30 rounded-lg border p-2">
                                        <span className="text-muted-foreground block truncate text-[11px]">Leave Attrition</span>
                                        <span className="text-foreground text-xs font-semibold tabular-nums">
                                            {enrolled > 0 ? formatPercent((onLeave / enrolled) * 100) : "0.0%"}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </FramePanel>
                    </Frame>
                </div>

                {/* Right Column: Enrollment Velocity Trend (AreaChart) */}
                <div className="lg:col-span-7">
                    <Frame variant="default" className="h-full shadow-xs">
                        <FramePanel className="bg-card flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/50 border-b p-4 sm:p-5">
                                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <div className="space-y-0.5">
                                        <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                            <IconTile variant="soft" size="xs" className="text-emerald-500">
                                                <TrendingUp className="size-3.5" />
                                            </IconTile>
                                            <span>Enrollment Velocity Trend</span>
                                            <Badge variant="outline" size="sm" radius="full" className="font-mono text-[10px] uppercase">
                                                Velocity
                                            </Badge>
                                        </FrameTitle>
                                        <FrameDescription className="text-muted-foreground text-xs">
                                            Monthly registration throughput and matriculation velocity
                                        </FrameDescription>
                                    </div>

                                    {/* 12M / 6M Range Switcher */}
                                    <div className="flex items-center gap-1.5 self-start sm:self-center">
                                        <span className="text-muted-foreground hidden text-[11px] font-medium tracking-wider uppercase sm:inline">
                                            Window:
                                        </span>
                                        <div className="border-border/60 bg-muted/30 flex items-center gap-1 rounded-lg border p-0.5">
                                            <button
                                                type="button"
                                                onClick={() => setVelocityRange("6M")}
                                                className={cn(
                                                    "rounded-md px-2.5 py-1 text-xs font-medium transition-all",
                                                    velocityRange === "6M"
                                                        ? "bg-background text-foreground font-semibold shadow-2xs"
                                                        : "text-muted-foreground hover:text-foreground",
                                                )}
                                            >
                                                6M
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setVelocityRange("12M")}
                                                className={cn(
                                                    "rounded-md px-2.5 py-1 text-xs font-medium transition-all",
                                                    velocityRange === "12M"
                                                        ? "bg-background text-foreground font-semibold shadow-2xs"
                                                        : "text-muted-foreground hover:text-foreground",
                                                )}
                                            >
                                                12M
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {/* Summary metric bar */}
                                <div className="border-border/30 mt-3 flex flex-wrap items-center gap-3.5 border-t pt-2.5 text-xs">
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-muted-foreground">Period Total:</span>
                                        <span className="text-foreground font-semibold tabular-nums">{formatNumber(velocityTotal)}</span>
                                    </div>
                                    <span className="text-border" aria-hidden="true">
                                        &bull;
                                    </span>
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-muted-foreground">Monthly Avg:</span>
                                        <span className="text-foreground font-medium tabular-nums">{formatNumber(Math.round(velocityAverage))}</span>
                                    </div>
                                    <span className="text-border" aria-hidden="true">
                                        &bull;
                                    </span>
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-muted-foreground">Peak Month:</span>
                                        <span className="text-foreground font-medium tabular-nums">{formatNumber(velocityPeak)}</span>
                                    </div>
                                </div>
                            </FrameHeader>

                            <div className="p-4 sm:p-5">
                                {displayedTrends.length > 0 ? (
                                    /* Zero CLS fixed container */
                                    <div className="relative h-[260px] min-h-[260px] w-full">
                                        <AreaChart data={displayedTrends} xDataKey="date" className="h-[260px] w-full" aspectRatio="16 / 7">
                                            <Grid horizontal strokeDasharray="3 3" strokeOpacity={0.3} />
                                            <Area
                                                dataKey="enrollments"
                                                fill={chartCssVars.linePrimary}
                                                fillOpacity={0.24}
                                                stroke={chartCssVars.linePrimary}
                                                strokeWidth={2.5}
                                                showMarkers
                                            />
                                            <XAxis tickMode="data" />
                                            <ChartTooltip showDatePill={false} />
                                        </AreaChart>
                                    </div>
                                ) : (
                                    <div className="flex h-[260px] flex-col items-center justify-center px-6 text-center">
                                        <IconTile variant="soft" size="lg" className="text-muted-foreground mb-3">
                                            <TrendingUp className="size-5" />
                                        </IconTile>
                                        <p className="text-foreground text-sm font-semibold">No enrollment history</p>
                                        <p className="text-muted-foreground mt-1 max-w-sm text-xs">
                                            No monthly enrollment records exist for this period, so a velocity trend cannot be drawn yet.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </FramePanel>
                    </Frame>
                </div>
            </div>

            {/* =========================================================
                4. ADMISSIONS SEGMENT BREAKDOWN & QUICK QUEUE
                ========================================================= */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Panel A (4 cols): Conversion Efficiency Gauge & Target Meter */}
                <div className="lg:col-span-4">
                    <Frame variant="default" className="h-full shadow-xs">
                        <FramePanel className="bg-card flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/50 border-b p-4 sm:p-5">
                                <div className="flex items-center justify-between gap-2">
                                    <div className="space-y-0.5">
                                        <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                            <IconTile variant="soft" size="xs" className="text-emerald-500">
                                                <Target className="size-3.5" />
                                            </IconTile>
                                            <span>Matriculation Efficiency</span>
                                        </FrameTitle>
                                        <FrameDescription className="text-muted-foreground text-xs">
                                            Realized yield vs admissions targets
                                        </FrameDescription>
                                    </div>
                                    <Badge
                                        variant={conversionRate >= 70 ? "success-light" : "warning-light"}
                                        size="sm"
                                        radius="full"
                                        className="font-semibold"
                                    >
                                        {conversionRate >= 70 ? "Strong Yield" : "Low Yield"}
                                    </Badge>
                                </div>
                            </FrameHeader>

                            <div className="flex flex-1 flex-col justify-between p-4 sm:p-5">
                                {/* Conversion Efficiency Gauge */}
                                <div className="relative flex flex-col items-center justify-center py-1">
                                    <div className="relative mx-auto flex min-h-[190px] w-full max-w-[280px] items-center justify-center">
                                        <Gauge
                                            value={Math.min(100, Math.max(0, Number(conversionRate.toFixed(1))))}
                                            centerValue={Number(conversionRate.toFixed(1))}
                                            suffix="%"
                                            defaultLabel="Conversion Yield"
                                            minWidth={160}
                                            useGradient
                                            activeGradient={["#38bdf8", "#10b981"]}
                                            notchCornerRadius={2}
                                        />
                                    </div>
                                </div>

                                {/* Applicant decision split */}
                                <div className="border-border/40 mt-4 space-y-2.5 border-t pt-3">
                                    <div className="flex items-baseline justify-between text-xs">
                                        <span className="text-muted-foreground font-medium">Applicant Decision Split</span>
                                        <span className="text-foreground font-semibold tabular-nums">
                                            {formatNumber(pipelineTotals.decided)} decided / {formatNumber(pending)} pending
                                        </span>
                                    </div>

                                    <Progress value={pipelineTotals.decidedShare} className="h-2" />

                                    <div className="text-muted-foreground flex items-center justify-between pt-0.5 text-[11px]">
                                        <span className="tabular-nums">{pipelineTotals.decidedShare}% of intake decided</span>
                                        <Badge
                                            variant={pipelineTotals.pending === 0 ? "success-light" : "warning-light"}
                                            size="xs"
                                            radius="full"
                                            className="font-mono text-[10px]"
                                        >
                                            {pipelineTotals.pending === 0
                                                ? "No Awaiting Decision"
                                                : `${formatNumber(pipelineTotals.pending)} awaiting decision`}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </FramePanel>
                    </Frame>
                </div>

                {/* Panel B (4 cols): Segment Mix (Student Demographics) */}
                <div className="lg:col-span-4">
                    <Frame variant="default" className="h-full shadow-xs">
                        <FramePanel className="bg-card flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/50 border-b p-4 sm:p-5">
                                <div className="flex items-center justify-between gap-2">
                                    <div className="space-y-0.5">
                                        <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                            <IconTile variant="soft" size="xs" className="text-sky-500">
                                                <Users className="size-3.5" />
                                            </IconTile>
                                            <span>Admissions Segment Mix</span>
                                        </FrameTitle>
                                        <FrameDescription className="text-muted-foreground text-xs">
                                            Intake breakdown by student classification
                                        </FrameDescription>
                                    </div>
                                    <Badge variant="outline" size="sm" radius="full" className="font-mono text-[10px] uppercase">
                                        Segments
                                    </Badge>
                                </div>
                            </FrameHeader>

                            <div className="flex flex-1 flex-col justify-between space-y-4 p-4 sm:p-5">
                                <div className="space-y-3">
                                    {studentTypes.length > 0 ? (
                                        studentTypes.map((type) => {
                                            const share = type.percentage || (enrolled > 0 ? (type.count / enrolled) * 100 : 0);
                                            return (
                                                <div key={type.label} className="space-y-1.5">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="text-foreground font-medium">{type.label}</span>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-muted-foreground tabular-nums">{formatNumber(type.count)}</span>
                                                            <Badge variant="secondary" size="xs" radius="full" className="font-mono tabular-nums">
                                                                {formatPercent(share)}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                    <div className="bg-muted/60 h-1.5 w-full overflow-hidden rounded-full">
                                                        <div
                                                            className="bg-primary/70 h-full rounded-full transition-all duration-300"
                                                            style={{ width: `${Math.min(100, Math.max(2, share))}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="flex flex-col items-center justify-center px-4 py-8 text-center">
                                            <IconTile variant="soft" size="lg" className="text-muted-foreground mb-3">
                                                <Users className="size-5" />
                                            </IconTile>
                                            <p className="text-foreground text-xs font-semibold">No segment mix</p>
                                            <p className="text-muted-foreground mt-1 text-[11px]">
                                                Applicants have not been classified into admission segments yet.
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* Top course highlight if available */}
                                {admin_data.student_demographics?.top_courses?.length > 0 && (
                                    <div className="border-border/40 border-t pt-3">
                                        <span className="text-muted-foreground mb-2 block text-[11px] font-semibold tracking-wider uppercase">
                                            Leading Programs by Intake
                                        </span>
                                        <div className="flex flex-wrap gap-1.5">
                                            {admin_data.student_demographics.top_courses.slice(0, 3).map((c) => (
                                                <Badge key={c.code} variant="outline" size="sm" className="gap-1 font-mono text-xs">
                                                    <span className="text-foreground font-bold">{c.code}:</span>
                                                    <span className="text-muted-foreground tabular-nums">{c.student_count}</span>
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </FramePanel>
                    </Frame>
                </div>

                {/* Panel C (4 cols): Quick Queue & Direct Dispatch Actions */}
                <div className="lg:col-span-4">
                    <Frame variant="default" className="h-full shadow-xs">
                        <FramePanel className="bg-card flex h-full flex-col justify-between p-0">
                            <FrameHeader className="border-border/50 border-b p-4 sm:p-5">
                                <div className="flex items-center justify-between gap-2">
                                    <div className="space-y-0.5">
                                        <FrameTitle className="flex items-center gap-2 text-base font-semibold">
                                            <IconTile variant="soft" size="xs" className="text-amber-500">
                                                <ClipboardCheck className="size-3.5" />
                                            </IconTile>
                                            <span>Admissions Quick Queue</span>
                                        </FrameTitle>
                                        <FrameDescription className="text-muted-foreground text-xs">
                                            Pending approvals & workflow dispatch
                                        </FrameDescription>
                                    </div>
                                    <Badge
                                        variant={pending > 0 ? "warning-light" : "success-light"}
                                        size="sm"
                                        radius="full"
                                        className="font-mono text-[10px]"
                                    >
                                        {pending > 0 ? `${formatNumber(pending)} In Queue` : "Queue Clear"}
                                    </Badge>
                                </div>
                            </FrameHeader>

                            <div className="flex flex-1 flex-col justify-between space-y-4 p-4 sm:p-5">
                                {/* Semantic Alert */}
                                <Alert variant={pending > 0 ? "warning" : "success"} className="border-border/60">
                                    <AlertCircle className="size-4" />
                                    <AlertTitle className="text-xs font-semibold">
                                        {pending > 0
                                            ? `${formatNumber(pending)} Applications Require Review`
                                            : "All Admissions Submissions Processed"}
                                    </AlertTitle>
                                    <AlertDescription className="text-xs">
                                        {pending > 0
                                            ? "Candidates awaiting entrance evaluation, transcript verification, or cashier tuition settlement."
                                            : "All prospective student submissions have completed enrollment screening."}
                                    </AlertDescription>
                                    {pending > 0 && (
                                        <AlertAction>
                                            <Link
                                                href="/administrators/enrollments/applicants"
                                                className={cn(buttonVariants({ variant: "outline", size: "xs" }), "text-[11px] font-semibold")}
                                            >
                                                Review Now
                                            </Link>
                                        </AlertAction>
                                    )}
                                </Alert>

                                {/* Direct Action Links */}
                                <div className="space-y-2">
                                    <Link
                                        href="/administrators/enrollments/applicants"
                                        className="border-border/70 bg-card hover:bg-muted/40 group flex items-center justify-between rounded-lg border p-3 transition-all"
                                    >
                                        <div className="flex items-center gap-3">
                                            <IconTile variant="soft" size="sm" className="text-amber-500">
                                                <ClipboardCheck className="size-4" />
                                            </IconTile>
                                            <div>
                                                <span className="text-foreground group-hover:text-primary block text-xs font-semibold transition-colors">
                                                    Applicant Evaluations
                                                </span>
                                                <span className="text-muted-foreground text-[11px]">Review intake files and interview marks</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Badge variant="warning-light" size="xs" radius="full" className="font-mono tabular-nums">
                                                {formatNumber(pending)}
                                            </Badge>
                                            <ArrowUpRight className="text-muted-foreground group-hover:text-foreground size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                        </div>
                                    </Link>

                                    <Link
                                        href="/administrators/enrollments"
                                        className="border-border/70 bg-card hover:bg-muted/40 group flex items-center justify-between rounded-lg border p-3 transition-all"
                                    >
                                        <div className="flex items-center gap-3">
                                            <IconTile variant="soft" size="sm" className="text-emerald-500">
                                                <GraduationCap className="size-4" />
                                            </IconTile>
                                            <div>
                                                <span className="text-foreground group-hover:text-primary block text-xs font-semibold transition-colors">
                                                    Confirmed Enrollments
                                                </span>
                                                <span className="text-muted-foreground text-[11px]">Browse and audit all active registrations</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Badge variant="success-light" size="xs" radius="full" className="font-mono tabular-nums">
                                                {formatNumber(enrolled)}
                                            </Badge>
                                            <ArrowUpRight className="text-muted-foreground group-hover:text-foreground size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                        </div>
                                    </Link>

                                    <Link
                                        href="/administrators/enrollments/create"
                                        className="border-border hover:border-primary/50 bg-muted/20 hover:bg-muted/40 group flex items-center justify-between rounded-lg border border-dashed p-3 transition-all"
                                    >
                                        <div className="flex items-center gap-3">
                                            <IconTile
                                                variant="outline"
                                                size="sm"
                                                className="text-muted-foreground group-hover:text-primary transition-colors"
                                            >
                                                <UserCheck className="size-4" />
                                            </IconTile>
                                            <div>
                                                <span className="text-foreground group-hover:text-primary block text-xs font-semibold transition-colors">
                                                    Manual Registration
                                                </span>
                                                <span className="text-muted-foreground text-[11px]">
                                                    Directly enroll walk-in or continuing students
                                                </span>
                                            </div>
                                        </div>
                                        <ArrowUpRight className="text-muted-foreground group-hover:text-foreground size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                    </Link>
                                </div>
                            </div>
                        </FramePanel>
                    </Frame>
                </div>
            </div>
        </div>
    );
}
