import { Badge, type BadgeProps } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import {
    Timeline,
    TimelineContent,
    TimelineDate,
    TimelineHeader,
    TimelineIndicator,
    TimelineItem,
    TimelineSeparator,
    TimelineTitle,
} from "@/components/reui/timeline";
import { History, User } from "lucide-react";
import type { EditHistoryItem } from "./types";

export interface AuditHistoryPanelProps {
    history: EditHistoryItem[];
    formatCurrency: (amount: number) => string;
}

interface ParsedSectionChange {
    subjectCode: string;
    oldSection: string | null;
    newSection: string | null;
}

interface ParsedAuditChanges {
    addedSubjects: string[];
    removedSubjects: string[];
    sectionChanges: ParsedSectionChange[];
    feeDelta: number | null;
    changeReason: string | null;
    overloadSection: string | null;
    overloadCounts: string | null;
    overrideWindow: boolean;
    overrideHolds: boolean;
    notifyStudent: boolean;
}

function getEventPresentation(event: string): {
    label: string;
    variant: BadgeProps["variant"];
} {
    switch (event) {
        case "enrollment_edited":
            return { label: "Enrollment Edited", variant: "info-light" };
        case "class_overload_approved":
            return { label: "Class Overload Approved", variant: "warning-light" };
        case "enrollment_created":
        case "created":
            return { label: "Enrollment Created", variant: "success-light" };
        case "updated":
            return { label: "Updated", variant: "info-light" };
        default: {
            const label = event
                .replace(/[_-]+/g, " ")
                .trim()
                .replace(/\b\w/g, (char) => char.toUpperCase());
            return { label: label || "Activity", variant: "outline" };
        }
    }
}

