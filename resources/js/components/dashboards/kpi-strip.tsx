import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { TrendBadge } from "@/components/trend-badge";
import { cn } from "@/lib/utils";
import {
    Activity,
    AlertTriangle,
    Banknote,
    BookOpen,
    Briefcase,
    Building2,
    CheckCircle2,
    GraduationCap,
    HeartHandshake,
    Percent,
    Server,
    ShieldAlert,
    TrendingUp,
    Users,
    type LucideIcon,
} from "lucide-react";
import { useId, useMemo } from "react";
import { formatDeskValue, type DeskKpi, type DeskTone } from "./types";

type KpiStripProps = {
    kpis: DeskKpi[];
    /** Sub-heading shown under the panel title, e.g. the academic period. */
    caption?: string;
    /** Layout style: standard responsive card grid, or continuous divided stats ribbon (stats-7 style). */
    layout?: "grid" | "divided";
};

const TONE_CONFIG: Record<
    DeskTone,
    {
        textClass: string;
        bgClass: string;
        borderClass: string;
        indicatorClass: string;
        hexColor: string;
    }
> = {
    success: {
        textClass: "text-emerald-600 dark:text-emerald-400",
        bgClass: "text-emerald-600 dark:text-emerald-400",
        borderClass: "hover:border-emerald-500/40",
        indicatorClass: "bg-emerald-500",
        hexColor: "rgb(16, 185, 129)",
    },
    warning: {
        textClass: "text-amber-600 dark:text-amber-400",
        bgClass: "text-amber-600 dark:text-amber-400",
        borderClass: "hover:border-amber-500/40",
        indicatorClass: "bg-amber-500",
        hexColor: "rgb(245, 158, 11)",
    },
    info: {
        textClass: "text-sky-600 dark:text-sky-400",
        bgClass: "text-sky-600 dark:text-sky-400",
        borderClass: "hover:border-sky-500/40",
        indicatorClass: "bg-sky-500",
        hexColor: "rgb(14, 165, 233)",
    },
    destructive: {
        textClass: "text-rose-600 dark:text-rose-400",
        bgClass: "text-rose-600 dark:text-rose-400",
        borderClass: "hover:border-rose-500/40",
        indicatorClass: "bg-rose-500",
        hexColor: "rgb(239, 68, 68)",
    },
    neutral: {
        textClass: "text-primary",
        bgClass: "text-primary",
        borderClass: "hover:border-primary/30",
        indicatorClass: "bg-primary",
        hexColor: "rgb(99, 102, 241)",
    },
};

function getKpiIcon(kpi: DeskKpi): LucideIcon {
    const label = kpi.label.toLowerCase();
    if (
        kpi.format === "currency" ||
        label.includes("collected") ||
        label.includes("tuition") ||
        label.includes("paid") ||
        label.includes("revenue") ||
        label.includes("payer") ||
        label.includes("balance")
    ) {
        return Banknote;
    }
    if (label.includes("faculty") || label.includes("instructor") || label.includes("teacher")) {
        return Briefcase;
    }
    if (label.includes("staff") || label.includes("personnel")) {
        return Users;
    }
    if (
        label.includes("student") ||
        label.includes("enrolled") ||
        label.includes("headcount") ||
        label.includes("applicant") ||
        label.includes("admissions")
    ) {
        return GraduationCap;
    }
    if (label.includes("program") || label.includes("course") || label.includes("curriculum") || label.includes("class")) {
        return BookOpen;
    }
    if (label.includes("department") || label.includes("section")) {
        return Building2;
    }
    if (label.includes("ticket") || label.includes("asset") || label.includes("borrowing") || label.includes("telemetry")) {
        return Server;
    }
    if (label.includes("health") || label.includes("status")) {
        return CheckCircle2;
    }
    if (label.includes("welfare") || label.includes("stalled") || label.includes("counsel")) {
        return HeartHandshake;
    }
    if (kpi.format === "percent" || label.includes("rate") || label.includes("percent")) {
        return Percent;
    }
    if (kpi.tone === "destructive") {
        return ShieldAlert;
    }
    if (kpi.tone === "warning") {
        return AlertTriangle;
    }
    if (kpi.tone === "success") {
        return TrendingUp;
    }
    return Activity;
}

