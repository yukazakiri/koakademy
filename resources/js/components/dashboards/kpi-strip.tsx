import { FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { TrendBadge } from "@/components/trend-badge";
import { formatDeskValue, type DeskKpi, type DeskTone } from "./types";

type KpiStripProps = {
    kpis: DeskKpi[];
    /** Sub-heading shown under the panel title, e.g. the academic period. */
    caption?: string;
};

/**
 * Headline figures for a desk, rendered inside a single Frame panel.
 *
 * Part of the first paint, so this stays a plain grid of figures: no chart library and no
 * deferred data. Values use tabular-nums so digits align column-to-column.
 */
export function KpiStrip({ kpis, caption }: KpiStripProps) {
    if (kpis.length === 0) {
        return null;
    }

    return (
        <FramePanel>
            <FrameHeader>
                <FrameTitle>At a glance</FrameTitle>
                {caption ? <FrameDescription>{caption}</FrameDescription> : null}
            </FrameHeader>

            <div className="grid gap-3 px-5 pb-5 sm:grid-cols-2 lg:grid-cols-4">
                {kpis.map((kpi) => (
                    <KpiCard key={kpi.label} kpi={kpi} />
                ))}
            </div>
        </FramePanel>
    );
}

function KpiCard({ kpi }: { kpi: DeskKpi }) {
    return (
        <div className="bg-card rounded-lg border p-4">
            <div className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{kpi.label}</div>

            <div className="mt-1 text-2xl font-semibold tabular-nums">{formatDeskValue(kpi.value, kpi.format)}</div>

            {kpi.description ? <div className="text-muted-foreground mt-1 text-xs">{kpi.description}</div> : null}

            {typeof kpi.trend === "number" ? <TrendBadge value={kpi.trend} className="mt-2" /> : null}
        </div>
    );
}

/** Exported for the desk shell's empty state. */
export const KPI_TONES: DeskTone[] = ["success", "warning", "info", "neutral"];