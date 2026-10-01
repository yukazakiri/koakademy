/**
 * Shared widget contract for the administrative desks.
 *
 * The backend (`App\Dashboards\Contracts\Dashboard`) is the source of truth for these shapes;
 * keep the two in step. Types live here so every desk component reads one definition instead
 * of re-declaring the same interfaces.
 */

/** Severity/emphasis of a KPI or queue item, mapped to ReUI Alert/Badge variants. */
export type DeskTone = "success" | "warning" | "info" | "neutral";

export type DeskKpi = {
    label: string;
    value: number | string;
    description?: string;
    tone?: DeskTone;
    format?: "number" | "percent" | "currency";
    trend?: number;
    /** Optional sparkline series of `{date, value}` points. */
    series?: Array<{ date: string; value: number }>;
};

export type DeskQueueItem = {
    id: string;
    title: string;
    description?: string;
    count: number;
    severity: DeskTone;
    href: string;
    /** Key into the desk icon map; unknown keys fall back to a generic icon. */
    icon?: string;
};

export type DeskTrendPoint = Record<string, string | number>;

export type DeskTrend = {
    id: string;
    title: string;
    description?: string;
    data: DeskTrendPoint[];
    /** Which numeric field on each point the chart plots. */
    dataKey?: string;
    format?: "number" | "currency";
};

export type DeskTableColumn = {
    key: string;
    label: string;
    align?: "start" | "end";
};

export type DeskTable = {
    id: string;
    title: string;
    description?: string;
    columns: DeskTableColumn[];
    rows: Array<Record<string, unknown>>;
};

export type DeskOption = {
    id: string;
    title: string;
    description: string;
    url: string;
};

export type DeskMeta = {
    id: string;
    title: string;
    description: string;
};

export type DeskContext = {
    period_label: string;
    range_label: string;
    range: string;
};

export type DeskProps = {
    desk: DeskMeta;
    desks: DeskOption[];
    context: DeskContext;
    kpis: DeskKpi[];
    queues: DeskQueueItem[];
    /** Deferred group: undefined until the desk-secondary request resolves. */
    trends?: DeskTrend[];
    /** Deferred group: undefined until the desk-secondary request resolves. */
    tables?: DeskTable[];
    activity?: unknown[];
};

/** Trend windows the backend understands (see DashboardContext::resolveRange). */
export const DESK_RANGES = [
    { value: "year", label: "School year" },
    { value: "months", label: "Last 6 months" },
] as const;

/**
 * Format a KPI for display. Currency defaults to PHP because the platform is
 * Philippines-first, matching AdministratorPortalData.
 */
export function formatDeskValue(value: number | string, format?: DeskKpi["format"]): string {
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