function MicroSparkline({ series, tone = "neutral" }: { series: Array<{ date: string; value: number }>; tone?: DeskTone }) {
    const gradId = useId();

    if (!series || series.length < 2) {
        return null;
    }

    const values = series.map((s) => Number(s.value) || 0);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;

    const width = 88;
    const height = 28;
    const paddingX = 4;
    const paddingY = 4;

    const points = series.map((pt, i) => {
        const x = paddingX + (i / (series.length - 1)) * (width - paddingX * 2);
        const y = height - paddingY - ((Number(pt.value) - min) / range) * (height - paddingY * 2);
        return { x, y, str: `${x.toFixed(1)},${y.toFixed(1)}` };
    });

    const pathD = `M ${points.map((p) => p.str).join(" L ")}`;
    const areaD = `M ${points[0].str} L ${points.map((p) => p.str).join(" L ")} L ${(width - paddingX).toFixed(1)},${height} L ${paddingX},${height} Z`;

    const lastPoint = points[points.length - 1];
    const strokeColor = TONE_CONFIG[tone]?.hexColor ?? TONE_CONFIG.neutral.hexColor;

    return (
        <div className="relative h-7 w-22 shrink-0" title={`Trend series across ${series.length} observations`}>
            <svg viewBox={`0 0 ${width} ${height}`} className="h-full w-full overflow-visible">
                <defs>
                    <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={strokeColor} stopOpacity={0.35} />
                        <stop offset="100%" stopColor={strokeColor} stopOpacity={0.0} />
                    </linearGradient>
                </defs>
                <path d={areaD} fill={`url(#${gradId})`} />
                <path d={pathD} fill="none" stroke={strokeColor} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                {lastPoint && <circle cx={lastPoint.x} cy={lastPoint.y} r={2.5} fill={strokeColor} className="animate-pulse" />}
            </svg>
        </div>
    );
}

function MiniProgress({ value, tone }: { value: number; tone?: DeskTone }) {
    const clamped = Math.min(100, Math.max(0, value));
    const bgTone =
        tone === "success"
            ? "bg-emerald-500"
            : tone === "warning"
              ? "bg-amber-500"
              : tone === "destructive"
                ? "bg-rose-500"
                : tone === "info"
                  ? "bg-sky-500"
                  : "bg-primary";

    return (
        <div className="bg-muted/80 mt-2.5 h-1.5 w-full overflow-hidden rounded-full">
            <div className={cn("h-full rounded-full transition-all duration-500", bgTone)} style={{ width: `${clamped}%` }} />
        </div>
    );
}

function KpiCard({ kpi }: { kpi: DeskKpi }) {
    const tone = kpi.tone ?? "neutral";
    const toneConfig = TONE_CONFIG[tone] ?? TONE_CONFIG.neutral;
    const Icon = getKpiIcon(kpi);

    const percentValue = useMemo(() => {
        if (kpi.format === "percent") {
            const num = typeof kpi.value === "number" ? kpi.value : parseFloat(String(kpi.value));
            return isNaN(num) ? null : num;
        }
        if (typeof kpi.value === "string" && kpi.value.includes("%")) {
            const num = parseFloat(kpi.value);
            return isNaN(num) ? null : num;
        }
        return null;
    }, [kpi.format, kpi.value]);

    return (
        <Frame variant="default" spacing="xs" className="group shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xs">
            <FramePanel
                className={cn("bg-card relative flex flex-col justify-between overflow-hidden p-4 transition-colors sm:p-5", toneConfig.borderClass)}
            >
                {/* Subtle top indicator bar on hover */}
                <div
                    className={cn(
                        "absolute inset-x-0 top-0 h-0.5 opacity-0 transition-opacity duration-200 group-hover:opacity-100",
                        toneConfig.indicatorClass,
                    )}
                />

                {/* Header row: Label + IconTile */}
                <div className="flex items-start justify-between gap-3">
                    <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">{kpi.label}</span>
                    <IconTile
                        variant="soft"
                        size="sm"
                        className={cn("shrink-0 transition-transform duration-200 group-hover:scale-105", toneConfig.bgClass)}
                    >
                        <Icon className="size-4" />
                    </IconTile>
                </div>

                {/* Main numeric value display */}
                <div className="my-2.5 flex items-baseline justify-between gap-2">
                    <div className="text-foreground text-2xl font-bold tracking-tight tabular-nums sm:text-3xl">
                        {formatDeskValue(kpi.value, kpi.format)}
                    </div>
                    {kpi.series && kpi.series.length > 1 ? <MicroSparkline series={kpi.series} tone={tone} /> : null}
                </div>

                {/* Mini progress bar if percentage and no sparkline series */}
                {(!kpi.series || kpi.series.length <= 1) && percentValue !== null ? <MiniProgress value={percentValue} tone={tone} /> : null}

                {/* Footer row: Description + TrendBadge */}
                <div className="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs">
                    {kpi.description ? (
                        <span className="text-muted-foreground line-clamp-1 flex-1 text-[11px] leading-tight">{kpi.description}</span>
                    ) : (
                        <span />
                    )}

                    {typeof kpi.trend === "number" ? (
                        <TrendBadge value={kpi.trend} className="shrink-0 text-[11px] font-medium tabular-nums" />
                    ) : null}
                </div>
            </FramePanel>
        </Frame>
    );
}

/**
 * Divided stats-7 / card-40 ribbon style card for high-density desks (e.g. Executive Desk).
 */
