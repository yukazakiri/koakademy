import { Area, AreaChart, ChartTooltip, Grid, XAxis, chartCssVars } from "@/components/charts";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { router } from "@inertiajs/react";
import { BarChart3, TrendingUp } from "lucide-react";
import { useMemo } from "react";
import { DESK_RANGES, formatDeskValue, type DeskTrend, type DeskTrendPoint } from "./types";

type TrendCardProps = {
    trend: DeskTrend;
    /** Current window from the URL, so the control reflects server state. */
    range: string;
};

/**
 * A single time series on the project's visx chart kit.
 *
 * Polished ReUI Frame + FramePanel with header stats telemetry (Total, Average, Peak),
 * Range toggle group (Year / 6 Months), and Visx AreaChart with smooth gradient fills and zero layout shift.
 */
export function TrendCard({ trend, range }: TrendCardProps) {
    const points = trend.data ?? [];

    const dataKey = useMemo(() => {
        if (trend.dataKey) {
            return trend.dataKey;
        }

        const sample = points[0];

        if (!sample) {
            return "value";
        }

        const numeric = Object.keys(sample).find((key) => key !== "date" && typeof sample[key] === "number");

        return numeric ?? "value";
    }, [points, trend.dataKey]);

    const labelKey = useMemo(() => (points[0] && "label" in points[0] ? "label" : "date"), [points]);

    const total = useMemo(() => points.reduce((sum, point) => sum + (Number(point[dataKey]) || 0), 0), [points, dataKey]);

    const average = useMemo(() => (points.length > 0 ? total / points.length : 0), [points, total]);

    const maxVal = useMemo(() => points.reduce((max, point) => Math.max(max, Number(point[dataKey]) || 0), 0), [points, dataKey]);

    if (points.length === 0) {
        return (
            <Frame variant="default" spacing="default" className="shadow-xs">
                <FramePanel className="bg-card">
                    <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <IconTile variant="soft" size="default" className="text-primary">
                                    <BarChart3 className="size-4" />
                                </IconTile>
                                <div className="space-y-0.5">
                                    <FrameTitle className="text-foreground flex items-center gap-2 text-base font-semibold">
                                        <span>{trend.title}</span>
                                        <Badge variant="outline" size="sm" radius="full" className="font-mono text-[10px] uppercase">
                                            Trend
                                        </Badge>
                                    </FrameTitle>
                                    {trend.description ? (
                                        <FrameDescription className="text-muted-foreground text-xs">{trend.description}</FrameDescription>
                                    ) : null}
                                </div>
                            </div>

                            <ToggleGroup
                                type="single"
                                variant="outline"
                                size="sm"
                                value={range}
                                onValueChange={(next: string) => {
                                    if (next && next !== range) {
                                        router.get(window.location.pathname, { range: next }, { preserveState: true, preserveScroll: true });
                                    }
                                }}
                                className="bg-muted/40 border-border/60 rounded-lg border p-0.5"
                            >
                                {DESK_RANGES.map((option) => (
                                    <ToggleGroupItem
                                        key={option.value}
                                        value={option.value}
                                        className="data-[state=on]:bg-background data-[state=on]:text-foreground rounded-md px-2.5 py-1 text-xs font-medium transition-all data-[state=on]:shadow-2xs"
                                    >
                                        {option.label}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        </div>
                    </FrameHeader>

                    <div className="border-border/80 bg-muted/20 m-4 flex flex-col items-center justify-center rounded-xl border border-dashed px-4 py-8 text-center sm:m-5">
                        <IconTile variant="outline" size="lg" className="text-muted-foreground/70 mb-3">
                            <BarChart3 className="size-5" />
                        </IconTile>
                        <p className="text-foreground text-sm font-semibold">No data points recorded</p>
                        <p className="text-muted-foreground mt-1 max-w-sm text-xs">
                            There are no trend records available for the selected {range === "year" ? "school year" : "time window"}.
                        </p>
                    </div>
                </FramePanel>
            </Frame>
        );
    }

    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <IconTile variant="soft" size="default" className="text-primary mt-0.5 shrink-0">
                                <TrendingUp className="size-4" />
                            </IconTile>
                            <div className="space-y-0.5">
                                <div className="flex items-center gap-2">
                                    <FrameTitle className="text-foreground text-base font-semibold tracking-tight">{trend.title}</FrameTitle>
                                    <Badge variant="outline" size="sm" radius="full" className="font-mono text-[10px] font-medium uppercase">
                                        Time Series
                                    </Badge>
                                </div>
                                {trend.description ? (
                                    <FrameDescription className="text-muted-foreground text-xs leading-normal">{trend.description}</FrameDescription>
                                ) : null}
                            </div>
                        </div>

                        <div className="flex items-center gap-2 self-start sm:self-center">
                            <span className="text-muted-foreground hidden text-[11px] font-medium tracking-wider uppercase sm:inline">Window:</span>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                size="sm"
                                value={range}
                                onValueChange={(next: string) => {
                                    if (next && next !== range) {
                                        router.get(window.location.pathname, { range: next }, { preserveState: true, preserveScroll: true });
                                    }
                                }}
                                className="bg-muted/40 border-border/60 rounded-lg border p-0.5"
                            >
                                {DESK_RANGES.map((option) => (
                                    <ToggleGroupItem
                                        key={option.value}
                                        value={option.value}
                                        className="data-[state=on]:bg-background data-[state=on]:text-foreground rounded-md px-2.5 py-1 text-xs font-medium transition-all data-[state=on]:shadow-2xs"
                                    >
                                        {option.label}
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        </div>
                    </div>

                    {/* Header stats telemetry bar */}
                    <div className="border-border/30 mt-3 flex flex-wrap items-center gap-3.5 border-t pt-2.5 text-xs">
                        <div className="flex items-center gap-1.5">
                            <span className="text-muted-foreground">Total:</span>
                            <span className="text-foreground font-semibold tabular-nums">{formatDeskValue(total, trend.format)}</span>
                        </div>
                        <span className="text-border" aria-hidden="true">
                            &bull;
                        </span>
                        <div className="flex items-center gap-1.5">
                            <span className="text-muted-foreground">Average:</span>
                            <span className="text-foreground font-medium tabular-nums">{formatDeskValue(Math.round(average), trend.format)}</span>
                        </div>
                        <span className="text-border" aria-hidden="true">
                            &bull;
                        </span>
                        <div className="flex items-center gap-1.5">
                            <span className="text-muted-foreground">Peak:</span>
                            <span className="text-foreground font-medium tabular-nums">{formatDeskValue(maxVal, trend.format)}</span>
                        </div>
                        <span className="text-border" aria-hidden="true">
                            &bull;
                        </span>
                        <div className="flex items-center gap-1.5">
                            <span className="text-muted-foreground">Points:</span>
                            <Badge variant="secondary" size="xs" radius="full" className="font-mono tabular-nums">
                                {points.length}
                            </Badge>
                        </div>
                    </div>
                </FrameHeader>

                <div className="p-4 sm:p-5">
                    {/* Fixed container dimensions for zero Cumulative Layout Shift (CLS) */}
                    <div className="relative h-[260px] min-h-[260px] w-full">
                        <AreaChart data={points as DeskTrendPoint[]} xDataKey={labelKey} className="h-[260px] w-full" aspectRatio="16 / 7">
                            <Grid horizontal strokeDasharray="3 3" strokeOpacity={0.3} />
                            <Area
                                dataKey={dataKey}
                                fill={chartCssVars.linePrimary}
                                fillOpacity={0.28}
                                gradientSpan={0.88}
                                gradientToOpacity={0.01}
                                stroke={chartCssVars.linePrimary}
                                strokeWidth={2.25}
                                showMarkers
                            />
                            <XAxis tickMode="data" />
                            <ChartTooltip showDatePill={false} />
                        </AreaChart>
                    </div>
                </div>
            </FramePanel>
        </Frame>
    );
}
