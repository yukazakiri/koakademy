import type { BadgeProps } from "@/components/reui/badge";
import type { EnrollmentRow } from "./columns";
import type { EnrollmentPipeline } from "./types";

export type ReuiBadgeVariant = NonNullable<BadgeProps["variant"]>;

export function formatMoney(value: number | null | undefined, currency: string = "PHP"): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return "—";
    }

    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
        style: "currency",
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatCompactMoney(value: number | null | undefined, currency: string = "PHP"): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return "—";
    }

    if (Math.abs(value) >= 1_000_000) {
        const symbol = currency === "USD" ? "$" : "₱";
        return `${symbol}${(value / 1_000_000).toFixed(1)}M`;
    }

    if (Math.abs(value) >= 10_000) {
        const symbol = currency === "USD" ? "$" : "₱";
        return `${symbol}${(value / 1_000).toFixed(1)}k`;
    }

    return formatMoney(value, currency);
}

export function formatShortDate(value: string | null | undefined): string {
    if (!value) return "—";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "—";

    return new Intl.DateTimeFormat("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
    }).format(date);
}

export function getInitials(name: string | null | undefined): string {
    if (!name) return "ST";

    const cleaned = name.replace(/,/g, " ").trim();
    const parts = cleaned.split(/\s+/).filter(Boolean);
    if (parts.length === 0) return "ST";
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
}

export function resolvePipelineColor(color: string | undefined | null): {
    dot: string;
    bar: string;
    badge: ReuiBadgeVariant;
    text: string;
} {
    switch ((color ?? "").toLowerCase()) {
        case "amber":
        case "yellow":
        case "orange":
            return {
                dot: "bg-amber-500",
                bar: "bg-amber-500",
                badge: "warning-light",
                text: "text-amber-600 dark:text-amber-400",
            };
        case "green":
        case "emerald":
            return {
                dot: "bg-emerald-500",
                bar: "bg-emerald-500",
                badge: "success-light",
                text: "text-emerald-600 dark:text-emerald-400",
            };
        case "blue":
        case "sky":
        case "cyan":
            return {
                dot: "bg-blue-500",
                bar: "bg-blue-500",
                badge: "info-light",
                text: "text-blue-600 dark:text-blue-400",
            };
        case "purple":
        case "violet":
        case "indigo":
            return {
                dot: "bg-violet-500",
                bar: "bg-violet-500",
                badge: "primary-light",
                text: "text-violet-600 dark:text-violet-400",
            };
        case "red":
        case "rose":
            return {
                dot: "bg-rose-500",
                bar: "bg-rose-500",
                badge: "destructive-light",
                text: "text-rose-600 dark:text-rose-400",
            };
        default:
            return {
                dot: "bg-zinc-400 dark:bg-zinc-500",
                bar: "bg-zinc-400 dark:bg-zinc-500",
                badge: "secondary",
                text: "text-muted-foreground",
            };
    }
}

export function resolveStatusMeta(
    status: string | null | undefined,
    terminalOutcome: string | null | undefined,
    pipeline: EnrollmentPipeline,
): {
    label: string;
    dot: string;
    bar: string;
    badge: ReuiBadgeVariant;
    text: string;
    isCompleted: boolean;
    isCancelled: boolean;
} {
    if (terminalOutcome === "cancelled") {
        return {
            label: "Cancelled",
            ...resolvePipelineColor("red"),
            isCompleted: false,
            isCancelled: true,
        };
    }

    const normalized = (status ?? "").trim();
    const matchedStep = pipeline.steps.find((step) => step.status === normalized);

    if (matchedStep) {
        return {
            label: matchedStep.status,
            ...resolvePipelineColor(matchedStep.color),
            isCompleted: Boolean(matchedStep.is_completion),
            isCancelled: false,
        };
    }

    if (normalized === "Enrolled" || normalized === "Completed" || normalized === pipeline.cashier_verified_status) {
        return {
            label: normalized || "Enrolled",
            ...resolvePipelineColor("green"),
            isCompleted: true,
            isCancelled: false,
        };
    }

    if (/pending/i.test(normalized)) {
        return {
            label: normalized,
            ...resolvePipelineColor("amber"),
            isCompleted: false,
            isCancelled: false,
        };
    }

    if (/dept|head|academic/i.test(normalized)) {
        return {
            label: normalized,
            ...resolvePipelineColor("blue"),
            isCompleted: false,
            isCancelled: false,
        };
    }

    if (/cashier|payment/i.test(normalized)) {
        return {
            label: normalized,
            ...resolvePipelineColor("violet"),
            isCompleted: false,
            isCancelled: false,
        };
    }

    return {
        label: normalized || "Unspecified",
        ...resolvePipelineColor("neutral"),
        isCompleted: false,
        isCancelled: false,
    };
}

export function tuitionSettlementPercent(overall: number | null | undefined, balance: number | null | undefined): number {
    if (!overall || overall <= 0) {
        return balance && balance > 0 ? 0 : 100;
    }

    const paid = Math.max(0, overall - (balance ?? 0));
    return Math.min(100, Math.max(0, Math.round((paid / overall) * 100)));
}

export function exportEnrollmentsToCsv(rows: EnrollmentRow[], filename: string): void {
    const headers = [
        "Enrollment ID",
        "Student ID",
        "Student Name",
        "Course",
        "Department",
        "Year Level",
        "Status",
        "Subjects",
        "Tuition",
        "Balance",
        "Enrolled Date",
    ];

    const escapeCell = (value: string | number | null | undefined): string => {
        const str = value === null || value === undefined ? "" : String(value);
        return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
    };

    const lines = [
        headers.join(","),
        ...rows.map((row) =>
            [
                row.id,
                row.student_number ?? row.student_id ?? "",
                row.student_name ?? "",
                row.course ?? "",
                row.department ?? "",
                row.academic_year ?? "",
                row.terminal_outcome === "cancelled" ? "Cancelled" : (row.status ?? ""),
                row.subjects_count ?? 0,
                row.tuition?.overall ?? 0,
                row.tuition?.balance ?? 0,
                row.created_at ?? "",
            ]
                .map(escapeCell)
                .join(","),
        ),
    ];

    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
