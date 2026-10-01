import AdminLayout from "@/components/administrators/admin-layout";
import { Frame, FrameDescription, FrameFooter, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Badge } from "@/components/reui/badge";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { TrendBadge } from "@/components/trend-badge";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowRight, Inbox } from "lucide-react";

type DeskTone = "success" | "warning" | "info" | "neutral";

type DeskKpi = {
    label: string;
    value: number | string;
    description?: string;
    tone?: DeskTone;
    format?: "number" | "percent" | "currency";
    trend?: number;
};

type DeskQueue = {
    id: string;
    title: string;
    description?: string;
    count: number;
    severity: DeskTone;
    href: string;
    icon?: string;
};

type DeskTrend = {
    id: string;
    title: string;
    description?: string;
    data: Array<Record<string, string | number>>;
};

type DeskTableColumn = {
    key: string;
    label: string;
    align?: "start" | "end";
};

type DeskTable = {
    id: string;
    title: string;
    description?: string;
    columns: DeskTableColumn[];
    rows: Array<Record<string, unknown>>;
};

type DeskOption = {
    id: string;
    title: string;
    description: string;
    url: string;
};

type Props = {
    user: User;
    desk: { id: string; title: string; description: string };
    desks: DeskOption[];
    context: { period_label: string; range_label: string; range: string };
    kpis: DeskKpi[];
    queues: DeskQueue[];
    trends: DeskTrend[];
    tables: DeskTable[];
    activity: unknown[];
};

type AlertVariant = "default" | "destructive" | "warning" | "success" | "info" | "invert";

/** Desk tone -> the ReUI Alert/Badge variant that expresses it. */
const toneVariant: Record<DeskTone, AlertVariant> = {
    success: "success",
    warning: "warning",
    info: "info",
    neutral: "default",
};

function formatValue(kpi: DeskKpi): string {
    const { value, format } = kpi;

    if (typeof value === "string") {
        return value;
    }

    if (format === "currency") {
        return new Intl.NumberFormat("en-PH", {
            style: "currency",
            currency: "PHP",
            maximumFractionDigits: 0,
        }).format(value);
    }

    return new Intl.NumberFormat("en-PH").format(value);
}

export default function AdministratorDesk({ desk, desks, context, kpis, queues, tables }: Props) {
    const switchDesk = (url: string) => {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout title={desk.title}>
            <Head title={`${desk.title} Dashboard`} />

            <Frame spacing="sm" dense>
                {/* Desk header + switcher. First paint starts here. */}
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
                        <div className="flex flex-wrap items-center gap-2 text-muted-foreground text-xs">
                            <span>{context.period_label}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span>{context.range_label}</span>
                        </div>
                    </FrameFooter>
                </FramePanel>

                {/* KPI strip */}
                {kpis.length > 0 ? (
                    <FramePanel>
                        <FrameHeader>
                            <FrameTitle>At a glance</FrameTitle>
                            <FrameDescription>Headline figures for {context.period_label}.</FrameDescription>
                        </FrameHeader>
                        <div className="grid gap-3 px-5 pb-5 sm:grid-cols-2 lg:grid-cols-4">
                            {kpis.map((kpi) => (
                                <div key={kpi.label} className="bg-card rounded-lg border p-4">
                                    <div className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                        {kpi.label}
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold tabular-nums">{formatValue(kpi)}</div>
                                    {kpi.description ? (
                                        <div className="text-muted-foreground mt-1 text-xs">{kpi.description}</div>
                                    ) : null}
                                    {typeof kpi.trend === "number" ? <TrendBadge value={kpi.trend} className="mt-2" /> : null}
                                </div>
                            ))}
                        </div>
                    </FramePanel>
                ) : null}

                {/* Attention queue */}
                {queues.length > 0 ? (
                    <FramePanel>
                        <FrameHeader>
                            <FrameTitle>Needs attention</FrameTitle>
                            <FrameDescription>Items waiting on this desk.</FrameDescription>
                        </FrameHeader>
                        <div className="grid gap-2 px-5 pb-5">
                            {queues.map((item) => (
                                <Alert key={item.id} variant={toneVariant[item.severity]}>
                                    <Inbox aria-hidden="true" className="size-4" />
                                    <AlertTitle>{item.title}</AlertTitle>
                                    {item.description ? <AlertDescription>{item.description}</AlertDescription> : null}
                                    <AlertAction>
                                        <Badge variant={toneVariant[item.severity]}>{item.count}</Badge>
                                        <Button variant="outline" size="sm" render={<Link href={item.href} />}>
                                            Open
                                            <ArrowRight aria-hidden="true" className="size-3.5" />
                                        </Button>
                                    </AlertAction>
                                </Alert>
                            ))}
                        </div>
                    </FramePanel>
                ) : null}

                {/* Tables (deferred payload) */}
                {tables.map((table) => (
                    <FramePanel key={table.id}>
                        <FrameHeader>
                            <FrameTitle>{table.title}</FrameTitle>
                            {table.description ? <FrameDescription>{table.description}</FrameDescription> : null}
                        </FrameHeader>
                        <div className="px-5 pb-5">
                            {table.rows.length === 0 ? (
                                <p className="text-muted-foreground py-6 text-center text-sm">No records yet.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            {table.columns.map((column) => (
                                                <TableHead key={column.key} className={column.align === "end" ? "text-right" : undefined}>
                                                    {column.label}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {table.rows.map((row, index) => (
                                            <TableRow key={String(row.id ?? index)}>
                                                {table.columns.map((column) => (
                                                    <TableCell
                                                        key={column.key}
                                                        className={column.align === "end" ? "text-right tabular-nums" : undefined}
                                                    >
                                                        {String(row[column.key] ?? "—")}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    </FramePanel>
                ))}
            </Frame>
        </AdminLayout>
    );
}