function formatTimestamp(isoString: string | null): string {
    if (!isoString) return "Unknown time";
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return isoString;
    return new Intl.DateTimeFormat("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(date);
}

function toStringArray(value: unknown): string[] {
    if (!Array.isArray(value)) return [];
    return value
        .map((entry) => (typeof entry === "string" || typeof entry === "number" ? String(entry).trim() : ""))
        .filter((entry) => entry.length > 0);
}

function toSectionChanges(value: unknown): ParsedSectionChange[] {
    if (!Array.isArray(value)) return [];
    const parsed: ParsedSectionChange[] = [];
    for (const item of value) {
        if (!item || typeof item !== "object") continue;
        const record = item as Record<string, unknown>;
        const subjectCode =
            typeof record.subject_code === "string" && record.subject_code.trim()
                ? record.subject_code.trim()
                : null;
        if (!subjectCode) continue;
        const oldSection =
            typeof record.old_section === "string" && record.old_section.trim()
                ? record.old_section.trim()
                : null;
        const newSection =
            typeof record.new_section === "string" && record.new_section.trim()
                ? record.new_section.trim()
                : null;
        if (oldSection === newSection) continue;
        parsed.push({ subjectCode, oldSection, newSection });
    }
    return parsed;
}

function parseAuditChanges(changes: Record<string, unknown> | null | undefined): ParsedAuditChanges {
    const safe = changes && typeof changes === "object" ? changes : {};

    const rawDelta = safe.fee_delta;
    const numericDelta =
        typeof rawDelta === "number"
            ? rawDelta
            : typeof rawDelta === "string" && rawDelta.trim() !== ""
              ? Number(rawDelta)
              : null;
    const feeDelta =
        numericDelta !== null && Number.isFinite(numericDelta) && Math.abs(numericDelta) >= 0.01
            ? numericDelta
            : null;

    const changeReason =
        typeof safe.change_reason === "string" && safe.change_reason.trim().length > 0
            ? safe.change_reason.trim()
            : null;

    const overloadSection =
        typeof safe.section === "string" && safe.section.trim().length > 0
            ? safe.section.trim()
            : null;
    const enrolledCount = typeof safe.enrolled_count === "number" ? safe.enrolled_count : null;
    const maxSlots = typeof safe.maximum_slots === "number" ? safe.maximum_slots : null;
    const overloadCounts =
        enrolledCount !== null && maxSlots !== null ? `${enrolledCount}/${maxSlots}` : null;

    return {
        addedSubjects: toStringArray(safe.added_subjects),
        removedSubjects: toStringArray(safe.removed_subjects),
        sectionChanges: toSectionChanges(safe.section_changes),
        feeDelta,
        changeReason,
        overloadSection,
        overloadCounts,
        overrideWindow: safe.override_window === true,
        overrideHolds: safe.override_holds === true,
        notifyStudent: safe.notify_student === true,
    };
}

export function AuditHistoryPanel({ history, formatCurrency }: AuditHistoryPanelProps) {
    return (
        <Frame className="w-full min-w-0">
            <FrameHeader className="flex-row items-center justify-between gap-2">
                <div className="min-w-0">
                    <FrameTitle>Enrollment Audit History</FrameTitle>
                    <FrameDescription>
                        Persisted registrar edits, tuition adjustments, and capacity overrides.
                    </FrameDescription>
                </div>
                <Badge variant="outline" size="sm" className="shrink-0 tabular-nums">
                    {history.length} {history.length === 1 ? "entry" : "entries"}
                </Badge>
            </FrameHeader>

            <FramePanel>
                {history.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-8 text-center">
                        <IconTile variant="frame" size="default" className="text-muted-foreground">
                            <History />
                        </IconTile>
                        <div className="space-y-0.5">
                            <p className="text-sm font-medium text-foreground">No edit history yet</p>
                            <p className="max-w-sm text-xs text-muted-foreground">
                                Saved subject changes, section reassignments, and approved overrides will
                                appear here chronologically.
                            </p>
                        </div>
                    </div>
                ) : (
                    <Timeline value={history.length} orientation="vertical">
                        {history.map((item, index) => {
                            const eventMeta = getEventPresentation(item.event);
                            const parsed = parseAuditChanges(item.changes);
                            const hasChips =
                                parsed.addedSubjects.length > 0 ||
                                parsed.removedSubjects.length > 0 ||
                                parsed.sectionChanges.length > 0 ||
                                parsed.feeDelta !== null ||
                                parsed.overloadSection !== null ||
                                parsed.overrideWindow ||
                                parsed.overrideHolds ||
                                parsed.notifyStudent ||
                                parsed.changeReason !== null;

                            return (
                                <TimelineItem key={item.id} step={index + 1}>
                                    <TimelineHeader className="space-y-1">
                                        <TimelineSeparator />
                                        <TimelineIndicator />
                                        <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                <Badge variant={eventMeta.variant} size="sm">
                                                    {eventMeta.label}
                                                </Badge>
                                                <TimelineTitle className="inline-flex items-center gap-1 text-xs font-medium text-foreground">
                                                    <User className="size-3 shrink-0 text-muted-foreground" />
                                                    <span className="truncate">
                                                        {item.causer_name || "System"}
                                                    </span>
                                                </TimelineTitle>
                                            </div>
                                            <TimelineDate className="mb-0 text-[11px] tabular-nums">
                                                {formatTimestamp(item.created_at)}
                                            </TimelineDate>
                                        </div>
                                    </TimelineHeader>

                                    <TimelineContent className="mt-1.5 space-y-2">
                                        {item.description && (
                                            <p className="break-words text-xs leading-relaxed text-foreground/90">
                                                {item.description}
                                            </p>
                                        )}

                                        {hasChips && (
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                {parsed.addedSubjects.map((code) => (
                                                    <Badge
                                                        key={`add-${code}`}
                                                        variant="success-light"
                                                        size="xs"
                                                        className="font-mono"
                                                    >
                                                        +{code}
                                                    </Badge>
                                                ))}

                                                {parsed.removedSubjects.map((code) => (
                                                    <Badge
                                                        key={`rem-${code}`}
                                                        variant="destructive-light"
                                                        size="xs"
                                                        className="font-mono"
                                                    >
                                                        -{code}
                                                    </Badge>
                                                ))}

                                                {parsed.sectionChanges.map((change) => (
                                                    <Badge
                                                        key={`sec-${change.subjectCode}-${change.newSection ?? "none"}`}
                                                        variant="info-light"
                                                        size="xs"
                                                    >
                                                        {change.subjectCode}: {change.oldSection ?? "TBA"} →{" "}
                                                        {change.newSection ?? "TBA"}
                                                    </Badge>
                                                ))}

                                                {parsed.feeDelta !== null && (
                                                    <Badge
                                                        variant={
                                                            parsed.feeDelta > 0
                                                                ? "warning-light"
                                                                : "success-light"
                                                        }
                                                        size="xs"
                                                        className="tabular-nums"
                                                    >
                                                        Δ {parsed.feeDelta > 0 ? "+" : "-"}
                                                        {formatCurrency(Math.abs(parsed.feeDelta))}
                                                    </Badge>
                                                )}

                                                {parsed.overloadSection && (
                                                    <Badge
                                                        variant="warning-light"
                                                        size="xs"
                                                        className="tabular-nums"
                                                    >
                                                        Sec {parsed.overloadSection}
                                                        {parsed.overloadCounts
                                                            ? ` (${parsed.overloadCounts})`
                                                            : ""}
                                                    </Badge>
                                                )}

                                                {parsed.overrideWindow && (
                                                    <Badge variant="warning-light" size="xs">
                                                        Window Override
                                                    </Badge>
                                                )}

                                                {parsed.overrideHolds && (
                                                    <Badge variant="warning-light" size="xs">
                                                        Hold Override
                                                    </Badge>
                                                )}

                                                {parsed.notifyStudent && (
                                                    <Badge variant="info-light" size="xs">
                                                        Student Notified
                                                    </Badge>
                                                )}

                                                {parsed.changeReason && (
                                                    <Badge
                                                        variant="outline"
                                                        size="xs"
                                                        className="h-auto max-w-full py-0.5 text-left whitespace-normal break-words"
                                                    >
                                                        Reason: {parsed.changeReason}
                                                    </Badge>
                                                )}
                                            </div>
                                        )}
                                    </TimelineContent>
                                </TimelineItem>
                            );
                        })}
                    </Timeline>
                )}
            </FramePanel>
        </Frame>
    );
}
