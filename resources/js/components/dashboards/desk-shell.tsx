import { AttentionQueue } from "@/components/dashboards/attention-queue";
import { KpiStrip } from "@/components/dashboards/kpi-strip";
import { QueueTable } from "@/components/dashboards/queue-table";
import { TrendCard } from "@/components/dashboards/trend-card";
import type { DeskContext, DeskKpi, DeskMeta, DeskOption, DeskQueueItem, DeskTable, DeskTrend } from "@/components/dashboards/types";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";

type DeskShellProps = {
    desk: DeskMeta;
    desks: DeskOption[];
    context: DeskContext;
    kpis: DeskKpi[];
    queues: DeskQueueItem[];
    trends: DeskTrend[];
    tables: DeskTable[];
};

/**
 * Shared layout for every administrative desk.
 *
 * Order is deliberate and matches the payload contract: heading, KPI strip and attention queue
 * land in the first paint, then charts, then tables. Reading top to bottom answers "how are we
 * doing" before "show me everything".
 */
export function DeskShell({ desk, desks, context, kpis, queues, trends, tables }: DeskShellProps) {
    const switchDesk = (url: string) => {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <Frame spacing="sm" dense>
            <FramePanel>
                <FrameHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <FrameTitle>{desk.title}</FrameTitle>
                            <FrameDescription>{desk.description}</FrameDescription>
                        </div>

                        {desks.length > 1 ? (
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground text-xs">Desk</span>
                                <div className="flex flex-wrap gap-1">
                                    {desks.map((option) => (
                                        <Button
                                            key={option.id}
                                            variant={option.id === desk.id ? "default" : "outline"}
                                            size="sm"
                                            onClick={() => switchDesk(option.url)}
                                        >
                                            {option.title}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </FrameHeader>

                <FrameFooter>
                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                        <span>{context.period_label}</span>
                        <span aria-hidden="true">&middot;</span>
                        <span>{context.range_label}</span>
                    </div>
                </FrameFooter>
            </FramePanel>

            {/* First paint: the two things a user acts on immediately. */}
            <KpiStrip kpis={kpis} caption={`Headline figures for ${context.period_label}.`} />
            <AttentionQueue items={queues} />

            {/* Deferred payload. */}
            {trends.map((trend) => (
                <TrendCard key={trend.id} trend={trend} range={context.range} />
            ))}

            {tables.map((table) => (
                <QueueTable key={table.id} table={table} />
            ))}
        </Frame>
    );
}