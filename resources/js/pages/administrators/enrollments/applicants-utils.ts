import { cn } from "@/lib/utils";
import type { ApplicantStage } from "./types";

/**
 * Presentation helpers shared by the applicants inbox.
 *
 * The stage vocabulary mirrors `App\Services\ApplicantDirectoryService` so the
 * tab strip, the stat cards and the table always agree on what an applicant
 * is waiting for.
 */

export const APPLICANT_STAGE_ORDER: ApplicantStage[] = ["awaiting_documents", "ready_for_review", "reviewed", "manual"];

export const STAGE_BADGE_CLASSES: Record<ApplicantStage, string> = {
    awaiting_documents: "border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/30 dark:text-amber-300",
    ready_for_review: "border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-800/60 dark:bg-blue-900/30 dark:text-blue-300",
    reviewed: "border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-900/30 dark:text-emerald-300",
    manual: "border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300",
    cancelled: "border-rose-200 bg-rose-100 text-rose-800 dark:border-rose-800/60 dark:bg-rose-900/30 dark:text-rose-300",
};

export const STAGE_FALLBACK_LABELS: Record<ApplicantStage, string> = {
    awaiting_documents: "Awaiting documents",
    ready_for_review: "Ready for review",
    reviewed: "Reviewed",
    manual: "Added manually",
    cancelled: "Cancelled",
};

const STUDENT_TYPE_ABBREVIATIONS: Record<string, string> = {
    college: "COL",
    shs: "SHS",
    tesda: "TES",
    dhrt: "DHRT",
};

/**
 * Normalise the `Y-m-d H:i:s` timestamps Laravel serialises so every browser
 * parses them as local time instead of falling back to an implementation
 * specific format.
 */
function parseTimestamp(value: string | null | undefined): Date | null {
    if (!value) return null;

    const normalized = value.includes("T") ? value : value.replace(" ", "T");
    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function formatDate(value: string | null | undefined, fallback = "—"): string {
    const parsed = parseTimestamp(value);

    if (!parsed) return fallback;

    return new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(parsed);
}

export function formatDateTime(value: string | null | undefined, fallback = "—"): string {
    const parsed = parseTimestamp(value);

    if (!parsed) return fallback;

    return new Intl.DateTimeFormat("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(parsed);
}

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ["year", 31536000],
    ["month", 2592000],
    ["week", 604800],
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
];

/**
 * "3 hours ago" style label used to surface how stale an application is.
 */
export function formatRelativeTime(value: string | null | undefined, fallback = "—"): string {
    const parsed = parseTimestamp(value);

    if (!parsed) return fallback;

    const deltaSeconds = (parsed.getTime() - Date.now()) / 1000;
    const absolute = Math.abs(deltaSeconds);

    if (absolute < 60) return "just now";

    const formatter = new Intl.RelativeTimeFormat("en", { numeric: "auto" });

    for (const [unit, seconds] of RELATIVE_UNITS) {
        if (absolute >= seconds) {
            return formatter.format(Math.round(deltaSeconds / seconds), unit);
        }
    }

    return formatter.format(Math.round(deltaSeconds / 60), "minute");
}

export function getInitials(name: string | null | undefined): string {
    if (!name) return "ST";

    const parts = name.replace(/,/g, " ").split(/\s+/).filter(Boolean);

    if (parts.length === 0) return "ST";

    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

export function studentTypeAbbreviation(studentType: string | null | undefined): string | null {
    if (!studentType) return null;

    return STUDENT_TYPE_ABBREVIATIONS[studentType] ?? studentType.slice(0, 4).toUpperCase();
}

export function stageLabel(stage: string, stageMeta: Record<string, { label: string }> | undefined): string {
    if (stage in STAGE_FALLBACK_LABELS) {
        return stageMeta?.[stage]?.label ?? STAGE_FALLBACK_LABELS[stage as ApplicantStage];
    }

    return stage;
}

/**
 * Requirement status badge classes.
 */
export function requirementStatusClasses(status: string): string {
    switch (status) {
        case "verified":
            return "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300";
        case "waived":
            return "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300";
        case "rejected":
            return "border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300";
        default:
            return "border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300";
    }
}

/**
 * Shared avatar fallback tone.
 */
export function avatarToneClass(isTrashed: boolean): string {
    return cn(
        "text-xs font-semibold",
        isTrashed
            ? "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300"
            : "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300",
    );
}
