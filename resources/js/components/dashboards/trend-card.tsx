import { Area, AreaChart, ChartTooltip, Grid, XAxis, chartCssVars } from "@/components/charts";
import { FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { router } from "@inertiajs/react";
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
 * ReUI ships no chart component, so this deliberately composes `@/components/charts` inside a
 * FramePanel rather than reaching for recharts. Mixing chart systems would be exactly the
 * inconsistency this kit exists to remove.
 *
 * Deferred payload: rendered only once the chart data has streamed in.
 */
export function TrendCard({ trend, range }: TrendCardProps) {
    const points = trend.data ?? [];

    // Prefer an explicit dataKey, then the first numeric field on the point, so a desk can
    // return `date`/`label`/`enrollments` or `day`/`total` without the card caring which.
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

    const total = useMemo(
        () => points.reduce((sum, point) => sum + (Number(point[dataKey]) || 0), 0),
        [points, dataKey],
    );

    if (points.length === 0) {
        return (
            <FramePanel>
                <FrameHeader>
                    <FrameTitle>{trend.title}</FrameTitle>
                    {trend.description ? <FrameDescription>{trend.description}</FrameDescription> : null}
                </FrameHeader>
                <p className="text-muted-foreground px-5 pb-5 text-sm">No data for this period yet.</p>
            </FramePanel>
        );
    }

    return (
        <FramePanel>
            <FrameHeader>
                <FrameTitle>{trend.title}</FrameTitle>
                {trend.description ? <FrameDescription>{trend.description}</FrameDescription> : null}
            </FrameHeader>

            <div className="px-5 pb-2">
                <AreaChart data={points as DeskTrendPoint[]} xDataKey={labelKey} className="h-[240px] w-full" aspectRatio="16 / 7">
                    <Grid horizontal />
                    <Area dataKey={dataKey} fill={chartCssVars.linePrimary} fillOpacity={0.36} showMarkers />
                    <XAxis tickMode="data" />
                    <ChartTooltip showDatePill={false} />
                </AreaChart>
            </div>

            <FrameFooter>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-xs">
                        Total {formatDeskValue(total, trend.format)} across {points.length} point{points.length === 1 ? "" : "s"}
                    </p>

                    {/* Two fixed windows, so a segmented control beats a calendar picker here.
                        Single mode: Radix hands back a single string, and an empty string
                        means the active item was deselected, which is ignored here. */}
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
                    >
                        {DESK_RANGES.map((option) => (
                            <ToggleGroupItem key={option.value} value={option.value}>
                                {option.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>
            </FrameFooter>
        </FramePanel>
    );
}