function DividedKpiCell({ kpi }: { kpi: DeskKpi }) {
    const tone = kpi.tone ?? "neutral";
    const toneConfig = TONE_CONFIG[tone] ?? TONE_CONFIG.neutral;
    const Icon = getKpiIcon(kpi);

    const percentValue = useMemo(() => {
        if (kpi.format === "percent") {
            const num = typeof kpi.value === "number" ? kpi.value : parseFloat(String(kpi.value));
            return isNaN(num) ? null : num;
        }
        if (typeof kpi.value === "string" && kpi.value.includes("%")) {
            const num = parseFloat(kpi.value);
            return isNaN(num) ? null : num;
        }
        return null;
    }, [kpi.format, kpi.value]);

    return (
        <div className="group hover:bg-muted/30 relative flex flex-col justify-between p-4 transition-colors sm:p-5">
            <div
                className={cn(
                    "absolute inset-x-0 top-0 h-0.5 opacity-0 transition-opacity duration-200 group-hover:opacity-100",
                    toneConfig.indicatorClass,
                )}
            />
            <div className="flex items-start justify-between gap-2.5">
                <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">{kpi.label}</span>
                <IconTile
                    variant="soft"
                    size="xs"
                    className={cn("shrink-0 transition-transform duration-200 group-hover:scale-105", toneConfig.bgClass)}
                >
                    <Icon className="size-3.5" />
                </IconTile>
            </div>

            <div className="my-2 flex items-baseline justify-between gap-1.5">
                <div className="text-foreground text-xl font-bold tracking-tight tabular-nums sm:text-2xl">
                    {formatDeskValue(kpi.value, kpi.format)}
                </div>
                {kpi.series && kpi.series.length > 1 ? <MicroSparkline series={kpi.series} tone={tone} /> : null}
            </div>

            {(!kpi.series || kpi.series.length <= 1) && percentValue !== null ? <MiniProgress value={percentValue} tone={tone} /> : null}

            <div className="mt-1 flex items-center justify-between gap-1.5 text-xs">
                {kpi.description ? <span className="text-muted-foreground line-clamp-1 text-[10px] leading-tight">{kpi.description}</span> : <span />}
                {typeof kpi.trend === "number" ? <TrendBadge value={kpi.trend} className="shrink-0 text-[10px] font-medium tabular-nums" /> : null}
            </div>
        </div>
    );
}

/**
 * Headline figures for a desk.
 *
 * Supports standard responsive grid as well as the seamless continuous divided ribbon
 * (stats-7 / card-40 style) ideal for the executive desk.
 */
export function KpiStrip({ kpis, caption, layout = "grid" }: KpiStripProps) {
    if (kpis.length === 0) {
        return null;
    }

    if (layout === "divided") {
        return (
            <Frame variant="default" spacing="default" className="shadow-xs">
                <FramePanel className="bg-card p-0">
                    {caption ? (
                        <div className="border-border/40 flex items-center justify-between border-b px-4 py-2.5 sm:px-5">
                            <span className="text-foreground flex items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                                <span>Headline Metrics</span>
                                <Badge variant="primary-light" size="xs" radius="full" className="font-mono">
                                    {kpis.length} KPIs
                                </Badge>
                            </span>
                            <span className="text-muted-foreground text-xs">{caption}</span>
                        </div>
                    ) : null}

                    <div
                        className={cn(
                            "divide-border/50 border-border/40 grid divide-y sm:divide-x sm:divide-y-0",
                            kpis.length >= 7
                                ? "grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7"
                                : kpis.length >= 5
                                  ? "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
                                  : "grid-cols-1 sm:grid-cols-2 lg:grid-cols-4",
                        )}
                    >
                        {kpis.map((kpi) => (
                            <DividedKpiCell key={kpi.label} kpi={kpi} />
                        ))}
                    </div>
                </FramePanel>
            </Frame>
        );
    }

    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                    <div className="flex items-center justify-between">
                        <div className="space-y-0.5">
                            <FrameTitle className="text-foreground flex items-center gap-2 text-base font-semibold">
                                <span>At a glance</span>
                                <Badge variant="outline" size="sm" radius="full" className="font-mono text-[10px] font-medium uppercase">
                                    KPIs
                                </Badge>
                            </FrameTitle>
                            {caption ? <FrameDescription className="text-muted-foreground text-xs">{caption}</FrameDescription> : null}
                        </div>
                    </div>
                </FrameHeader>

                <div className="grid gap-3.5 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
                    {kpis.map((kpi) => (
                        <KpiCard key={kpi.label} kpi={kpi} />
                    ))}
                </div>
            </FramePanel>
        </Frame>
    );
}

/** Exported for desk tone compatibility. */
export const KPI_TONES: DeskTone[] = ["success", "warning", "info", "neutral", "destructive"];
